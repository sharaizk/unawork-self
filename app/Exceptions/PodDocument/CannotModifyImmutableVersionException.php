<?php

namespace App\Exceptions\PodDocument;

use RuntimeException;

class CannotModifyImmutableVersionException extends RuntimeException
{
    public function __construct(string $message = 'Published and archived document versions are immutable and cannot be modified.')
    {
        parent::__construct($message);
    }
}
