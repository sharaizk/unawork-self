<?php

namespace App\Services\Matching;

use App\Enums\Matching\MembershipAction;

/**
 * Counts of one recalculation run (UNA-014). Failure details are reported to the log only, never
 * exposed to users.
 */
final class RecalculationSummary
{
    public int $evaluated = 0;
    public int $qualified = 0;
    public int $created = 0;
    public int $advanced = 0;
    public int $unchanged = 0;   // no membership action (not qualified, recommend only, draft, already a member)
    public int $skipped = 0;     // refused (incomplete profile) or no longer a candidate
    public int $failed = 0;      // unexpected error, reported
    public int $invalidated = 0; // matches deleted because their requirement became inactive

    public function record(PodMatchOutcome $outcome): void
    {
        $this->evaluated++;
        $this->qualified += $outcome->qualification->qualified ? 1 : 0;
        match ($outcome->action) {
            MembershipAction::CREATED => $this->created++,
            MembershipAction::ADVANCED => $this->advanced++,
            MembershipAction::BLOCKED => $this->skipped++,
            MembershipAction::NONE, MembershipAction::UNCHANGED => $this->unchanged++,
        };
    }

    public function merge(self $other): self
    {
        foreach (get_object_vars($other) as $key => $value) {
            $this->{$key} += $value;
        }

        return $this;
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
