<?php

namespace App\Services\Membership;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs a MembershipResult's external effects once the database work is durable (UNA-005).
 *
 * Each effect is handed to DB::afterCommit(): it runs immediately when no transaction is open
 * (the normal case, since services call this after their own DB::transaction() returned), or
 * when an enclosing transaction commits; it is discarded if that transaction rolls back.
 * A failing effect (SMTP, Pusher, chat) is reported and recorded on the result, never
 * rethrown: the membership change it follows has already been committed.
 */
class AfterCommit
{
    public function run(MembershipResult $result): MembershipResult
    {
        foreach ($result->effects() as $name => $effect) {
            DB::afterCommit(function () use ($name, $effect, $result) {
                try {
                    $effect();
                } catch (Throwable $e) {
                    report($e);
                    $result->failedEffects[$name] = $e->getMessage();
                }
            });
        }

        return $result;
    }
}
