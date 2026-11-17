<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-018: explicit provenance of a matching proposal. One row per pod_users membership that the
 * matching workflow put into pending_business_approval; manual applications never get one.
 * pod_users.state stays the authoritative workflow state and pod_user_state_histories the
 * authoritative decision history; this row only records origin and review metadata.
 *
 * - pod_user_id: unique, cascades with the membership.
 * - source_pod_match_id: the match the proposal was made from; nullOnDelete, because regenerated or
 *   deleted matches must not erase the membership's provenance.
 * - decided_by: nullOnDelete.
 *
 * Backfill: only memberships that are pending right now AND whose state history shows they were
 * entered by the UNA-014 matching workflow (its exact reason string). Their source match cannot be
 * identified safely, so source_pod_match_id stays null. Runtime code never uses the reason string.
 */
return new class extends Migration
{
    private const LEGACY_MATCHING_REASON = 'Matched by POD matching, awaiting approval';

    public function up(): void
    {
        Schema::create('pod_match_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_user_id')->constrained('pod_users', 'id', 'pmr_pod_user_foreign')->cascadeOnDelete();
            $table->foreignId('source_pod_match_id')->nullable()->constrained('pod_matches', 'id', 'pmr_source_match_foreign')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('decision', 32)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users', 'id', 'pmr_decided_by_foreign')->nullOnDelete();
            $table->string('decision_reason', 2000)->nullable();
            $table->timestamps();

            $table->unique('pod_user_id', 'pmr_pod_user_unique');
            $table->index(['resolved_at', 'pod_user_id'], 'pmr_unresolved_index');
        });

        $now = now();
        $rows = DB::table('pod_users')
            ->where('pod_users.state', 'pending_business_approval')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('pod_user_state_histories as h')
                ->whereColumn('h.pod_user_id', 'pod_users.id')
                ->where('h.to_state', 'pending_business_approval')
                ->where('h.reason', self::LEGACY_MATCHING_REASON))
            ->pluck('pod_users.id')
            ->map(fn ($id) => ['pod_user_id' => $id, 'source_pod_match_id' => null, 'created_at' => $now, 'updated_at' => $now])
            ->all();
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('pod_match_reviews')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_match_reviews');
    }
};
