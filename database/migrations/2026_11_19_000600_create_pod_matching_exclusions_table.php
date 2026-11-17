<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-018: a business excluded an employee from future automated matching for one POD. It stops
 * generation, workflow placement and recommendations for that pair; it is not a POD ban (manual
 * discovery and application keep their own rules) and deletes no history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_matching_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_id')->constrained('pods', 'id', 'pme_pod_foreign')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id', 'pme_user_foreign')->cascadeOnDelete();
            $table->foreignId('excluded_by')->nullable()->constrained('users', 'id', 'pme_excluded_by_foreign')->nullOnDelete();
            $table->string('reason', 2000)->nullable();
            $table->timestamps();

            $table->unique(['pod_id', 'user_id'], 'pme_pod_user_unique'); // also serves POD lookups
            $table->index('user_id', 'pme_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_matching_exclusions');
    }
};
