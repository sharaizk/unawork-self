<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-010: structured worker requirements of a company workspace, built on the administrator
 * catalogs (job_titles, skills). A requirement is deleted with its workspace; catalog entries in use
 * by a requirement cannot be deleted (CatalogService), and the foreign keys restrict as a backstop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_worker_requirements', function (Blueprint $table) {
            $table->id();
            // workspaces.id is unsigned bigint (bigIncrements).
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->restrictOnDelete();
            $table->unsignedSmallInteger('headcount')->nullable();
            $table->decimal('minimum_years_experience', 4, 1)->nullable();
            $table->string('minimum_skill_proficiency', 20)->nullable();
            $table->string('preferred_work_mode', 32)->nullable();
            $table->string('preferred_engagement_type', 32)->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'is_active'], 'workspace_worker_requirements_workspace_active_index');
            $table->index('job_title_id', 'workspace_worker_requirements_job_title_id_index');
        });

        Schema::create('workspace_worker_requirement_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_worker_requirement_id')->constrained('workspace_worker_requirements', 'id', 'wwrs_requirement_foreign')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills', 'id', 'wwrs_skill_foreign')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['workspace_worker_requirement_id', 'skill_id'], 'wwrs_requirement_skill_unique');
            $table->index('skill_id', 'wwrs_skill_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_worker_requirement_skill');
        Schema::dropIfExists('workspace_worker_requirements');
    }
};
