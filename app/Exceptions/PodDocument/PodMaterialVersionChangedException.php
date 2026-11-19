<?php

namespace App\Exceptions\PodDocument;

use RuntimeException;

/**
 * Thrown when an applicant attempts to complete a document version that has been superseded
 * by a newly published version or is no longer the active published version (UNA-023).
 */
class PodMaterialVersionChangedException extends RuntimeException
{
    public function __construct(string $message = 'This material was recently updated with a new version. Please review the updated content before completing.')
    {
        parent::__construct($message);
    }
}
