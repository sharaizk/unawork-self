<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-011: POD draft/publish lifecycle and POD-owned worker requirements.
 *
 * - pods.status defaults to 'draft' for new PODs; every POD that exists when this runs was public
 *   before, so it becomes 'published' with published_at = created_at. Ids, workspaces,
 *   memberships, histories, skills and URLs are untouched.
 * - pod_worker_requirements (+ pod_worker_requirement_skill) are POD-owned snapshots. A row may
 *   remember the company template it was imported from (source_workspace_requirement_id), but later
 *   template edits or deletion never change it (nullOnDelete only clears the reference).
 *
 * down() drops the new tables and columns (the published/draft distinction is lost).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pods', function (Blueprint $table) {
            $table->string('status', 20)->default('draft');
            $table->string('matching_mode', 20)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('configuration_started_at')->nullable();
            $table->timestamp('configuration_completed_at')->nullable();

            $table->index('status', 'pods_status_index');
            $table->index(['created_by', 'status'], 'pods_created_by_status_index');
        });

        // Existing PODs stay public.
        DB::table('pods')->update(['status' => 'published']);
        DB::table('pods')->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);

        Schema::create('pod_worker_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_id')->constrained('pods')->cascadeOnDelete();
            $table->foreignId('source_workspace_requirement_id')->nullable()
                ->constrained('workspace_worker_requirements', 'id', 'pwr_source_requirement_foreign')->nullOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles', 'id', 'pwr_job_title_foreign')->restrictOnDelete();
            $table->unsignedSmallInteger('headcount')->nullable();
            $table->decimal('minimum_years_experience', 4, 1)->nullable();
            $table->string('minimum_skill_proficiency', 20)->nullable();
            $table->string('preferred_work_mode', 32)->nullable();
            $table->string('preferred_engagement_type', 32)->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['pod_id', 'is_active'], 'pwr_pod_active_index');
            $table->index('job_title_id', 'pwr_job_title_id_index');
        });

        Schema::create('pod_worker_requirement_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_worker_requirement_id')->constrained('pod_worker_requirements', 'id', 'pwrs_requirement_foreign')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills', 'id', 'pwrs_skill_foreign')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['pod_worker_requirement_id', 'skill_id'], 'pwrs_requirement_skill_unique');
            $table->index('skill_id', 'pwrs_skill_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_worker_requirement_skill');
        Schema::dropIfExists('pod_worker_requirements');
        Schema::table('pods', function (Blueprint $table) {
            $table->dropIndex('pods_status_index');
            $table->dropIndex('pods_created_by_status_index');
        });
        Schema::table('pods', function (Blueprint $table) {
            $table->dropColumn(['status', 'matching_mode', 'published_at', 'configuration_started_at', 'configuration_completed_at']);
        });
    }
};
