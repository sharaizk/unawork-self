<?php

namespace App\Services\Matching;

use App\Enums\Matching\MembershipAction;
use App\Enums\PodUser\State;
use App\Models\PodMatch;

/** Result of PodMatchingWorkflowService::evaluateAndApply() (UNA-014). */
final class PodMatchOutcome
{
    public function __construct(
        public readonly PodMatch $match,
        public readonly MatchQualification $qualification,
        public readonly MembershipAction $action,
        // Membership state after the call (null when the candidate has no membership).
        public readonly ?State $membershipState = null,
        // Short machine reason for UNCHANGED / BLOCKED / NONE (e.g. "already_member", "profile_incomplete").
        public readonly ?string $reason = null,
    ) {
    }
}
