<?php

namespace App\Enums\PodUser;

/**
 * The single authoritative workflow / circle state of a POD membership (pod_users.state, UNA-006).
 *
 * Workspace permissions are access controls that are synchronized FROM this state
 * (see accessProfile()); they are never read to decide it. All changes go through
 * App\Services\Membership\PodStateTransitionService, which validates the graph below and writes
 * one pod_user_state_histories row per change.
 */
enum State: string
{
    case MATCHED = 'matched';                                    // invited by the POD, awaiting the employee
    case PENDING_BUSINESS_APPROVAL = 'pending_business_approval'; // applied, awaiting the POD
    case OUTER_CIRCLE = 'outer_circle';
    case SCREENING_IN_PROGRESS = 'screening_in_progress';
    case SCREENING_COMPLETED = 'screening_completed';
    case PENDING_INNER_APPROVAL = 'pending_inner_approval';
    case INNER_CIRCLE = 'inner_circle';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';
    case REMOVED = 'removed';

    /** @return self[] states reachable from this one */
    public function transitions(): array
    {
        return match ($this) {
            self::MATCHED, self::PENDING_BUSINESS_APPROVAL => [self::OUTER_CIRCLE, self::REJECTED, self::REMOVED],
            self::OUTER_CIRCLE => [self::SCREENING_IN_PROGRESS, self::PENDING_INNER_APPROVAL, self::INNER_CIRCLE, self::SUSPENDED, self::REMOVED],
            self::SCREENING_IN_PROGRESS => [self::SCREENING_COMPLETED, self::OUTER_CIRCLE, self::REJECTED, self::SUSPENDED, self::REMOVED],
            self::SCREENING_COMPLETED => [self::PENDING_INNER_APPROVAL, self::OUTER_CIRCLE, self::REJECTED, self::SUSPENDED, self::REMOVED],
            self::PENDING_INNER_APPROVAL => [self::INNER_CIRCLE, self::OUTER_CIRCLE, self::REJECTED, self::SUSPENDED, self::REMOVED],
            self::INNER_CIRCLE => [self::OUTER_CIRCLE, self::SUSPENDED, self::REMOVED],
            self::SUSPENDED => [self::OUTER_CIRCLE, self::INNER_CIRCLE, self::REMOVED],
            // Re-entry: the existing unique row (UNA-004) is moved back into a workflow.
            self::REJECTED => [self::MATCHED, self::PENDING_BUSINESS_APPROVAL, self::REMOVED],
            self::REMOVED => [self::MATCHED, self::PENDING_BUSINESS_APPROVAL],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    /** Rejection, suspension, removal, demotion and resuming from suspension need a reason. */
    public function requiresReasonFrom(?self $from): bool
    {
        return in_array($this, [self::REJECTED, self::SUSPENDED, self::REMOVED], true)
            || ($from === self::INNER_CIRCLE && $this === self::OUTER_CIRCLE)
            || $from === self::SUSPENDED;
    }

    /** Access consequence of the state: 'none', 'outer' or 'inner'. */
    public function accessProfile(): string
    {
        return match ($this) {
            self::OUTER_CIRCLE, self::SCREENING_IN_PROGRESS, self::SCREENING_COMPLETED, self::PENDING_INNER_APPROVAL => 'outer',
            self::INNER_CIRCLE => 'inner',
            default => 'none',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MATCHED => __('Invited'),
            self::PENDING_BUSINESS_APPROVAL => __('Awaiting POD approval'),
            self::OUTER_CIRCLE => __('Member Outer Circle'),
            self::SCREENING_IN_PROGRESS => __('Screening in progress'),
            self::SCREENING_COMPLETED => __('Screening completed'),
            self::PENDING_INNER_APPROVAL => __('Awaiting Inner Circle approval'),
            self::INNER_CIRCLE => __('Member Inner Circle'),
            self::REJECTED => __('Declined'),
            self::SUSPENDED => __('Suspended'),
            self::REMOVED => __('Removed'),
        };
    }

    /** @return self[] awaiting a decision (invitation or application) */
    public static function applicants(): array
    {
        return [self::MATCHED, self::PENDING_BUSINESS_APPROVAL];
    }

    /** @return self[] members with workspace access (Outer or Inner Circle, incl. screening) */
    public static function activeMembers(): array
    {
        return [self::OUTER_CIRCLE, self::SCREENING_IN_PROGRESS, self::SCREENING_COMPLETED, self::PENDING_INNER_APPROVAL, self::INNER_CIRCLE];
    }

    /** @return self[] Outer Circle states (everything active except Inner Circle) */
    public static function outerCircle(): array
    {
        return [self::OUTER_CIRCLE, self::SCREENING_IN_PROGRESS, self::SCREENING_COMPLETED, self::PENDING_INNER_APPROVAL];
    }

    /** @return self[] states a new invitation/application may restart from */
    public static function reentry(): array
    {
        return [self::REJECTED, self::REMOVED];
    }

    /** @return string[] */
    public static function values(array $states): array
    {
        return array_map(fn (self $state) => $state->value, $states);
    }
}
