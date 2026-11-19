<?php

namespace App\Services\PodDocument;

use Illuminate\Support\Facades\Storage;

/**
 * Service to extract searchable plain text from POD document files (UNA-022).
 *
 * Supports PDF, DOCX, DOC, and TXT formats using pure PHP implementations
 * without requiring external system dependencies, respecting private storage
 * and deployment constraints.
 */
class PodDocumentTextExtractorService
{
    /**
     * Extract searchable plain text from a stored file on private storage.
     *
     * @throws \RuntimeException on file reading or format corruption errors
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

        $text = match ($extension) {
            'docx' => $this->extractDocx($content, $filePath, $disk),
            'pdf' => $this->extractPdf($content),
            'doc' => $this->extractDoc($content),
            'txt' => $this->cleanExtractedText($content),
            default => $this->extractFallback($content, $mime),
        };

        return $this->limitLength($this->cleanExtractedText($text));
    }

    /**
     * Extract text directly from raw binary content (for tests and in-memory streams).
     */
    public function extractFromBinary(string $binary, string $extension): string
    {
        $extension = strtolower($extension);

        $text = match ($extension) {
            'docx' => $this->extractDocxFromBinary($binary),
            'pdf' => $this->extractPdf($binary),
            'doc' => $this->extractDoc($binary),
            'txt' => $this->cleanExtractedText($binary),
            default => $this->cleanExtractedText($binary),
        };

        return $this->limitLength($this->cleanExtractedText($text));
    }

    /**
     * Extract text from a DOCX file using ZipArchive.
     */
    public function extractDocx(string $content, ?string $filePath = null, string $disk = 'local'): string
    {
        // If file exists on local disk, we can pass realpath directly
        $fullPath = null;
        try {
            $fullPath = Storage::disk($disk)->path($filePath);
        } catch (\Throwable) {
            $fullPath = null;
        }

        if ($fullPath && file_exists($fullPath)) {
            return $this->extractDocxFromPath($fullPath);
        }

        return $this->extractDocxFromBinary($content);
    }

    /**
     * Extract text from a DOCX archive via filesystem path.
     */
    private function extractDocxFromPath(string $path): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException(__('ZipArchive extension is not available for DOCX extraction.'));
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($path);

        if ($openResult !== true) {
            throw new \RuntimeException(__('The DOCX file appears to be corrupted or invalid.'));
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException(__('Invalid DOCX structure: word/document.xml not found.'));
        }

        return $this->parseWordXml($xml);
    }

    /**
     * Extract text from DOCX binary content using a temporary file.
     */
    private function extractDocxFromBinary(string $binary): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException(__('ZipArchive extension is not available for DOCX extraction.'));
        }

        $temp = tempnam(sys_get_temp_dir(), 'docx_extract_');
        if (!$temp) {
            throw new \RuntimeException(__('Failed to allocate temporary storage for DOCX extraction.'));
        }

        try {
            file_put_contents($temp, $binary);
            return $this->extractDocxFromPath($temp);
        } finally {
            if (file_exists($temp)) {
                @unlink($temp);
            }
        }
    }

    /**
     * Parse word/document.xml content into plain text.
     */
    private function parseWordXml(string $xml): string
    {
        // Replace paragraph and table row closings with newlines
        $xml = preg_replace('/<\/w:p>/i', "\n", $xml);
        $xml = preg_replace('/<\/w:tr>/i', "\n", $xml);
        $xml = preg_replace('/<w:br[^>]*\/>/i', "\n", $xml);
        $xml = preg_replace('/<w:tab[^>]*\/>/i', "\t", $xml);

        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $text;
    }

    /**
     * Extract text from a PDF binary string using pure PHP stream parsing.
     */
    public function extractPdf(string $content): string
    {
        if (strpos($content, '%PDF-') === false && strpos($content, 'stream') === false) {
            throw new \RuntimeException(__('The file is not a valid PDF document.'));
        }

        $text = '';

        // Extract streams: stream ... endstream
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $streamMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($streamMatches[1] as $idx => $match) {
                $streamData = $match[0];
                $streamOffset = $streamMatches[0][$idx][1];
                $header = substr($content, max(0, $streamOffset - 600), min(600, $streamOffset));

                $decompressed = null;
                if (stripos($header, '/FlateDecode') !== false) {
                    $decompressed = @gzuncompress($streamData);
                    if ($decompressed === false) {
                        $decompressed = @gzinflate($streamData);
                    }
                    if ($decompressed === false && strlen($streamData) > 2) {
                        // Strip 2-byte zlib wrapper
                        $decompressed = @gzinflate(substr($streamData, 2));
                    }
                } else {
                    $decompressed = $streamData;
                }

                if ($decompressed !== false && !empty($decompressed)) {
                    $text .= ' ' . $this->extractPdfTextCommands($decompressed);
                }
            }
        }

        // If no streams were extracted or streams were empty, check for direct text operators
        if (trim($text) === '') {
            $directText = $this->extractPdfTextCommands($content);
            if (trim($directText) !== '') {
                $text = $directText;
            }
        }

        return $text;
    }

    /**
     * Extract text from PDF content stream commands between BT and ET blocks.
     */
    private function extractPdfTextCommands(string $stream): string
    {
        $result = '';

        // Match BT ... ET blocks
        if (preg_match_all('/BT[\r\n\s]+(.*?)[\r\n\s]+ET/s', $stream, $textBlocks)) {
            foreach ($textBlocks[1] as $block) {
                // 1. Array TJ operator: [ (str1) -120 (str2) ] TJ
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $tjMatches)) {
                    foreach ($tjMatches[1] as $tjArray) {
                        if (preg_match_all('/\((.*?)(?<!\\\\)\)/s', $tjArray, $strMatches)) {
                            foreach ($strMatches[1] as $s) {
                                $result .= ' ' . $this->decodePdfString($s);
                            }
                        }
                    }
                }

                // 2. Simple Tj operator: (string) Tj
                if (preg_match_all('/\((.*?)(?<!\\\\)\)\s*Tj/s', $block, $tjSingleMatches)) {
                    foreach ($tjSingleMatches[1] as $s) {
                        $result .= ' ' . $this->decodePdfString($s);
                    }
                }

                // 3. Single / Double quote operators: (string) ' or "
                if (preg_match_all('/\((.*?)(?<!\\\\)\)\s*[\'"]/s', $block, $quoteMatches)) {
                    foreach ($quoteMatches[1] as $s) {
                        $result .= "\n" . $this->decodePdfString($s);
                    }
                }

                // 4. Hex strings: <48656c6c6f> Tj
                if (preg_match_all('/<([0-9a-fA-F]+)>\s*Tj/s', $block, $hexMatches)) {
                    foreach ($hexMatches[1] as $hex) {
                        $decodedHex = @hex2bin($hex);
                        if ($decodedHex !== false) {
                            $result .= ' ' . $decodedHex;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Decode PDF string escapes and octal byte sequences.
     */
    private function decodePdfString(string $str): string
    {
        // Unescape standard PDF sequences
        $str = str_replace(
            ['\\n', '\\r', '\\t', '\\b', '\\f', '\\(', '\\)', '\\\\'],
            ["\n", "\r", "\t", "\x08", "\x0C", '(', ')', '\\'],
            $str
        );

        // Decode octal escapes (\000 - \777)
        $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($matches) {
            return chr(octdec($matches[1]));
        }, $str);

        return $str;
    }

    /**
     * Extract text from legacy Word 97-2003 (.doc) binary format.
     */
    public function extractDoc(string $binary): string
    {
        $text = '';

        // 1. Extract UTF-16LE text sequences (sequences of printable chars followed by \x00)
        if (preg_match_all('/(?:[\x20-\x7E\t\r\n]\x00){3,}/', $binary, $utf16Matches)) {
            foreach ($utf16Matches[0] as $match) {
                $converted = @mb_convert_encoding($match, 'UTF-8', 'UTF-16LE');
                if ($converted !== false && !$this->isDocBoilerplate($converted)) {
                    $text .= ' ' . $converted;
                }
            }
        }

        // 2. Extract ASCII sequences (printable chars of length >= 4)
        if (preg_match_all('/[\x20-\x7E\t\r\n]{4,}/', $binary, $asciiMatches)) {
            foreach ($asciiMatches[0] as $match) {
                if (!$this->isDocBoilerplate($match)) {
                    $text .= ' ' . $match;
                }
            }
        }

        return $text;
    }

    /**
     * Identify and filter binary metadata and template boilerplate in DOC files.
     */
    private function isDocBoilerplate(string $str): bool
    {
        $trimmed = trim($str);
        if (strlen($trimmed) < 2) {
            return true;
        }

        $boilerplate = [
            'Normal.dot',
            'Microsoft Word',
            'Word.Document',
            'Times New Roman',
            'Calibri',
            'Arial',
            'Courier New',
            'CompObj',
            'DocumentSummaryInformation',
            'SummaryInformation',
            'WordDocument',
            'Table',
        ];

        foreach ($boilerplate as $term) {
            if ($trimmed === $term || str_contains($trimmed, 'Microsoft Office')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fallback extractor for plain text or generic documents.
     */
    private function extractFallback(string $content, ?string $mime = null): string
    {
        if ($mime && str_starts_with($mime, 'text/')) {
            return $this->cleanExtractedText($content);
        }

        // Try extracting readable ASCII/UTF-8 strings
        if (preg_match_all('/[\x20-\x7E\t\r\n]{4,}/', $content, $matches)) {
            return implode(' ', $matches[0]);
        }

        return '';
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
    private function limitLength(string $text): string
    {
        $maxChars = (int) config('pod_materials.extraction.max_extracted_characters', 500000);
        if ($maxChars > 0 && mb_strlen($text) > $maxChars) {
            return mb_substr($text, 0, $maxChars);
        }

        return $text;
    }
}
