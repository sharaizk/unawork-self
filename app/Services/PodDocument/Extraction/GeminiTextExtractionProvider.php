<?php

namespace App\Services\PodDocument\Extraction;

use App\Contracts\DocumentTextExtractionProvider;
use App\Exceptions\PodDocument\DocumentTextExtractionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini AI provider adapter for document text extraction (UNA-022).
 *
 * Uses the Google Generative Language API (Gemini models) to extract plain text
 * from PDF, Word (DOCX, DOC), and text files via inline base64 data.
 */
class GeminiTextExtractionProvider implements DocumentTextExtractionProvider
{
    public const KEY = 'gemini';

    /**
     * Documented Google Generative Language API error status codes.
     */
    public const DOCUMENTED_ERROR_STATUSES = [
        'OK',
        'CANCELLED',
        'UNKNOWN',
        'INVALID_ARGUMENT',
        'DEADLINE_EXCEEDED',
        'NOT_FOUND',
        'ALREADY_EXISTS',
        'PERMISSION_DENIED',
        'UNAUTHENTICATED',
        'RESOURCE_EXHAUSTED',
        'FAILED_PRECONDITION',
        'ABORTED',
        'OUT_OF_RANGE',
        'UNIMPLEMENTED',
        'INTERNAL',
        'UNAVAILABLE',
        'DATA_LOSS',
        'API_KEY_INVALID',
    ];

    /**
     * Documented Google Generative Language API block reasons.
     */
    public const DOCUMENTED_BLOCK_REASONS = [
        'BLOCK_REASON_UNSPECIFIED',
        'SAFETY',
        'OTHER',
        'BLOCKLIST',
        'PROHIBITED_CONTENT',
    ];

    /**
     * Documented Google Generative Language API finish reasons.
     */
    public const DOCUMENTED_FINISH_REASONS = [
        'FINISH_REASON_UNSPECIFIED',
        'STOP',
        'MAX_TOKENS',
        'SAFETY',
        'RECITATION',
        'LANGUAGE',
        'OTHER',
        'BLOCKLIST',
        'PROHIBITED_CONTENT',
        'SPII',
        'MALFORMED_FUNCTION_CALL',
    ];

    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $timeout;
    private float $temperature;
    private int $maxOutputTokens;
    private string $prompt;

    public function __construct(array $config = [])
    {
        $this->apiKey = (string) ($config['api_key'] ?? config('pod_materials.extraction.providers.gemini.api_key', ''));
        $this->model = (string) ($config['model'] ?? config('pod_materials.extraction.providers.gemini.model', 'gemini-3.8-flash'));
        $this->baseUrl = (string) ($config['base_url'] ?? config('pod_materials.extraction.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'));
        $this->timeout = max(5, (int) ($config['timeout'] ?? config('pod_materials.extraction.providers.gemini.timeout', 60)));
        $this->temperature = (float) ($config['temperature'] ?? config('pod_materials.extraction.providers.gemini.temperature', 0.0));
        $this->maxOutputTokens = max(256, (int) ($config['max_output_tokens'] ?? config('pod_materials.extraction.providers.gemini.max_output_tokens', 8192)));
        $this->prompt = (string) ($config['prompt'] ?? config(
            'pod_materials.extraction.providers.gemini.prompt',
            'Extract all readable text from this document verbatim. Return only the extracted plain text with no commentary, no summary, no conversational filler, and no markdown code fences.'
        ));
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function supports(string $mime, string $extension): bool
    {
        $ext = strtolower(trim($extension));
        $supportedExtensions = ['pdf', 'docx', 'doc', 'txt'];

        if (in_array($ext, $supportedExtensions, true)) {
            return true;
        }

        $supportedMimes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'text/plain',
        ];

        return in_array(strtolower(trim($mime)), $supportedMimes, true);
    }

    public function extractText(string $binary, string $mime, string $extension): string
    {
        if (empty($this->apiKey)) {
            Log::error("Text extraction failed for provider [{$this->key()}]: API key is not configured.");
            throw new DocumentTextExtractionException(__('AI document text extraction is not configured. Please contact the administrator.'));
        }

        $ext = strtolower(trim($extension));
        $resolvedMime = $this->resolveMime($ext, $mime);

        if (!$this->supports($resolvedMime, $ext)) {
            $safeExt = preg_match('/^[a-zA-Z0-9]{1,10}$/', $ext) ? strtolower($ext) : 'unsupported';
            Log::warning("Text extraction rejected unsupported format for provider [{$this->key()}]: [{$safeExt}].");
            throw new DocumentTextExtractionException(
                __('The document format (:extension) is not supported by the AI provider.', ['extension' => strtoupper($ext ?: 'UNKNOWN')])
            );
        }

        if (strlen(trim($binary)) === 0) {
            throw new DocumentTextExtractionException(__('The document file is empty.'));
        }

        $url = rtrim($this->baseUrl, '/') . "/models/{$this->model}:generateContent";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $this->prompt,
                        ],
                        [
                            'inlineData' => [
                                'mimeType' => $resolvedMime,
                                'data' => base64_encode($binary),
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => $this->maxOutputTokens,
            ],
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ])
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::error("Text extraction connection timeout after {$this->timeout}s for provider [{$this->key()}] with model [{$this->model}].");
            throw new DocumentTextExtractionException(__('The AI provider connection timed out. Please try again.'), 408, 'TIMEOUT', $e);
        } catch (\Throwable $e) {
            $exceptionClass = get_class($e);
            Log::error("Text extraction network error for provider [{$this->key()}] with model [{$this->model}]: [{$exceptionClass}].");
            throw new DocumentTextExtractionException(__('Failed to connect to the AI text extraction service.'), null, null, $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            $data = $response->json() ?? [];
            $errorObj = $data['error'] ?? [];
            $rawErrorMessage = (string) ($errorObj['message'] ?? '');
            $rawErrorStatus = (string) ($errorObj['status'] ?? '');
            $safeErrorStatus = $this->sanitizeErrorStatus($rawErrorStatus);

            // Derive controlled, stable error category without leaking raw response messages
            $category = match (true) {
                $status === 400 && (str_contains(strtolower($rawErrorMessage), 'key') || $rawErrorStatus === 'API_KEY_INVALID' || $safeErrorStatus === 'API_KEY_INVALID') => 'INVALID_API_KEY',
                $status === 400 && (str_contains(strtolower($rawErrorMessage), 'mime') || str_contains(strtolower($rawErrorMessage), 'unsupported')) => 'UNSUPPORTED_FORMAT',
                $status === 400 => 'BAD_REQUEST',
                $status === 401 || $status === 403 => 'UNAUTHENTICATED',
                $status === 429 => 'RATE_LIMIT_EXCEEDED',
                $status >= 500 => 'SERVICE_UNAVAILABLE',
                default => 'PROVIDER_ERROR',
            };

            // Log strictly controlled, non-sensitive fields (no raw error message, body, or credentials)
            Log::error("Text extraction failed for provider [{$this->key()}] with model [{$this->model}]: HTTP {$status} [{$safeErrorStatus}] [category: {$category}].");

            if ($status === 400) {
                if ($category === 'INVALID_API_KEY') {
                    throw new DocumentTextExtractionException(
                        __('AI document text extraction is misconfigured. Please check provider credentials.'),
                        $status,
                        $safeErrorStatus
                    );
                }
                if ($category === 'UNSUPPORTED_FORMAT') {
                    throw new DocumentTextExtractionException(
                        __('The uploaded document format is not supported by the AI provider.'),
                        $status,
                        $safeErrorStatus
                    );
                }
                throw new DocumentTextExtractionException(
                    __('The AI text extraction request was rejected as invalid.'),
                    $status,
                    $safeErrorStatus
                );
            }

            if ($status === 401 || $status === 403) {
                throw new DocumentTextExtractionException(
                    __('AI document text extraction authentication failed. Please verify API credentials.'),
                    $status,
                    $safeErrorStatus
                );
            }

            if ($status === 429) {
                throw new DocumentTextExtractionException(
                    __('AI provider rate limit was exceeded. Please try again shortly.'),
                    $status,
                    $safeErrorStatus
                );
            }

            if ($status >= 500) {
                throw new DocumentTextExtractionException(
                    __('The AI text extraction service is temporarily unavailable. Please try again later.'),
                    $status,
                    $safeErrorStatus
                );
            }

            throw new DocumentTextExtractionException(
                __('AI text extraction failed with HTTP status :status.', ['status' => $status]),
                $status,
                $safeErrorStatus
            );
        }

        $data = $response->json();
        if (!is_array($data)) {
            Log::error("Text extraction malformed response for provider [{$this->key()}] with model [{$this->model}]: non-JSON response.");
            throw new DocumentTextExtractionException(__('The AI provider returned an unexpected response format.'));
        }

        $candidates = $data['candidates'] ?? [];
        if (empty($candidates)) {
            $rawBlockReason = $data['promptFeedback']['blockReason'] ?? null;
            if ($rawBlockReason !== null) {
                $safeBlockReason = $this->sanitizeBlockReason((string) $rawBlockReason);
                Log::warning("Text extraction content blocked by safety filters for provider [{$this->key()}] with model [{$this->model}]: [{$safeBlockReason}].");
                throw new DocumentTextExtractionException(__('The document content was blocked by AI provider safety filters.'));
            }

            Log::warning("Text extraction produced no candidates for provider [{$this->key()}] with model [{$this->model}].");
            throw new DocumentTextExtractionException(__('No readable text could be extracted from this document.'));
        }

        $candidate = $candidates[0];
        $parts = $candidate['content']['parts'] ?? [];
        $extractedText = '';
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $extractedText .= $part['text'];
            }
        }

        $extractedText = trim($extractedText);
        if ($extractedText === '') {
            $rawFinishReason = $candidate['finishReason'] ?? null;
            $safeFinishReason = $this->sanitizeFinishReason((string) $rawFinishReason);
            Log::warning("Text extraction produced empty text for provider [{$this->key()}] with model [{$this->model}] [finishReason: {$safeFinishReason}].");
            throw new DocumentTextExtractionException(__('No readable text could be extracted from this document.'));
        }

        return $extractedText;
    }

    /**
     * Sanitize provider error status against documented Google API error codes.
     */
    public function sanitizeErrorStatus(?string $status): string
    {
        $status = strtoupper(trim((string) $status));

        return in_array($status, self::DOCUMENTED_ERROR_STATUSES, true) ? $status : 'OTHER';
    }

    /**
     * Sanitize safety block reason against documented Google API block reasons.
     */
    public function sanitizeBlockReason(?string $reason): string
    {
        $reason = strtoupper(trim((string) $reason));

        return in_array($reason, self::DOCUMENTED_BLOCK_REASONS, true) ? $reason : 'OTHER';
    }

    /**
     * Sanitize candidate finish reason against documented Google API finish reasons.
     */
    public function sanitizeFinishReason(?string $reason): string
    {
        $reason = strtoupper(trim((string) $reason));

        return in_array($reason, self::DOCUMENTED_FINISH_REASONS, true) ? $reason : 'UNKNOWN';
    }

    private function resolveMime(string $extension, ?string $mime = null): string
    {
        $mime = strtolower(trim((string) $mime));
        if ($mime !== '' && $mime !== 'application/octet-stream') {
            return $mime;
        }

        return match ($extension) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
