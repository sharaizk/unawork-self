<?php

namespace App\Exceptions\PodDocument;

use RuntimeException;

class CannotDeleteDocumentWithHistoryException extends RuntimeException
{
    public function __construct(string $message = 'Documents with published version history cannot be deleted. Archive the document instead.')
    {
        parent::__construct($message);
    }
}
