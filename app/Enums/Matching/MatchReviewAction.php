<?php

namespace App\Enums\Matching;

/** Actions a business can take on a pending match review (UNA-018). */
enum MatchReviewAction: string
{
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case EXCLUDE = 'exclude';
}
