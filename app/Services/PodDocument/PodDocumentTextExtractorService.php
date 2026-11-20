<?php

namespace App\Services\PodDocument;

use App\Contracts\DocumentTextExtractionProvider;
use App\Services\PodDocument\Extraction\DocumentTextExtractionResolver;
use Illuminate\Support\Facades\Storage;

/**
 * Service to extract searchable plain text from POD document files (UNA-022).
 *
 * Configured AI providers (e.g. Gemini) are used exclusively for text extraction.
 * Legacy custom PHP parsers for PDF/DOC/DOCX have been retired and are not used as fallback.
 */
class PodDocumentTextExtractorService implements DocumentTextExtractionProvider
{
    private DocumentTextExtractionProvider $provider;

    public function __construct(?DocumentTextExtractionProvider $provider = null)
    {
        $this->provider = $provider ?? app(DocumentTextExtractionResolver::class)->resolve();
    }

    public function key(): string
    {
        return $this->provider->key();
    }

    public function model(): string
    {
        return $this->provider->model();
    }

    public function supports(string $mime, string $extension): bool
    {
        return $this->provider->supports($mime, $extension);
    }

    /**
     * Extract searchable plain text from raw file binary content using the configured AI provider.
     */
    public function extractText(string $binary, string $mime, string $extension): string
    {
        $text = $this->provider->extractText($binary, $mime, $extension);

        return $this->limitLength($this->cleanExtractedText($text));
    }

    /**
     * Extract searchable plain text from a stored file on private storage.
     *
     * @throws \RuntimeException on file reading or missing file errors
     * @throws \App\Exceptions\PodDocument\DocumentTextExtractionException on AI provider errors
     */
    public function extract(string $filePath, ?string $mime = null, ?string $extension = null, string $disk = 'local'): string
    {
        if (!Storage::disk($disk)->exists($filePath)) {
            throw new \RuntimeException(__('The document file was not found on private storage.'));
        }

        $extension = strtolower($extension ?: pathinfo($filePath, PATHINFO_EXTENSION));
        $content = Storage::disk($disk)->get($filePath);

        if ($content === null || strlen($content) === 0) {
            return '';
        }

        $mime = $mime ?: $this->resolveMime($extension);

        return $this->extractText($content, $mime, $extension);
    }

    /**
     * Clean and normalize extracted text:
     * - Strips non-printable ASCII control characters (keeping \t, \n, \r)
     * - Replaces carriage returns with standard newlines
     * - Normalizes spaces and repeated line breaks
     * - Trims leading/trailing whitespace
     */
    public function cleanExtractedText(string $text): string
    {
        // Strip null bytes and non-printable control characters except \t, \n, \r
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Normalize newlines
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Normalize multiple spaces and tabs
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // Normalize excessive consecutive newlines (max 2)
        $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Limit maximum characters stored for indexing according to configuration.
     */
    public function limitLength(string $text): string
    {
        $maxChars = (int) config('pod_materials.extraction.max_extracted_characters', 500000);
        if ($maxChars > 0 && mb_strlen($text) > $maxChars) {
            return mb_substr($text, 0, $maxChars);
        }

        return $text;
    }

    private function resolveMime(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
