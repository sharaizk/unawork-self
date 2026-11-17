<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-013: persisted, explainable match snapshots.
 *
 * - pod_matches: one row per (employee, POD worker requirement); re-evaluation updates it. Deleting
 *   the POD, the worker requirement or the user deletes the match (cascade).
 * - pod_match_reasons: one row per criterion of a match, in display order. Deleted with its match
 *   (cascade), so a reason never outlives its match.
 *
 * Reasons store stable codes and structured context, never translated prose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_id')->constrained('pods', 'id', 'pod_matches_pod_foreign')->cascadeOnDelete();
            $table->foreignId('pod_worker_requirement_id')
                ->constrained('pod_worker_requirements', 'id', 'pod_matches_requirement_foreign')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id', 'pod_matches_user_foreign')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->string('status', 20);
            // Sum of the weights of the scored (active) criteria; the score is renormalized from it.
            $table->unsignedSmallInteger('active_weight')->default(0);
            $table->string('scoring_version', 32);
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique(['user_id', 'pod_worker_requirement_id'], 'pod_matches_user_requirement_unique');
            $table->index(['pod_worker_requirement_id', 'status', 'score'], 'pod_matches_requirement_status_score_index');
            $table->index(['pod_id', 'status', 'score'], 'pod_matches_pod_status_score_index');
        });

        Schema::create('pod_match_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_match_id')->constrained('pod_matches', 'id', 'pod_match_reasons_match_foreign')->cascadeOnDelete();
            $table->string('criterion', 32);
            $table->unsignedTinyInteger('position');
            $table->boolean('active');
            $table->unsignedTinyInteger('possible_points')->default(0);
            $table->decimal('awarded_points', 5, 2)->default(0);
            $table->boolean('passed')->default(false);
            $table->boolean('mandatory')->default(false);
            $table->boolean('disqualifying')->default(false);
            $table->string('code', 64);
            $table->json('context')->nullable();
            $table->timestamps();

            $table->unique(['pod_match_id', 'criterion'], 'pod_match_reasons_match_criterion_unique');
            $table->index(['pod_match_id', 'position'], 'pod_match_reasons_match_position_index');
            $table->index(['criterion', 'passed'], 'pod_match_reasons_criterion_passed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_match_reasons');
        Schema::dropIfExists('pod_matches');
    }
};
