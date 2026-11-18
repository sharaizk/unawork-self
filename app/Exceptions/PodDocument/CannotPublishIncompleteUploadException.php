<?php

namespace App\Exceptions\PodDocument;

/**
 * Thrown when attempting to publish a document version whose file upload or processing
 * is not ready (e.g. pending or failed) (UNA-021).
 */
class CannotPublishIncompleteUploadException extends CannotModifyImmutableVersionException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message ?: __('Cannot publish onboarding material with pending or failed upload status.'));
    }
}
