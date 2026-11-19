<?php

namespace App\Services\PodDocument;

use App\Enums\Pod\PodStatus;
use App\Enums\PodDocument\PodDocumentExtractionStatus;
use App\Enums\PodDocument\PodDocumentStatus;
use App\Enums\PodDocument\PodDocumentType;
use App\Enums\PodDocument\PodDocumentUploadStatus;
use App\Enums\PodDocument\PodDocumentVersionStatus;
use App\Exceptions\PodDocument\CannotDeleteDocumentWithHistoryException;
use App\Exceptions\PodDocument\CannotDeletePublishedVersionException;
use App\Exceptions\PodDocument\CannotModifyImmutableVersionException;
use App\Exceptions\PodDocument\CannotPublishIncompleteUploadException;
use App\Exceptions\PodDocument\CannotPublishNonDraftVersionException;
use App\Jobs\PodDocument\ExtractPodDocumentVersionText;
use App\Models\Pod;
use App\Models\PodDocument;
use App\Models\PodDocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for managing POD Knowledge Base documents, onboarding materials,
 * private file uploads, links, and immutable version history (UNA-020, UNA-021).
 */
class PodDocumentService
{
    /**
     * Retrieve and validate the private storage disk.
     *
     * @throws \DomainException If the configured disk is public or has public visibility.
     * @throws \InvalidArgumentException If the disk is not configured in filesystems.
     */
    public function getPrivateDisk(): string
    {
        $disk = config('pod_materials.disk', 'local');
        $this->assertPrivateDisk($disk);
        return $disk;
    }

    /**
     * Validate that the given disk is configured and strictly private.
     *
     * Rejects:
     *  - Disks named 'public'
     *  - Disks with 'visibility' => 'public'
     *  - Disks whose root path is inside public_path() or storage_path('app/public')
     *
     * @throws \DomainException If the disk is publicly accessible.
     * @throws \InvalidArgumentException If the disk does not exist in filesystems configuration.
     */
    public function assertPrivateDisk(string $disk): void
    {
        if ($disk === 'public') {
            throw new \DomainException(__('POD materials cannot be stored on a public disk. A private storage disk is required.'));
        }

        $diskConfig = config("filesystems.disks.{$disk}");
        if (!$diskConfig || !is_array($diskConfig)) {
            throw new \InvalidArgumentException("Storage disk [{$disk}] is not configured in filesystems.");
        }

        if ($this->isPublicDisk($disk)) {
            throw new \DomainException(__('POD materials cannot be stored on a public disk. A private storage disk is required.'));
        }
    }

    /**
     * Determine whether a filesystem disk is publicly accessible.
     */
    public function isPublicDisk(string $disk): bool
    {
        if ($disk === 'public') {
            return true;
        }

        $diskConfig = config("filesystems.disks.{$disk}");
        if (!is_array($diskConfig)) {
            return false;
        }

        if (($diskConfig['visibility'] ?? null) === 'public') {
            return true;
        }

        if (isset($diskConfig['root'])) {
            $root = realpath($diskConfig['root']) ?: $diskConfig['root'];
            $publicStorage = realpath(storage_path('app/public')) ?: storage_path('app/public');
            $publicPath = realpath(public_path()) ?: public_path();

            if (str_starts_with($root, $publicStorage) || str_starts_with($root, $publicPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Schedule cleanup of an unreferenced draft file after the database transaction commits.
     *
     * Rules:
     *  - Never deletes if $oldFilePath is empty or equals $newFilePath.
     *  - Never deletes if $oldFilePath is referenced by ANY published or archived version,
     *    or by any remaining document version in the database.
     *  - Defers physical deletion to DB::afterCommit() so storage is only touched after
     *    the database transaction successfully commits.
     *  - Handles storage failures gracefully without leaving database inconsistencies.
     */
    public function cleanupReplacedDraftFileAfterCommit(string $disk, ?string $oldFilePath, ?string $newFilePath = null): void
    {
        if (!$oldFilePath || $oldFilePath === $newFilePath) {
            return;
        }

        // Check if the old file is still referenced by any published or archived version
        $isReferencedByImmutable = PodDocumentVersion::query()
            ->where('file_path', $oldFilePath)
            ->where(function ($query) {
                $query->where('status', '!=', PodDocumentVersionStatus::DRAFT->value)
                    ->orWhereNotNull('published_at');
            })
            ->exists();

        if ($isReferencedByImmutable) {
            return;
        }

        // Check if any row in the database still points to this file path
        $isReferencedByAny = PodDocumentVersion::query()
            ->where('file_path', $oldFilePath)
            ->exists();

        if ($isReferencedByAny) {
            return;
        }

        // Schedule deletion to execute only after database transaction commits
        DB::afterCommit(function () use ($disk, $oldFilePath) {
            try {
                if (Storage::disk($disk)->exists($oldFilePath)) {
                    Storage::disk($disk)->delete($oldFilePath);
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to clean up replaced draft file [{$oldFilePath}]: " . $e->getMessage());
            }
        });
    }

    /**
     * Store an uploaded material file privately.
     * Validates extension, MIME type, and configurable size constraints.
     */
    public function storeUploadedFile(UploadedFile $file, Pod $pod): array
    {
        $disk = $this->getPrivateDisk();
        $maxKb = (int) config('pod_materials.max_file_size_kb', 20480);
        $allowedExtensions = config('pod_materials.allowed_extensions', ['pdf', 'doc', 'docx']);
        $allowedMimes = config('pod_materials.allowed_mimes', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);

        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $size = $file->getSize();

        if (!in_array($ext, $allowedExtensions, true) || !in_array($mime, $allowedMimes, true)) {
            return [
                'upload_status' => PodDocumentUploadStatus::FAILED,
                'failure_message' => __('Invalid file format. Allowed formats: :extensions.', [
                    'extensions' => strtoupper(implode(', ', $allowedExtensions)),
                ]),
                'file_path' => null,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $size,
                'file_mime' => $mime,
            ];
        }

        if ($size > $maxKb * 1024) {
            return [
                'upload_status' => PodDocumentUploadStatus::FAILED,
                'failure_message' => __('File size exceeds the maximum allowed limit of :limit MB.', [
                    'limit' => round($maxKb / 1024, 1),
                ]),
                'file_path' => null,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $size,
                'file_mime' => $mime,
            ];
        }

        try {
            $directory = config('pod_materials.directory', 'pod_materials') . '/' . $pod->id;
            $storedPath = $file->store($directory, $disk);

            if (!$storedPath || !Storage::disk($disk)->exists($storedPath)) {
                return [
                    'upload_status' => PodDocumentUploadStatus::FAILED,
                    'failure_message' => __('Failed to store file on private storage.'),
                    'file_path' => null,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $size,
                    'file_mime' => $mime,
                ];
            }

            return [
                'upload_status' => PodDocumentUploadStatus::READY,
                'failure_message' => null,
                'file_path' => $storedPath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $size,
                'file_mime' => $mime,
            ];
        } catch (\Throwable $e) {
            return [
                'upload_status' => PodDocumentUploadStatus::FAILED,
                'failure_message' => __('Upload failed: :error', ['error' => $e->getMessage()]),
                'file_path' => null,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $size,
                'file_mime' => $mime,
            ];
        }
    }

    /**
     * Create a new Knowledge Base document or onboarding material with its initial version.
     */
    public function createDocument(Pod $pod, User $creator, array $data): PodDocument
    {
        return DB::transaction(function () use ($pod, $creator, $data) {
            $isPublish = !empty($data['publish']);
            $type = $data['type'] instanceof PodDocumentType ? $data['type'] : PodDocumentType::from($data['type']);

            $uploadData = $this->resolveUploadData($pod, $data);

            if (isset($data['file']) && $data['file'] instanceof UploadedFile && $uploadData['upload_status'] === PodDocumentUploadStatus::FAILED) {
                throw new \DomainException(
                    $uploadData['failure_message'] ?: __('Failed to store uploaded file.')
                );
            }

            if ($isPublish && $uploadData['upload_status'] !== PodDocumentUploadStatus::READY) {
                throw new CannotPublishIncompleteUploadException();
            }

            $position = (PodDocument::where('pod_id', $pod->id)->max('position') ?? 0) + 1;

            $document = PodDocument::create([
                'pod_id' => $pod->id,
                'title' => trim($data['title']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'type' => $type,
                'status' => $isPublish ? PodDocumentStatus::PUBLISHED : PodDocumentStatus::DRAFT,
                'position' => $position,
                'is_mandatory' => !empty($data['is_mandatory']),
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]);

            $content = $this->resolveContent($data, $uploadData);

            $hasFile = !empty($uploadData['file_path']);
            $version = PodDocumentVersion::create([
                'pod_document_id' => $document->id,
                'version_number' => 1,
                'title' => $document->title,
                'content' => $content,
                'change_summary' => $data['change_summary'] ?? ($isPublish ? __('Initial published version') : __('Initial draft')),
                'file_path' => $uploadData['file_path'],
                'file_name' => $uploadData['file_name'],
                'link_url' => $uploadData['link_url'],
                'file_size' => $uploadData['file_size'],
                'file_mime' => $uploadData['file_mime'],
                'upload_status' => $uploadData['upload_status'],
                'failure_message' => $uploadData['failure_message'],
                'extraction_status' => $hasFile ? PodDocumentExtractionStatus::PENDING : PodDocumentExtractionStatus::COMPLETED,
                'extracted_text' => $hasFile ? null : $content,
                'extracted_at' => $hasFile ? null : now(),
                'status' => $isPublish ? PodDocumentVersionStatus::PUBLISHED : PodDocumentVersionStatus::DRAFT,
                'created_by' => $creator->id,
                'published_at' => $isPublish ? now() : null,
            ]);

            if ($version->isFile() && $version->isUploadReady()) {
                $this->dispatchExtractionAfterCommit($version);
            }

            return $document->fresh(['versions', 'creator']);
        });
    }

    /**
     * Update document metadata and optionally material content/file.
     * When an un-published draft file is replaced, the old file is safely cleaned up after commit.
     */
    public function updateDocument(PodDocument $document, User $user, array $data): PodDocument
    {
        return DB::transaction(function () use ($document, $user, $data) {
            // Lock parent document first (consistent lock order)
            $lockedDoc = PodDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $pod = $lockedDoc->pod;

            if (isset($data['title'])) {
                $lockedDoc->title = trim($data['title']);
            }
            if (array_key_exists('description', $data)) {
                $lockedDoc->description = $data['description'] !== null ? trim($data['description']) : null;
            }
            if (isset($data['type'])) {
                $lockedDoc->type = $data['type'] instanceof PodDocumentType ? $data['type'] : PodDocumentType::from($data['type']);
            }
            if (array_key_exists('is_mandatory', $data)) {
                $lockedDoc->is_mandatory = !empty($data['is_mandatory']);
            }
            $lockedDoc->updated_by = $user->id;

            $versionToPublish = null;
            $hasContentUpdate = isset($data['content']) || isset($data['file']) || array_key_exists('link_url', $data) || array_key_exists('upload_status', $data);

            if ($hasContentUpdate) {
                // Lock latest version to check whether it is a mutable draft
                $latestVersion = PodDocumentVersion::where('pod_document_id', $lockedDoc->id)
                    ->orderByDesc('version_number')
                    ->lockForUpdate()
                    ->first();

                $uploadData = $this->resolveUploadData($pod, $data, $latestVersion);

                if ($uploadData['upload_status'] === PodDocumentUploadStatus::FAILED) {
                    throw new \DomainException(
                        $uploadData['failure_message'] ?: __('The replacement file could not be stored. The existing draft attachment was not changed.')
                    );
                }

                $content = $this->resolveContent($data, $uploadData, $latestVersion);

                // If latest version is an un-published draft, recheck under lock and update it.
                if ($latestVersion && $latestVersion->status === PodDocumentVersionStatus::DRAFT && $latestVersion->published_at === null) {
                    $oldFilePath = $latestVersion->file_path;
                    $latestVersion->content = $content;
                    if (isset($data['change_summary'])) {
                        $latestVersion->change_summary = trim($data['change_summary']);
                    }
                    $latestVersion->file_path = $uploadData['file_path'];
                    $latestVersion->file_name = $uploadData['file_name'];
                    $latestVersion->link_url = $uploadData['link_url'];
                    $latestVersion->file_size = $uploadData['file_size'];
                    $latestVersion->file_mime = $uploadData['file_mime'];
                    $latestVersion->upload_status = $uploadData['upload_status'];
                    $latestVersion->failure_message = $uploadData['failure_message'];
                    $fileChanged = ($uploadData['file_path'] !== $oldFilePath);
                    if ($fileChanged) {
                        $latestVersion->extraction_status = !empty($uploadData['file_path']) ? PodDocumentExtractionStatus::PENDING : PodDocumentExtractionStatus::COMPLETED;
                        $latestVersion->extracted_text = !empty($uploadData['file_path']) ? null : $content;
                        $latestVersion->extracted_at = !empty($uploadData['file_path']) ? null : now();
                        $latestVersion->extraction_failure_message = null;
                        $latestVersion->extraction_token = null;
                    } elseif (!$latestVersion->isFile()) {
                        $latestVersion->extracted_text = $content;
                        $latestVersion->extraction_status = PodDocumentExtractionStatus::COMPLETED;
                        $latestVersion->extraction_token = null;
                    }
                    $latestVersion->save();
                    $versionToPublish = $latestVersion;

                    // Clean up replaced draft file after database transaction commits
                    $this->cleanupReplacedDraftFileAfterCommit($this->getPrivateDisk(), $oldFilePath, $uploadData['file_path']);

                    if ($fileChanged && $latestVersion->isFile() && $latestVersion->isUploadReady()) {
                        $this->dispatchExtractionAfterCommit($latestVersion);
                    }
                } else {
                    // Otherwise, create a new draft version to preserve history.
                    $nextVersionNumber = (PodDocumentVersion::where('pod_document_id', $lockedDoc->id)->max('version_number') ?? 0) + 1;
                    $hasFile = !empty($uploadData['file_path']);
                    $newVersion = PodDocumentVersion::create([
                        'pod_document_id' => $lockedDoc->id,
                        'version_number' => $nextVersionNumber,
                        'title' => $lockedDoc->title,
                        'content' => $content,
                        'change_summary' => $data['change_summary'] ?? __('Updated document content'),
                        'file_path' => $uploadData['file_path'],
                        'file_name' => $uploadData['file_name'],
                        'link_url' => $uploadData['link_url'],
                        'file_size' => $uploadData['file_size'],
                        'file_mime' => $uploadData['file_mime'],
                        'upload_status' => $uploadData['upload_status'],
                        'failure_message' => $uploadData['failure_message'],
                        'extraction_status' => $hasFile ? PodDocumentExtractionStatus::PENDING : PodDocumentExtractionStatus::COMPLETED,
                        'extracted_text' => $hasFile ? null : $content,
                        'extracted_at' => $hasFile ? null : now(),
                        'status' => PodDocumentVersionStatus::DRAFT,
                        'created_by' => $user->id,
                    ]);
                    $versionToPublish = $newVersion;

                    if ($newVersion->isFile() && $newVersion->isUploadReady()) {
                        $this->dispatchExtractionAfterCommit($newVersion);
                    }
                }
            }

            if (!empty($data['publish'])) {
                $targetVersion = $versionToPublish
                    ?? PodDocumentVersion::where('pod_document_id', $lockedDoc->id)
                        ->where('status', PodDocumentVersionStatus::DRAFT->value)
                        ->whereNull('published_at')
                        ->orderByDesc('version_number')
                        ->lockForUpdate()
                        ->first();

                if ($targetVersion && $targetVersion->status === PodDocumentVersionStatus::DRAFT && $targetVersion->published_at === null) {
                    $this->publishVersion($lockedDoc, $targetVersion, $user);
                } else {
                    $lockedDoc->status = PodDocumentStatus::PUBLISHED;
                    $lockedDoc->save();
                }
            } else {
                $lockedDoc->save();
            }

            return $lockedDoc->fresh(['versions', 'creator']);
        });
    }

    /**
     * Create a new draft version for a document/material.
     * When replacing an existing draft's file, clean up the old file after commit.
     */
    public function createDraftVersion(PodDocument $document, User $creator, array $data): PodDocumentVersion
    {
        return DB::transaction(function () use ($document, $creator, $data) {
            // Lock parent document first (consistent lock order)
            $lockedDoc = PodDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $pod = $lockedDoc->pod;

            $existingDraft = PodDocumentVersion::where('pod_document_id', $lockedDoc->id)
                ->where('status', PodDocumentVersionStatus::DRAFT->value)
                ->whereNull('published_at')
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            $priorVersion = $existingDraft ?? $lockedDoc->latestVersion();
            $uploadData = $this->resolveUploadData($pod, $data, $priorVersion);

            if ($uploadData['upload_status'] === PodDocumentUploadStatus::FAILED) {
                throw new \DomainException(
                    $uploadData['failure_message'] ?: __('The replacement file could not be stored. The existing draft attachment was not changed.')
                );
            }

            $content = $this->resolveContent($data, $uploadData, $priorVersion);

            if ($existingDraft) {
                $oldFilePath = $existingDraft->file_path;
                // Update existing draft under lock
                $existingDraft->content = $content;
                if (isset($data['change_summary'])) {
                    $existingDraft->change_summary = trim($data['change_summary']);
                }
                $existingDraft->file_path = $uploadData['file_path'];
                $existingDraft->file_name = $uploadData['file_name'];
                $existingDraft->link_url = $uploadData['link_url'];
                $existingDraft->file_size = $uploadData['file_size'];
                $existingDraft->file_mime = $uploadData['file_mime'];
                $existingDraft->upload_status = $uploadData['upload_status'];
                $existingDraft->failure_message = $uploadData['failure_message'];
                $fileChanged = ($uploadData['file_path'] !== $oldFilePath);
                if ($fileChanged) {
                    $existingDraft->extraction_status = !empty($uploadData['file_path']) ? PodDocumentExtractionStatus::PENDING : PodDocumentExtractionStatus::COMPLETED;
                    $existingDraft->extracted_text = !empty($uploadData['file_path']) ? null : $content;
                    $existingDraft->extracted_at = !empty($uploadData['file_path']) ? null : now();
                    $existingDraft->extraction_failure_message = null;
                    $existingDraft->extraction_token = null;
                } elseif (!$existingDraft->isFile()) {
                    $existingDraft->extracted_text = $content;
                    $existingDraft->extraction_status = PodDocumentExtractionStatus::COMPLETED;
                    $existingDraft->extraction_token = null;
                }
                $existingDraft->save();
                $version = $existingDraft;

                // Clean up replaced draft file after database transaction commits
                $this->cleanupReplacedDraftFileAfterCommit($this->getPrivateDisk(), $oldFilePath, $uploadData['file_path']);

                if ($fileChanged && $existingDraft->isFile() && $existingDraft->isUploadReady()) {
                    $this->dispatchExtractionAfterCommit($existingDraft);
                }
            } else {
                $nextVersionNumber = (PodDocumentVersion::where('pod_document_id', $lockedDoc->id)->max('version_number') ?? 0) + 1;
                $hasFile = !empty($uploadData['file_path']);
                $version = PodDocumentVersion::create([
                    'pod_document_id' => $lockedDoc->id,
                    'version_number' => $nextVersionNumber,
                    'title' => $lockedDoc->title,
                    'content' => $content,
                    'change_summary' => $data['change_summary'] ?? __('New draft version'),
                    'file_path' => $uploadData['file_path'],
                    'file_name' => $uploadData['file_name'],
                    'link_url' => $uploadData['link_url'],
                    'file_size' => $uploadData['file_size'],
                    'file_mime' => $uploadData['file_mime'],
                    'upload_status' => $uploadData['upload_status'],
                    'failure_message' => $uploadData['failure_message'],
                    'extraction_status' => $hasFile ? PodDocumentExtractionStatus::PENDING : PodDocumentExtractionStatus::COMPLETED,
                    'extracted_text' => $hasFile ? null : $content,
                    'extracted_at' => $hasFile ? null : now(),
                    'status' => PodDocumentVersionStatus::DRAFT,
                    'created_by' => $creator->id,
                ]);

                if ($version->isFile() && $version->isUploadReady()) {
                    $this->dispatchExtractionAfterCommit($version);
                }
            }

            if (!empty($data['publish'])) {
                $this->publishVersion($lockedDoc, $version, $creator);
            }

            return $version->fresh();
        });
    }

    /**
     * Update an existing draft version's content atomically within a transaction.
     * When a draft's file is replaced, the old unreferenced draft file is safely cleaned up after commit.
     */
    public function updateDraftVersion(PodDocument $document, PodDocumentVersion $version, User $user, array $data): PodDocumentVersion
    {
        if ((int) $version->pod_document_id !== (int) $document->id) {
            throw new \InvalidArgumentException('Version does not belong to the given document.');
        }

        if ($version->isImmutable()) {
            throw new CannotModifyImmutableVersionException();
        }

        return DB::transaction(function () use ($document, $version, $user, $data) {
            // Lock order: 1. Parent document, 2. Target version
            $lockedDoc = PodDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $lockedVersion = PodDocumentVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedVersion->pod_document_id !== (int) $lockedDoc->id) {
                throw new \InvalidArgumentException('Version does not belong to the given document.');
            }

            // Recheck that the version is still an un-published draft before saving
            if ($lockedVersion->status !== PodDocumentVersionStatus::DRAFT || $lockedVersion->published_at !== null || $lockedVersion->isImmutable()) {
                throw new CannotModifyImmutableVersionException();
            }

            $pod = $lockedDoc->pod;
            $oldFilePath = $lockedVersion->file_path;
            $uploadData = $this->resolveUploadData($pod, $data, $lockedVersion);

            if ($uploadData['upload_status'] === PodDocumentUploadStatus::FAILED) {
                throw new \DomainException(
                    $uploadData['failure_message'] ?: __('The replacement file could not be stored. The existing draft attachment was not changed.')
                );
            }

            $content = $this->resolveContent($data, $uploadData, $lockedVersion);

            $lockedVersion->content = $content;
            if (isset($data['change_summary'])) {
                $lockedVersion->change_summary = trim($data['change_summary']);
            }
            $lockedVersion->file_path = $uploadData['file_path'];
            $lockedVersion->file_name = $uploadData['file_name'];
            $lockedVersion->link_url = $uploadData['link_url'];
            $lockedVersion->file_size = $uploadData['file_size'];
            $lockedVersion->file_mime = $uploadData['file_mime'];
            $lockedVersion->upload_status = $uploadData['upload_status'];
            $lockedVersion->failure_message = $uploadData['failure_message'];

            $fileChanged = ($uploadData['file_path'] !== $oldFilePath);
            if ($fileChanged) {
                $lockedVersion->extraction_status = !empty($uploadData['file_path']) ? PodDocumentExtractionStatus::PENDING : PodDocumentExtractionStatus::COMPLETED;
                $lockedVersion->extracted_text = !empty($uploadData['file_path']) ? null : $content;
                $lockedVersion->extracted_at = !empty($uploadData['file_path']) ? null : now();
                $lockedVersion->extraction_failure_message = null;
                $lockedVersion->extraction_token = null;
            } elseif (!$lockedVersion->isFile()) {
                $lockedVersion->extracted_text = $content;
                $lockedVersion->extraction_status = PodDocumentExtractionStatus::COMPLETED;
                $lockedVersion->extraction_token = null;
            }
            $lockedVersion->save();

            // Clean up replaced draft file after database transaction commits
            $this->cleanupReplacedDraftFileAfterCommit($this->getPrivateDisk(), $oldFilePath, $uploadData['file_path']);

            if ($fileChanged && $lockedVersion->isFile() && $lockedVersion->isUploadReady()) {
                $this->dispatchExtractionAfterCommit($lockedVersion);
            }

            if (!empty($data['publish'])) {
                $this->publishVersion($lockedDoc, $lockedVersion, $user);
            }

            return $lockedVersion->fresh();
        });
    }

    /**
     * Publish a draft version of a document.
     *
     * Rules:
     *  - Only draft versions belonging to the document may be published.
     *  - Incomplete or failed uploads cannot be published.
     *  - Attempts to publish already-published or archived versions are rejected without changing them.
     *  - Publication is serialized via a row lock on the parent document.
     *  - Any previously published version(s) transition to ARCHIVED with archived_at = now().
     *  - History, content, files, and original timestamps are fully preserved.
     */
    public function publishVersion(PodDocument $document, PodDocumentVersion $version, User $actor): PodDocumentVersion
    {
        if ((int) $version->pod_document_id !== (int) $document->id) {
            throw new \InvalidArgumentException('Version does not belong to the given document.');
        }

        if ($version->status !== PodDocumentVersionStatus::DRAFT || $version->published_at !== null || $version->isImmutable()) {
            throw new CannotPublishNonDraftVersionException();
        }

        if ($version->upload_status !== PodDocumentUploadStatus::READY) {
            throw new CannotPublishIncompleteUploadException();
        }

        return DB::transaction(function () use ($document, $version, $actor) {
            // Serialize publication for this document by locking the parent document row
            $lockedDoc = PodDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            // Re-fetch and lock the target version to guard against race conditions
            $lockedVersion = PodDocumentVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedVersion->pod_document_id !== (int) $lockedDoc->id) {
                throw new \InvalidArgumentException('Version does not belong to the given document.');
            }

            if ($lockedVersion->status !== PodDocumentVersionStatus::DRAFT || $lockedVersion->published_at !== null || $lockedVersion->isImmutable()) {
                throw new CannotPublishNonDraftVersionException();
            }

            if ($lockedVersion->upload_status !== PodDocumentUploadStatus::READY) {
                throw new CannotPublishIncompleteUploadException();
            }

            // Archive all currently published versions for this document
            PodDocumentVersion::query()
                ->where('pod_document_id', $lockedDoc->id)
                ->where('status', PodDocumentVersionStatus::PUBLISHED->value)
                ->where('id', '!=', $lockedVersion->id)
                ->lockForUpdate()
                ->update([
                    'status' => PodDocumentVersionStatus::ARCHIVED->value,
                    'archived_at' => now(),
                    'updated_at' => now(),
                ]);

            // Mark this version as published
            $lockedVersion->status = PodDocumentVersionStatus::PUBLISHED;
            $lockedVersion->published_at = now();
            $lockedVersion->save();

            // Mark document as published
            $lockedDoc->status = PodDocumentStatus::PUBLISHED;
            $lockedDoc->updated_by = $actor->id;
            $lockedDoc->save();

            if ($lockedVersion->isFile() && $lockedVersion->extraction_status !== PodDocumentExtractionStatus::COMPLETED) {
                $this->dispatchExtractionAfterCommit($lockedVersion);
            }

            return $lockedVersion->fresh();
        });
    }

    /**
     * Reorder onboarding material documents for a POD.
     * Scoped strictly to the given POD and executed transactionally.
     *
     * @param array<int> $orderedIds
     */
    public function reorderDocuments(Pod $pod, array $orderedIds): void
    {
        DB::transaction(function () use ($pod, $orderedIds) {
            $documentIds = PodDocument::where('pod_id', $pod->id)
                ->whereIn('id', $orderedIds)
                ->pluck('id')
                ->all();

            if (count($documentIds) !== count($orderedIds)) {
                throw new \InvalidArgumentException('One or more document IDs do not belong to this POD.');
            }

            $documents = PodDocument::where('pod_id', $pod->id)
                ->whereIn('id', $orderedIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($orderedIds as $index => $id) {
                if (isset($documents[$id])) {
                    $documents[$id]->position = $index + 1;
                    $documents[$id]->save();
                }
            }
        });
    }

    /**
     * Delete a draft version within a transaction.
     * Refuses to delete any version that has been published or archived.
     * Deletes the unreferenced draft file after the transaction commits.
     */
    public function deleteDraftVersion(PodDocument $document, PodDocumentVersion $version): bool
    {
        if ((int) $version->pod_document_id !== (int) $document->id) {
            throw new \InvalidArgumentException('Version does not belong to the given document.');
        }

        if ($version->isImmutable()) {
            throw new CannotDeletePublishedVersionException();
        }

        return DB::transaction(function () use ($document, $version) {
            // Lock order: 1. Parent document, 2. Target version
            $lockedDoc = PodDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $lockedVersion = PodDocumentVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedVersion->pod_document_id !== (int) $lockedDoc->id) {
                throw new \InvalidArgumentException('Version does not belong to the given document.');
            }

            if ($lockedVersion->isImmutable() || $lockedVersion->status !== PodDocumentVersionStatus::DRAFT || $lockedVersion->published_at !== null) {
                throw new CannotDeletePublishedVersionException();
            }

            if (PodDocumentVersion::where('pod_document_id', $lockedDoc->id)->count() <= 1) {
                throw new \DomainException('Cannot delete the only version of a document. Archive or delete the document instead.');
            }

            $oldFilePath = $lockedVersion->file_path;
            $deleted = (bool) $lockedVersion->delete();

            if ($oldFilePath) {
                $this->cleanupReplacedDraftFileAfterCommit($this->getPrivateDisk(), $oldFilePath, null);
            }

            return $deleted;
        });
    }

    /**
     * Archive a document and mark its active published version(s) as archived in one transaction.
     */
    public function archiveDocument(PodDocument $document, User $actor): PodDocument
    {
        return DB::transaction(function () use ($document, $actor) {
            $lockedDoc = PodDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            // Transition active published version(s) to ARCHIVED with archived_at set
            PodDocumentVersion::query()
                ->where('pod_document_id', $lockedDoc->id)
                ->where('status', PodDocumentVersionStatus::PUBLISHED->value)
                ->lockForUpdate()
                ->update([
                    'status' => PodDocumentVersionStatus::ARCHIVED->value,
                    'archived_at' => now(),
                    'updated_at' => now(),
                ]);

            $lockedDoc->status = PodDocumentStatus::ARCHIVED;
            $lockedDoc->updated_by = $actor->id;
            $lockedDoc->save();

            return $lockedDoc->fresh(['versions']);
        });
    }

    /**
     * Delete an un-published draft document.
     * If the document has any published or archived version history, deletion is refused.
     * Cleans up unreferenced draft files after commit.
     */
    public function deleteDocument(PodDocument $document): bool
    {
        return DB::transaction(function () use ($document) {
            $hasPublishedHistory = $document->versions()
                ->where(function ($query) {
                    $query->where('status', '!=', PodDocumentVersionStatus::DRAFT->value)
                        ->orWhereNotNull('published_at');
                })
                ->exists();

            if ($hasPublishedHistory || $document->isPublished() || $document->isArchived()) {
                throw new CannotDeleteDocumentWithHistoryException();
            }

            $filePaths = [];
            foreach ($document->versions as $v) {
                if ($v->file_path) {
                    $filePaths[] = $v->file_path;
                }
            }

            $deleted = (bool) $document->delete();

            $disk = $this->getPrivateDisk();
            foreach ($filePaths as $filePath) {
                $this->cleanupReplacedDraftFileAfterCommit($disk, $filePath, null);
            }

            return $deleted;
        });
    }

    /**
     * Screening Consumer Interface (UNA-020, UNA-021):
     *
     * Returns ONLY the currently active published version of a published document owned by the POD.
     * Draft or archived versions, incomplete/failed uploads, and archived documents are NEVER returned.
     * Results are deterministically ordered by version_number desc, published_at desc, id desc.
     */
    public function getPublishedVersionForScreening(Pod $pod, int $documentId): ?PodDocumentVersion
    {
        return PodDocumentVersion::query()
            ->where('pod_document_id', $documentId)
            ->where('status', PodDocumentVersionStatus::PUBLISHED->value)
            ->where('upload_status', PodDocumentUploadStatus::READY->value)
            ->whereHas('document', function ($query) use ($pod) {
                $query->where('pod_id', $pod->id)
                    ->where('status', PodDocumentStatus::PUBLISHED->value);
            })
            ->orderByDesc('version_number')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Screening / Reader Consumer Interface (UNA-020, UNA-021):
     *
     * Returns all published documents for a POD with exactly one active published version
     * loaded per document (using deterministic ordering: version_number desc, published_at desc, id desc),
     * ensuring callers receive only one published version even if legacy or invalid data has
     * multiple versions marked published.
     * Documents with incomplete or failed uploads are excluded.
     * Optionally filters by document type (e.g. SCREENING, ONBOARDING).
     */
    public function getPublishedDocuments(Pod $pod, ?PodDocumentType $type = null): Collection
    {
        $query = PodDocument::query()
            ->where('pod_id', $pod->id)
            ->where('status', PodDocumentStatus::PUBLISHED->value)
            ->ordered()
            ->with(['versions' => function ($q) {
                $q->where('status', PodDocumentVersionStatus::PUBLISHED->value)
                    ->where('upload_status', PodDocumentUploadStatus::READY->value)
                    ->orderByDesc('version_number')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id');
            }, 'creator']);

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        $documents = $query->get();

        return $documents->filter(function ($doc) {
            $singleVersion = $doc->versions->first();
            if (!$singleVersion) {
                return false;
            }
            $doc->setRelation('versions', $doc->newCollection([$singleVersion]));
            return true;
        })->values();
    }

    /**
     * Searchable text for screening consumers (UNA-022).
     *
     * Returns extracted text exclusively from the active published version of a published document.
     * Draft or archived versions, incomplete uploads, or unpublished documents return null.
     */
    public function getSearchableTextForScreening(Pod $pod, int $documentId): ?string
    {
        $version = $this->getPublishedVersionForScreening($pod, $documentId);
        if (!$version) {
            return null;
        }

        return $version->searchableText();
    }

    /**
     * Search published documents for a POD by keyword (UNA-022).
     *
     * Searches title, description, and searchable text strictly on the active published version
     * of each published document. Never matches or returns draft or archived version content.
     */
    public function searchPublishedDocuments(Pod $pod, string $query, ?PodDocumentType $type = null): Collection
    {
        $documents = $this->getPublishedDocuments($pod, $type);
        $needle = mb_strtolower(trim($query));

        if ($needle === '') {
            return $documents;
        }

        return $documents->filter(function (PodDocument $doc) use ($needle) {
            $version = $doc->versions->first();
            if (!$version) {
                return false;
            }

            $searchable = mb_strtolower($version->searchableText());
            $title = mb_strtolower($doc->title);
            $desc = mb_strtolower($doc->description ?? '');

            return str_contains($title, $needle)
                || str_contains($desc, $needle)
                || str_contains($searchable, $needle);
        })->values();
    }

    /**
     * Retry text extraction for a specific document version (UNA-022).
     * Resets extraction status to pending, clears failure messages, and dispatches job after commit.
     * Does NOT alter or overwrite any other version.
     */
    public function retryExtraction(PodDocumentVersion $version, ?User $actor = null): PodDocumentVersion
    {
        return DB::transaction(function () use ($version) {
            $lockedVersion = PodDocumentVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();

            $lockedVersion->extraction_status = PodDocumentExtractionStatus::PENDING;
            $lockedVersion->extraction_failure_message = null;
            $lockedVersion->extraction_token = null;
            $lockedVersion->save();

            $this->dispatchExtractionAfterCommit($lockedVersion);

            return $lockedVersion->fresh();
        });
    }

    /**
     * Backfill text extraction for existing published POD document versions (UNA-022).
     *
     * Rules:
     *  - Batched via chunkById (default 100).
     *  - Handles published versions without an uploaded file directly: copies text content to
     *    extracted_text, sets extracted_at, and marks extraction COMPLETED.
     *  - For link-only versions with no text content, marks extraction COMPLETED with empty extracted text.
     *  - Dispatches asynchronous ExtractPodDocumentVersionText jobs for file-backed versions.
     *  - Excludes archived documents, archived versions, draft documents/versions, failed uploads,
     *    and unpublished (draft) PODs.
     *  - Idempotent: excludes already completed versions unless $force is true.
     *  - Scoped to exact versions; preserves publication status and historical content.
     *  - Can optionally be restricted to a single POD.
     *
     * @param int $chunkSize Number of records per chunk.
     * @param int|null $podId Optional POD ID filter.
     * @param bool $force Re-extract even if already completed.
     * @return array{queued: int, completed: int, total: int} Number of document versions processed.
     */
    public function backfillPublishedDocumentsText(int $chunkSize = 100, ?int $podId = null, bool $force = false): array
    {
        $chunkSize = max(1, $chunkSize);

        $baseQuery = PodDocumentVersion::query()
            ->whereHas('document', function ($q) use ($podId) {
                $q->where('status', PodDocumentStatus::PUBLISHED->value)
                    ->whereNull('archived_at')
                    ->whereHas('pod', function ($pq) {
                        $pq->where('status', PodStatus::PUBLISHED->value);
                    });
                if ($podId !== null) {
                    $q->where('pod_id', $podId);
                }
            })
            ->where('status', PodDocumentVersionStatus::PUBLISHED->value)
            ->whereNull('archived_at')
            ->where('upload_status', PodDocumentUploadStatus::READY->value);

        $completedCount = 0;
        $queuedCount = 0;

        // 1. Process non-file versions (text content and link materials) synchronously without file extraction
        $nonFileQuery = (clone $baseQuery)
            ->where(function ($q) {
                $q->whereNull('file_path')
                    ->orWhere('file_path', '');
            });

        if (!$force) {
            $nonFileQuery->where(function ($q) {
                $q->whereNull('extraction_status')
                    ->orWhere('extraction_status', '!=', PodDocumentExtractionStatus::COMPLETED->value);
            });
        }

        $nonFileQuery->chunkById($chunkSize, function ($versions) use (&$completedCount) {
            foreach ($versions as $version) {
                $content = (string) ($version->content ?? '');
                $version->extracted_text = trim($content) !== '' ? $content : '';
                $version->extraction_status = PodDocumentExtractionStatus::COMPLETED;
                $version->extracted_at = now();
                $version->extraction_token = null;
                $version->extraction_failure_message = null;
                $version->save();

                $completedCount++;
            }
        });

        // 2. Process file-backed versions asynchronously via queue
        $fileQuery = (clone $baseQuery)
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '');

        if (!$force) {
            $fileQuery->where(function ($q) {
                $q->whereNull('extraction_status')
                    ->orWhere('extraction_status', '!=', PodDocumentExtractionStatus::COMPLETED->value);
            });
        }

        $fileQuery->chunkById($chunkSize, function ($versions) use (&$queuedCount, $force) {
            foreach ($versions as $version) {
                $version->extraction_status = PodDocumentExtractionStatus::PENDING;
                $version->extraction_token = null;
                $version->save();

                ExtractPodDocumentVersionText::dispatch($version->id, $force);
                $queuedCount++;
            }
        });

        return [
            'queued' => $queuedCount,
            'completed' => $completedCount,
            'total' => $queuedCount + $completedCount,
        ];
    }

    /**
     * Schedule asynchronous text extraction for a document version after commit (UNA-022).
     */
    public function dispatchExtractionAfterCommit(PodDocumentVersion $version): void
    {
        if (!config('pod_materials.extraction.enabled', true)) {
            return;
        }

        $versionId = $version->id;
        DB::afterCommit(function () use ($versionId) {
            ExtractPodDocumentVersionText::dispatch($versionId);
        });
    }

    /**
     * Get Outer Circle learning progress and breakdown for a user in a POD (UNA-023).
     */
    public function getLearningProgress(User $user, Pod $pod): array
    {
        return app(PodLearningProgressService::class)->breakdown($user, $pod);
    }

    /**
     * Mark an active published material as completed by an applicant (UNA-023).
     */
    public function completeMaterial(User $user, Pod $pod, PodDocument $document, int|PodDocumentVersion|null $version = null): \App\Models\PodDocumentCompletion
    {
        $targetVersion = $version ?? $document->publishedVersion();
        if (!$targetVersion) {
            throw new \DomainException(__('The published material does not have a ready version to complete.'));
        }

        return app(PodLearningProgressService::class)->markCompleted($user, $pod, $document, $targetVersion);
    }

    /**
     * Resolve upload metadata from request data or existing version.
     */
    private function resolveUploadData(Pod $pod, array $data, ?PodDocumentVersion $existingVersion = null): array
    {
        // 1. If an UploadedFile is explicitly provided
        if (isset($data['file']) && $data['file'] instanceof UploadedFile) {
            $stored = $this->storeUploadedFile($data['file'], $pod);
            return [
                'file_path' => $stored['file_path'],
                'file_name' => $stored['file_name'],
                'file_size' => $stored['file_size'],
                'file_mime' => $stored['file_mime'],
                'link_url' => $data['link_url'] ?? null,
                'upload_status' => $stored['upload_status'],
                'failure_message' => $stored['failure_message'],
            ];
        }

        // 2. If explicit file attributes or upload status passed (e.g. in tests/fixtures)
        if (array_key_exists('upload_status', $data) || array_key_exists('file_path', $data) || array_key_exists('failure_message', $data)) {
            $status = $data['upload_status'] ?? PodDocumentUploadStatus::READY;
            if (is_string($status)) {
                $status = PodDocumentUploadStatus::from($status);
            }

            return [
                'file_path' => $data['file_path'] ?? null,
                'file_name' => $data['file_name'] ?? ($data['file_path'] ? basename($data['file_path']) : null),
                'file_size' => $data['file_size'] ?? null,
                'file_mime' => $data['file_mime'] ?? null,
                'link_url' => $data['link_url'] ?? null,
                'upload_status' => $status,
                'failure_message' => $data['failure_message'] ?? null,
            ];
        }

        // 3. If a new link is provided
        if (isset($data['link_url']) && trim($data['link_url']) !== '') {
            return [
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'file_mime' => null,
                'link_url' => trim($data['link_url']),
                'upload_status' => PodDocumentUploadStatus::READY,
                'failure_message' => null,
            ];
        }

        // 4. If existing version is present and no new file or link is provided, retain existing file/link
        if ($existingVersion) {
            return [
                'file_path' => $existingVersion->file_path,
                'file_name' => $existingVersion->file_name,
                'file_size' => $existingVersion->file_size,
                'file_mime' => $existingVersion->file_mime,
                'link_url' => $existingVersion->link_url,
                'upload_status' => $existingVersion->upload_status ?? PodDocumentUploadStatus::READY,
                'failure_message' => $existingVersion->failure_message,
            ];
        }

        // 5. Default: text-only or empty material
        return [
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
            'file_mime' => null,
            'link_url' => null,
            'upload_status' => PodDocumentUploadStatus::READY,
            'failure_message' => null,
        ];
    }

    /**
     * Resolve document version text content from data or fallback.
     */
    private function resolveContent(array $data, array $uploadData, ?PodDocumentVersion $existingVersion = null): string
    {
        if (isset($data['content']) && trim($data['content']) !== '') {
            return trim($data['content']);
        }

        if (!empty($uploadData['file_name'])) {
            return __('Uploaded file: :name', ['name' => $uploadData['file_name']]);
        }

        if (!empty($uploadData['link_url'])) {
            return __('Resource link: :url', ['url' => $uploadData['link_url']]);
        }

        if ($existingVersion && !empty($existingVersion->content)) {
            return $existingVersion->content;
        }

        return '';
    }
}
