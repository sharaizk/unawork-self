<?php

namespace App\Exceptions\PodDocument;

use RuntimeException;

class CannotDeletePublishedVersionException extends RuntimeException
{
    public function __construct(string $message = 'Published and archived document versions cannot be deleted. Version history must be preserved.')
    {
        parent::__construct($message);
    }
}
