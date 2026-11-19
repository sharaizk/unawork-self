<?php

namespace App\Services\PodDocument;

use App\Enums\PodDocument\PodDocumentStatus;
use App\Enums\PodDocument\PodDocumentUploadStatus;
use App\Enums\PodDocument\PodDocumentVersionStatus;
use App\Exceptions\PodDocument\PodMaterialVersionChangedException;
use App\Models\Pod;
use App\Models\PodDocument;
use App\Models\PodDocumentCompletion;
use App\Models\PodDocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for calculating Outer Circle POD learning material progress and tracking
 * per-user, per-version completion history (UNA-023).
 *
 * Rules:
 *  - Considers only published, non-archived materials belonging to published PODs.
 *  - Uses the active published version of each material exclusively; draft and archived versions
 *    never alter an applicant's active completion status.
 *  - Mandatory materials determine required Outer Circle completion percentage.
 *  - Optional materials remain accessible but do not block progress or dilute required completion percentage.
 *  - When a newer version of a mandatory material is published, prior completion history is preserved,
 *    and completion of the new version is required.
 */
class PodLearningProgressService
{
    /**
     * Outer Circle learning completion percentage (0 - 100).
     */
    public function percentage(User $user, Pod $pod): int
    {
        return $this->breakdown($user, $pod)['percentage'];
    }

    /**
     * Check if all mandatory learning materials have been completed.
     */
    public function isComplete(User $user, Pod $pod): bool
    {
        return $this->breakdown($user, $pod)['complete'];
    }

    /**
     * Retrieve the next incomplete mandatory learning material in business order.
     */
    public function nextIncompleteMandatory(User $user, Pod $pod): ?PodDocument
    {
        return $this->breakdown($user, $pod)['next_mandatory'];
    }

    /**
     * Detailed breakdown of Outer Circle learning progress.
     *
     * @return array{
     *     percentage: int,
     *     required: int,
     *     complete: bool,
     *     total_mandatory: int,
     *     completed_mandatory: int,
     *     total_optional: int,
     *     completed_optional: int,
     *     total_materials: int,
     *     completed_materials: int,
     *     next_mandatory: ?PodDocument,
     *     items: Collection
     * }
     */
    public function breakdown(User $user, Pod $pod): array
    {
        $documents = $this->getOrderedPublishedItems($pod);

        // Fetch all completed published version IDs for this user in this POD
        $completedRecords = PodDocumentCompletion::where('user_id', $user->id)
            ->where('pod_id', $pod->id)
            ->get()
            ->keyBy('pod_document_version_id');

        $totalMandatory = 0;
        $completedMandatory = 0;
        $totalOptional = 0;
        $completedOptional = 0;
        $nextMandatory = null;

        $items = $documents->map(function (PodDocument $doc) use ($completedRecords, &$totalMandatory, &$completedMandatory, &$totalOptional, &$completedOptional, &$nextMandatory) {
            $pubVersion = $doc->publishedVersion();
            $completion = ($pubVersion && isset($completedRecords[$pubVersion->id]))
                ? $completedRecords[$pubVersion->id]
                : null;
            $isComplete = ($completion !== null);

            $doc->setAttribute('is_completed', $isComplete);
            $doc->setAttribute('completed_at', $completion?->completed_at);
            $doc->setAttribute('completion_record', $completion);

            if ($doc->isMandatory()) {
                $totalMandatory++;
                if ($isComplete) {
                    $completedMandatory++;
                } elseif ($nextMandatory === null) {
                    $nextMandatory = $doc;
                }
            } else {
                $totalOptional++;
                if ($isComplete) {
                    $completedOptional++;
                }
            }

            return $doc;
        });

        if ($totalMandatory === 0) {
            $percentage = 100;
            $isComplete = true;
        } else {
            $percentage = (int) round(($completedMandatory / $totalMandatory) * 100);
            $isComplete = ($completedMandatory === $totalMandatory);
        }

        return [
            'percentage' => $percentage,
            'required' => 100,
            'complete' => $isComplete,
            'total_mandatory' => $totalMandatory,
            'completed_mandatory' => $completedMandatory,
            'total_optional' => $totalOptional,
            'completed_optional' => $completedOptional,
            'total_materials' => $documents->count(),
            'completed_materials' => $completedMandatory + $completedOptional,
            'next_mandatory' => $nextMandatory,
            'items' => $items,
        ];
    }

    /**
     * Check whether a specific published document is completed by the user for its active published version.
     */
    public function isDocumentCompletedByUser(PodDocument $document, User $user): bool
    {
        $version = $document->publishedVersion();
        if (!$version || !$version->isUploadReady()) {
            return false;
        }

        return PodDocumentCompletion::where('user_id', $user->id)
            ->where('pod_document_version_id', $version->id)
            ->exists();
    }

    /**
     * Get the completion record for a user against the document's active published version.
     */
    public function getCompletionRecord(PodDocument $document, User $user): ?PodDocumentCompletion
    {
        $version = $document->publishedVersion();
        if (!$version) {
            return null;
        }

        return PodDocumentCompletion::where('user_id', $user->id)
            ->where('pod_document_version_id', $version->id)
            ->first();
    }

    /**
     * Mark the active published version of a document as completed for the user.
     *
     * @param User $user
     * @param Pod $pod
     * @param PodDocument $document
     * @param int|PodDocumentVersion $version The exact published version viewed by the user.
     * @return PodDocumentCompletion
     *
     * @throws \InvalidArgumentException If document or version does not belong to the POD/document.
     * @throws \DomainException If document is not published or version upload is not ready.
     * @throws PodMaterialVersionChangedException If the version is no longer the active published version.
     */
    public function markCompleted(User $user, Pod $pod, PodDocument $document, int|PodDocumentVersion $version): PodDocumentCompletion
    {
        if ((int) $document->pod_id !== (int) $pod->id) {
            throw new \InvalidArgumentException('Document does not belong to the given POD.');
        }

        $versionId = ($version instanceof PodDocumentVersion) ? (int) $version->id : (int) $version;
        if ($versionId <= 0) {
            throw new \InvalidArgumentException('Invalid version ID provided.');
        }

        return DB::transaction(function () use ($user, $pod, $document, $versionId) {
            // 1. Lock the parent document row first (maintaining same lock order as publishVersion)
            $lockedDoc = PodDocument::whereKey($document->id)->lockForUpdate()->first();
            if (!$lockedDoc || (int) $lockedDoc->pod_id !== (int) $pod->id) {
                throw new \InvalidArgumentException('Document does not belong to the given POD.');
            }

            if (!$lockedDoc->isPublished()) {
                throw new \DomainException(__('Only published materials can be completed.'));
            }

            // 2. Lock the target version row
            $lockedVersion = PodDocumentVersion::whereKey($versionId)->lockForUpdate()->first();
            if (!$lockedVersion || (int) $lockedVersion->pod_document_id !== (int) $lockedDoc->id) {
                throw new \InvalidArgumentException('Submitted version does not belong to the specified document or POD.');
            }

            if (!$lockedVersion->isUploadReady()) {
                throw new \DomainException(__('The material version does not have a ready upload to complete.'));
            }

            // 3. Determine current active published version under lock
            $activeVersion = $lockedDoc->publishedVersion();
            if (!$activeVersion) {
                throw new \DomainException(__('The published material does not have a ready version to complete.'));
            }

            // 4. Verify that the submitted version is still published and matches active published version
            if (!$lockedVersion->isPublished() || (int) $lockedVersion->id !== (int) $activeVersion->id) {
                throw new PodMaterialVersionChangedException(
                    __('This material was recently updated with a new version. Please review the updated content before completing.')
                );
            }

            // 5. Atomic and idempotent record insertion
            return PodDocumentCompletion::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'pod_document_version_id' => $lockedVersion->id,
                ],
                [
                    'pod_id' => $pod->id,
                    'pod_document_id' => $lockedDoc->id,
                    'completed_at' => now(),
                ]
            );
        }, 3);
    }

    /**
     * Get all published materials for a POD in business-defined display order.
     */
    public function getOrderedPublishedItems(Pod $pod): Collection
    {
        return $pod->publishedDocuments()
            ->ordered()
            ->whereHas('versions', function ($q) {
                $q->where('status', PodDocumentVersionStatus::PUBLISHED->value)
                    ->where('upload_status', PodDocumentUploadStatus::READY->value);
            })
            ->with([
                'versions' => fn ($q) => $q->where('status', PodDocumentVersionStatus::PUBLISHED->value)
                    ->where('upload_status', PodDocumentUploadStatus::READY->value),
                'creator',
            ])
            ->get();
    }
}
