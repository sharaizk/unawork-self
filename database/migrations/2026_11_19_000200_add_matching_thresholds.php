<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-014: POD matching configuration.
 *
 * - pods.minimum_match_threshold (0-100, not null). Every existing POD receives
 *   config('pod_matching.default_minimum_threshold') through the column default; new PODs get a
 *   snapshot of their company's default from PodService::createDraft().
 * - workspaces.default_minimum_match_threshold (nullable): the company default for new PODs only.
 *   Null falls back to the config default. Never a live link to existing PODs.
 *
 * pods.matching_mode keeps its string column; 'recommend_only' is a new value next to the existing
 * 'pending_approval' and 'automatic', which stay valid. No index: no query filters on thresholds.
 */
return new class extends Migration
{
    public function up(): void
    {
        $default = max(0, min(100, (int) config('pod_matching.default_minimum_threshold', 70)));

        Schema::table('pods', function (Blueprint $table) use ($default) {
            $table->unsignedTinyInteger('minimum_match_threshold')->default($default);
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedTinyInteger('default_minimum_match_threshold')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('default_minimum_match_threshold');
        });
        Schema::table('pods', function (Blueprint $table) {
            $table->dropColumn('minimum_match_threshold');
        });
    }
};
