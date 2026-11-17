<?php

namespace App\Services\Matching;

/** The review was already decided, or its membership left pending meanwhile (UNA-018, HTTP 409). */
class MatchReviewConflict extends \DomainException
{
    public function __construct()
    {
        parent::__construct(__('This match has already been reviewed.'));
    }
}
