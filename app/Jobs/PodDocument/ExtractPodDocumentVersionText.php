<?php

namespace App\Jobs\PodDocument;

use App\Enums\PodDocument\PodDocumentExtractionStatus;
use App\Models\PodDocumentVersion;
use App\Services\PodDocument\PodDocumentService;
use App\Services\PodDocument\PodDocumentTextExtractorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Asynchronous job to extract searchable text from a POD document version (UNA-022).
 *
 * Rules:
 *  - Dispatched only after commit, passing scalar version ID.
 *  - Reloads version row and handles missing records, missing files, archived versions,
 *    and duplicate delivery safely.
 *  - Isolated to the target version: never modifies or overwrites any newer or other version.
 *  - Records processing metadata: status (pending, processing, completed, failed),
 *    extracted_at timestamp, and user-readable failure message.
 */
class ExtractPodDocumentVersionText implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries;
    public int $timeout;
    public array $backoff;

    public function __construct(
        public readonly int $versionId,
        public readonly bool $force = false
    ) {
        $this->onQueue((string) config('pod_materials.extraction.queue', 'default'));
        $this->tries = max(1, (int) config('pod_materials.extraction.tries', 3));
        $this->timeout = max(1, (int) config('pod_materials.extraction.timeout', 120));
        $this->backoff = array_map('intval', (array) config('pod_materials.extraction.backoff', [15, 60, 180]));
    }

    public function uniqueId(): string
    {
        return "pod_document_version:extract_text:{$this->versionId}";
    }

    /**
     * Execute text extraction job.
     *
     * @return string Description of outcome (for testing and logging)
     */
    public function handle(PodDocumentTextExtractorService $extractor, PodDocumentService $documentService): string
    {
        $version = PodDocumentVersion::query()->with('document')->find($this->versionId);

        if (!$version) {
            return 'skipped_missing_version';
        }

        if (!$version->document) {
            return 'skipped_missing_document';
        }

        // Duplicate delivery check: if already completed and not force retry, exit cleanly
        if ($version->extraction_status === PodDocumentExtractionStatus::COMPLETED && !$this->force) {
            return 'already_completed';
        }

        // Transition to PROCESSING under lock and capture stable change token and file path
        $state = DB::transaction(function () {
            $locked = PodDocumentVersion::whereKey($this->versionId)->lockForUpdate()->first();
            if (!$locked) {
                return null;
            }

            if ($locked->extraction_status === PodDocumentExtractionStatus::COMPLETED && !$this->force) {
                return false;
            }

            $token = bin2hex(random_bytes(16));
            $locked->extraction_status = PodDocumentExtractionStatus::PROCESSING;
            $locked->extraction_token = $token;
            $locked->save();

            return [
                'version' => $locked,
                'filePath' => $locked->file_path,
                'token' => $token,
                'mime' => $locked->file_mime,
                'extension' => $locked->fileExtension(),
            ];
        });

        if ($state === null) {
            return 'skipped_missing_version';
        }

        if ($state === false) {
            return 'already_completed';
        }

        $lockedVersion = $state['version'];
        $capturedFilePath = $state['filePath'];
        $capturedToken = $state['token'];
        $capturedMime = $state['mime'];
        $capturedExt = $state['extension'];

        // If version has no file attachment (e.g. text-only or link), use content directly
        if (!$lockedVersion->isFile() || empty($capturedFilePath)) {
            return DB::transaction(function () use ($capturedToken) {
                $current = PodDocumentVersion::whereKey($this->versionId)->lockForUpdate()->first();
                if (!$current) {
                    return 'skipped_missing';
                }

                if ($current->extraction_token !== $capturedToken || $current->extraction_status !== PodDocumentExtractionStatus::PROCESSING) {
                    return 'stale_discarded';
                }

                $content = (string) ($current->content ?? '');
                $current->extracted_text = trim($content) !== '' ? $content : '';
                $current->extraction_status = PodDocumentExtractionStatus::COMPLETED;
                $current->extracted_at = now();
                $current->extraction_failure_message = null;
                $current->save();

                return 'completed_text_only';
            });
        }

        // Validate private disk
        try {
            $disk = $documentService->getPrivateDisk();
        } catch (\Throwable $e) {
            return DB::transaction(function () use ($capturedFilePath, $capturedToken, $e) {
                $current = PodDocumentVersion::whereKey($this->versionId)->lockForUpdate()->first();
                if (!$current) {
                    return 'skipped_missing';
                }

                if ($current->file_path !== $capturedFilePath || $current->extraction_token !== $capturedToken || $current->extraction_status !== PodDocumentExtractionStatus::PROCESSING) {
                    return 'stale_failure_discarded';
                }

                $current->extraction_status = PodDocumentExtractionStatus::FAILED;
                $current->extraction_failure_message = $e->getMessage();
                $current->save();

                return 'failed_invalid_disk';
            });
        }

        // Check if file exists on private storage
        if (!Storage::disk($disk)->exists($capturedFilePath)) {
            return DB::transaction(function () use ($capturedFilePath, $capturedToken) {
                $current = PodDocumentVersion::whereKey($this->versionId)->lockForUpdate()->first();
                if (!$current) {
                    return 'skipped_missing';
                }

                if ($current->file_path !== $capturedFilePath || $current->extraction_token !== $capturedToken || $current->extraction_status !== PodDocumentExtractionStatus::PROCESSING) {
                    return 'stale_failure_discarded';
                }

                $current->extraction_status = PodDocumentExtractionStatus::FAILED;
                $current->extraction_failure_message = __('The document file was not found on private storage.');
                $current->save();

                return 'failed_missing_file';
            });
        }

        // Perform text extraction
        try {
            $text = $extractor->extract(
                $capturedFilePath,
                $capturedMime,
                $capturedExt,
                $disk
            );

            // Reacquire row lock before saving extracted text and confirm state has not changed
            return DB::transaction(function () use ($capturedFilePath, $capturedToken, $text) {
                $current = PodDocumentVersion::whereKey($this->versionId)->lockForUpdate()->first();
                if (!$current) {
                    return 'skipped_missing';
                }

                // Guard: confirm file path is identical AND token matches AND status is still PROCESSING
                if ($current->file_path !== $capturedFilePath || $current->extraction_token !== $capturedToken || $current->extraction_status !== PodDocumentExtractionStatus::PROCESSING) {
                    return 'stale_discarded';
                }

                $current->extracted_text = $text;
                $current->extraction_status = PodDocumentExtractionStatus::COMPLETED;
                $current->extracted_at = now();
                $current->extraction_failure_message = null;
                $current->save();

                return 'completed';
            });
        } catch (\Throwable $e) {
            // Apply the exact same guard for failure recording
            return DB::transaction(function () use ($capturedFilePath, $capturedToken, $e) {
                $current = PodDocumentVersion::whereKey($this->versionId)->lockForUpdate()->first();
                if (!$current) {
                    return 'skipped_missing';
                }

                if ($current->file_path !== $capturedFilePath || $current->extraction_token !== $capturedToken || $current->extraction_status !== PodDocumentExtractionStatus::PROCESSING) {
                    return 'stale_failure_discarded';
                }

                $current->extraction_status = PodDocumentExtractionStatus::FAILED;
                $current->extraction_failure_message = $e->getMessage() ?: __('Text extraction failed.');
                $current->save();

                return 'failed:' . $e->getMessage();
            });
        }
    }
}
