<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-013: POD-side matching targets that did not exist yet, on both the company templates
 * (workspace_worker_requirements) and the POD-owned snapshots (pod_worker_requirements), so an
 * import copies them.
 *
 * Every column is nullable: existing requirements keep their current meaning (a null target is an
 * inactive criterion that never affects a score). mandatory_criteria is a list of
 * App\Enums\Matching\MatchCriterion values; null/empty means nothing is mandatory.
 */
return new class extends Migration
{
    private const TABLES = ['workspace_worker_requirements', 'pod_worker_requirements'];

    private const COLUMNS = [
        'required_start_date', 'minimum_hours_per_week', 'timezone', 'timezone_tolerance_hours', 'project_type', 'mandatory_criteria',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->date('required_start_date')->nullable();
                $t->unsignedTinyInteger('minimum_hours_per_week')->nullable();
                $t->string('timezone', 64)->nullable();
                $t->unsignedTinyInteger('timezone_tolerance_hours')->nullable();
                $t->string('project_type', 32)->nullable();
                $t->json('mandatory_criteria')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(self::COLUMNS);
            });
        }
    }
};
