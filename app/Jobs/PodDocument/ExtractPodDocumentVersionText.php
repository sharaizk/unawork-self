<?php

namespace App\Jobs\PodDocument;

use App\Contracts\DocumentTextExtractionProvider;
use App\Enums\PodDocument\PodDocumentExtractionStatus;
use App\Exceptions\PodDocument\DocumentTextExtractionException;
use App\Models\PodDocumentVersion;
use App\Services\PodDocument\Extraction\DocumentTextExtractionResolver;
use App\Services\PodDocument\PodDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
 *  - Provider resolution failures are safely caught, guarded by change tokens, and marked
 *    as FAILED with user-safe translated feedback rather than crashing in DI container.
 *  - Transient network failures (timeouts, 429 rate limits, 5xx server errors) are retried
 *    via queue retries up to $tries times.
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
     * @param DocumentTextExtractionProvider|DocumentTextExtractionResolver|null $extractor
     * @param PodDocumentService|null $documentService
     * @return string Description of outcome (for testing and logging)
     */
    public function handle(
        DocumentTextExtractionProvider|DocumentTextExtractionResolver|null $extractor = null,
        ?PodDocumentService $documentService = null
    ): string {
        $documentService ??= app(PodDocumentService::class);

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
                $current->extraction_token = null;
                $current->save();

                return 'completed_text_only';
            });
        }

        // Validate private disk
        try {
            $disk = $documentService->getPrivateDisk();
        } catch (\Throwable $e) {
            return $this->recordPermanentFailure(
                $capturedFilePath,
                $capturedToken,
                $e->getMessage(),
                'failed_invalid_disk'
            );
        }

        // Check if file exists on private storage
        if (!Storage::disk($disk)->exists($capturedFilePath)) {
            return $this->recordPermanentFailure(
                $capturedFilePath,
                $capturedToken,
                __('The document file was not found on private storage.'),
                'failed_missing_file'
            );
        }

        // Resolve document text extraction provider safely inside handle()
        try {
            if ($extractor instanceof DocumentTextExtractionProvider) {
                $provider = $extractor;
            } elseif ($extractor instanceof DocumentTextExtractionResolver) {
                $provider = $extractor->resolve();
            } else {
                /** @var DocumentTextExtractionResolver $resolver */
                $resolver = app(DocumentTextExtractionResolver::class);
                $provider = $resolver->resolve();
            }
        } catch (\Throwable $e) {
            $exceptionClass = get_class($e);
            Log::error("Failed to resolve document text extraction provider for version [{$this->versionId}]: [{$exceptionClass}].");

            return $this->recordPermanentFailure(
                $capturedFilePath,
                $capturedToken,
                __('Document text extraction provider is misconfigured or unavailable. Please contact the administrator.'),
                'failed_provider_resolution'
            );
        }

        // Validate format support with resolved provider
        if (!$provider->supports($capturedMime ?: '', $capturedExt ?: '')) {
            return $this->recordPermanentFailure(
                $capturedFilePath,
                $capturedToken,
                __('The document format (:extension) is not supported by the AI provider.', ['extension' => strtoupper($capturedExt ?: 'UNKNOWN')]),
                'failed_unsupported_format'
            );
        }

        // Perform text extraction via configured AI provider
        try {
            $binary = Storage::disk($disk)->get($capturedFilePath);
            if ($binary === null || strlen($binary) === 0) {
                throw new DocumentTextExtractionException(__('The document file was not found or is empty.'));
            }

            $rawText = $provider->extractText(
                $binary,
                $capturedMime ?: '',
                $capturedExt ?: ''
            );

            $text = $this->cleanExtractedText($rawText);
            $text = $this->limitLength($text);

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
                $current->extraction_token = null;
                $current->save();

                return 'completed';
            });
        } catch (\Throwable $e) {
            $userMessage = $e instanceof DocumentTextExtractionException
                ? $e->getMessage()
                : __('Text extraction failed.');

            // Check for transient failure when running inside a queue worker
            if ($this->job && $this->isTransientFailure($e)) {
                if ($this->attempts() < $this->tries) {
                    $exceptionClass = get_class($e);
                    $category = match (true) {
                        $e instanceof ConnectionException => 'TIMEOUT',
                        $e instanceof DocumentTextExtractionException && $e->httpStatus === 429 => 'RATE_LIMIT',
                        $e instanceof DocumentTextExtractionException && $e->httpStatus >= 500 => 'SERVER_ERROR',
                        $e instanceof DocumentTextExtractionException && $e->httpStatus === 408 => 'TIMEOUT',
                        default => 'TRANSIENT_NETWORK_ERROR',
                    };
                    $httpStatus = ($e instanceof DocumentTextExtractionException && $e->httpStatus !== null)
                        ? " HTTP {$e->httpStatus}"
                        : '';

                    Log::warning("Transient text extraction failure on attempt {$this->attempts()} of {$this->tries} for version [{$this->versionId}]: [{$exceptionClass}]{$httpStatus} [category: {$category}].");
                    throw $e;
                }

                // Retries exhausted on queue
                $this->recordPermanentFailure(
                    $capturedFilePath,
                    $capturedToken,
                    $userMessage,
                    'failed_extraction'
                );

                throw $e;
            }

            return $this->recordPermanentFailure(
                $capturedFilePath,
                $capturedToken,
                $userMessage,
                'failed_extraction'
            );
        }
    }

    /**
     * Handle queue failure after all retries have been exhausted.
     */
    public function failed(?\Throwable $exception = null): void
    {
        $message = $exception instanceof DocumentTextExtractionException
            ? $exception->getMessage()
            : __('Text extraction failed after multiple attempts.');

        DB::transaction(function () use ($message) {
            $current = PodDocumentVersion::whereKey($this->versionId)->lockForUpdate()->first();
            if (!$current || $current->extraction_status === PodDocumentExtractionStatus::COMPLETED) {
                return;
            }

            $current->extraction_status = PodDocumentExtractionStatus::FAILED;
            $current->extraction_failure_message = $message;
            $current->extraction_token = null;
            $current->save();
        });
    }

    /**
     * Determine whether an extraction failure is transient and eligible for retry.
     */
    protected function isTransientFailure(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof DocumentTextExtractionException) {
            $status = $e->httpStatus;
            if ($status === 408 || $status === 429 || ($status !== null && $status >= 500)) {
                return true;
            }

            if ($e->getPrevious() instanceof ConnectionException) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record a permanent failure under lock with change token verification.
     */
    protected function recordPermanentFailure(
        ?string $capturedFilePath,
        ?string $capturedToken,
        string $message,
        string $outcome
    ): string {
        return DB::transaction(function () use ($capturedFilePath, $capturedToken, $message, $outcome) {
            $current = PodDocumentVersion::whereKey($this->versionId)->lockForUpdate()->first();
            if (!$current) {
                return 'skipped_missing';
            }

            if ($capturedFilePath !== null && $current->file_path !== $capturedFilePath) {
                return 'stale_failure_discarded';
            }

            if ($capturedToken !== null && $current->extraction_token !== $capturedToken) {
                return 'stale_failure_discarded';
            }

            if ($current->extraction_status !== PodDocumentExtractionStatus::PROCESSING) {
                return 'stale_failure_discarded';
            }

            $current->extraction_status = PodDocumentExtractionStatus::FAILED;
            $current->extraction_failure_message = $message;
            $current->extraction_token = null;
            $current->save();

            return $outcome;
        });
    }

    private function cleanExtractedText(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $text);

        return trim($text);
    }

    private function limitLength(string $text): string
    {
        $maxChars = (int) config('pod_materials.extraction.max_extracted_characters', 500000);
        if ($maxChars > 0 && mb_strlen($text) > $maxChars) {
            return mb_substr($text, 0, $maxChars);
        }

        return $text;
    }
}
