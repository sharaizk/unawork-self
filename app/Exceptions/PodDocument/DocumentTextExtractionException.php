<?php

namespace App\Exceptions\PodDocument;

use RuntimeException;
use Throwable;

/**
 * Thrown when an AI document text extraction provider encounters an error (UNA-022).
 *
 * Carries a user-safe, translated message suitable for display to business managers
 * on the document version screen, with optional technical details kept for logs.
 */
class DocumentTextExtractionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerErrorStatus = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
