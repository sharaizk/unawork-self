<?php

namespace App\Contracts;

/**
 * Contract for document text extraction AI providers (UNA-022).
 *
 * Implementations wrap provider-specific AI APIs (e.g. Gemini, OpenAI) to extract
 * searchable plain text from uploaded files (PDF, DOCX, DOC, TXT).
 */
interface DocumentTextExtractionProvider
{
    /**
     * Unique key identifying this provider (e.g. "gemini", "openai").
     */
    public function key(): string;

    /**
     * Configured model identifier (e.g. "gemini-3.8-flash").
     */
    public function model(): string;

    /**
     * Extract searchable plain text from raw file binary content and metadata.
     *
     * @param string $binary Raw binary content of the file from private storage.
     * @param string $mime MIME type of the file (e.g. "application/pdf").
     * @param string $extension File extension without dot (e.g. "pdf", "docx", "doc", "txt").
     * @return string Extracted plain text.
     *
     * @throws \App\Exceptions\PodDocument\DocumentTextExtractionException On timeout, rate limit, API error, or unsupported format.
     */
    public function extractText(string $binary, string $mime, string $extension): string;

    /**
     * Determine whether this provider supports the given MIME type and extension.
     */
    public function supports(string $mime, string $extension): bool;
}
