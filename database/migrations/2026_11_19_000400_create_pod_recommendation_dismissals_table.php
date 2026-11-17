<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-017: an employee hiding a POD from their own recommendation screen. A filtering preference
 * only: matches, reasons and memberships are untouched, and the POD stays in manual discovery.
 * One row per (user, POD); deleted with the user or the POD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_recommendation_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'id', 'prd_user_foreign')->cascadeOnDelete();
            $table->foreignId('pod_id')->constrained('pods', 'id', 'prd_pod_foreign')->cascadeOnDelete();
            $table->timestamp('dismissed_at');
            $table->timestamps();

            // Leading user_id also serves "this employee's dismissals" lookups.
            $table->unique(['user_id', 'pod_id'], 'prd_user_pod_unique');
            $table->index('pod_id', 'prd_pod_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_recommendation_dismissals');
    }
};
