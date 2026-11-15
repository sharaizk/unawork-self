<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-007: skill proficiency on the user_skills pivot, plus a unique (user_id, skill_id) pair.
 *
 * Legacy rows keep proficiency = null (still valid; they just do not count towards the
 * proficiency-rated skills completion section until edited).
 *
 * Duplicate (user_id, skill_id) rows can only differ by id and timestamps (the pivot had no other
 * data), so collapsing them loses nothing meaningful: the oldest row (lowest id) of each pair is
 * kept and the others are deleted before the unique index is added.
 *
 * down() drops the unique index and the proficiency column; deleted duplicates are not recreated.
 */
return new class extends Migration
{
    private const UNIQUE = 'user_skills_user_id_skill_id_unique';

    public function up(): void
    {
        Schema::table('user_skills', function (Blueprint $table) {
            $table->string('proficiency', 20)->nullable();
            $table->index('proficiency', 'user_skills_proficiency_index');
        });

        DB::table('user_skills')
            ->select('user_id', 'skill_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'skill_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('user_id')
            ->get()
            ->each(function ($pair) {
                DB::table('user_skills')
                    ->where('user_id', $pair->user_id)
                    ->where('skill_id', $pair->skill_id)
                    ->where('id', '!=', $pair->keep_id)
                    ->delete();
            });

        Schema::table('user_skills', function (Blueprint $table) {
            $table->unique(['user_id', 'skill_id'], self::UNIQUE);
        });
    }

    public function down(): void
    {
        Schema::table('user_skills', function (Blueprint $table) {
            // MySQL may use the unique index for the user_id foreign key; give it its own first.
            $table->index('user_id', 'user_skills_user_id_index');
        });
        Schema::table('user_skills', function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE);
            $table->dropIndex('user_skills_proficiency_index');
            $table->dropColumn('proficiency');
        });
    }
};
