<?php

namespace App\Services\Catalog;

/** A catalog entry cannot be deleted because other records reference it. */
class CatalogEntryInUse extends \DomainException
{
    public function __construct(string $message, public readonly int $usage)
    {
        parent::__construct($message);
    }
}
