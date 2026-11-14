<?php

namespace App\Services\Membership;

use Illuminate\Database\Eloquent\Model;

/**
 * Outcome of one transactional membership operation (UNA-005).
 *
 * applied  true when this call changed membership state; false for an existing / already
 *          handled / rejected outcome (no state changed, no effects scheduled).
 * state    the final status of the membership the operation was about (or null if gone).
 * reason   for a not-applied outcome, a short business reason key (e.g. "already_handled").
 * effects  named callbacks to run only after the transaction commits (notifications, emails,
 *          chat invitations). They are built inside the transaction attempt that committed, so a
 *          retried attempt never runs them twice.
 */
final class MembershipResult
{
    /** @var array<string, string> effect name => failure message, filled after running */
    public array $failedEffects = [];

    /** @param array<string, \Closure> $effects */
    private function __construct(
        public readonly bool $applied,
        public readonly ?string $state,
        public readonly ?Model $membership,
        public readonly ?string $reason,
        private readonly array $effects,
    ) {
    }

    /** @param array<string, \Closure> $effects */
    public static function applied(?Model $membership, ?string $state, array $effects = []): self
    {
        return new self(true, $state, $membership, null, array_filter($effects));
    }

    public static function unchanged(?Model $membership, string $reason): self
    {
        return new self(false, $membership?->status, $membership, $reason, []);
    }

    /** @return array<string, \Closure> */
    public function effects(): array
    {
        return $this->effects;
    }

    /** @return string[] */
    public function effectNames(): array
    {
        return array_keys($this->effects);
    }
}
