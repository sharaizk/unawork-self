<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-016: pod_matches.algorithm, the stable key of the algorithm that generated a row
 * (App\Contracts\PodMatchingAlgorithm::key()). scoring_version stays the algorithm version and
 * evaluated_at stays the generation timestamp; neither is renamed or rewritten.
 *
 * Every existing row was produced by the deterministic UNA-013 scorer, so it is backfilled with
 * 'deterministic'. The column then becomes NOT NULL without a default, so a new row cannot omit
 * it. No index: nothing queries by algorithm yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pod_matches', function (Blueprint $table) {
            $table->string('algorithm', 32)->nullable()->after('status');
        });

        DB::table('pod_matches')->whereNull('algorithm')->update(['algorithm' => 'deterministic']);

        Schema::table('pod_matches', function (Blueprint $table) {
            $table->string('algorithm', 32)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pod_matches', function (Blueprint $table) {
            $table->dropColumn('algorithm');
        });
    }
};
