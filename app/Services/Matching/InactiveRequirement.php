<?php

namespace App\Services\Matching;

/** Matching is evaluated against active POD worker requirements only (UNA-013). */
class InactiveRequirement extends \DomainException
{
    public function __construct()
    {
        parent::__construct(__('This worker requirement is not active.'));
    }
}
