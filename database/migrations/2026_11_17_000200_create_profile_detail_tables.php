<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-007: repeatable structured profile records (no JSON blobs). Every table belongs to a profile
 * and is deleted with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('employer');
            $table->string('job_title');
            // Catalog entries in use are never deleted (CatalogService); restrict as a database backstop.
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->restrictOnDelete();
            $table->string('engagement_type', 32)->nullable();
            $table->string('location')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'start_date'], 'profile_experiences_profile_id_start_date_index');
            $table->index('engagement_type', 'profile_experiences_engagement_type_index');
        });

        Schema::create('profile_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('institution');
            $table->string('degree')->nullable();
            $table->string('field_of_study')->nullable();
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('profile_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('name');
            $table->string('issuing_organization')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('credential_id')->nullable();
            $table->string('credential_url', 500)->nullable();
            $table->timestamps();

            $table->index('name', 'profile_certifications_name_index');
        });

        Schema::create('profile_portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('title');
            $table->string('url', 500);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('profile_preferred_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('job_title_id')->constrained('job_titles')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['profile_id', 'job_title_id'], 'profile_preferred_roles_profile_id_job_title_id_unique');
        });

        Schema::create('profile_project_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('project_type', 32);
            $table->timestamps();

            $table->unique(['profile_id', 'project_type'], 'profile_project_types_profile_id_project_type_unique');
            $table->index('project_type', 'profile_project_types_project_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_project_types');
        Schema::dropIfExists('profile_preferred_roles');
        Schema::dropIfExists('profile_portfolios');
        Schema::dropIfExists('profile_certifications');
        Schema::dropIfExists('profile_educations');
        Schema::dropIfExists('profile_experiences');
    }
};
