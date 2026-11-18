<?php

namespace App\Exceptions\PodDocument;

/**
 * Exception thrown when attempting to publish a version that is not in draft status.
 * Published and archived versions are immutable and cannot be republished (UNA-020).
 */
class CannotPublishNonDraftVersionException extends CannotModifyImmutableVersionException
{
    public function __construct(string $message = 'Only draft document versions can be published. Published and archived versions cannot be republished.')
    {
        parent::__construct($message);
    }
}
