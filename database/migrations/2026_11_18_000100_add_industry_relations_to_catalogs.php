<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Industries classify the Skills and Professional Roles catalogs (many-to-many: a skill such as
 * "Project Management" belongs to several industries) and an employee profile has one optional
 * primary industry, used to narrow the skill and role pickers on /my-profile.
 *
 * Deleting a skill or role removes only its industry tags. Industries themselves are protected:
 * the application refuses to delete one that is in use (CatalogService) and the foreign keys
 * restrict as a backstop. Existing rows are untouched; everything starts unclassified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industry_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('industry_id')->constrained('industries')->restrictOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['industry_id', 'skill_id'], 'industry_skill_industry_id_skill_id_unique');
            $table->index('skill_id', 'industry_skill_skill_id_index');
        });

        Schema::create('industry_job_title', function (Blueprint $table) {
            $table->id();
            $table->foreignId('industry_id')->constrained('industries')->restrictOnDelete();
            $table->foreignId('job_title_id')->constrained('job_titles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['industry_id', 'job_title_id'], 'industry_job_title_industry_id_job_title_id_unique');
            $table->index('job_title_id', 'industry_job_title_job_title_id_index');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->foreignId('industry_id')->nullable()->constrained('industries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') { // SQLite cannot drop a foreign key in place
                $table->dropForeign(['industry_id']);
            }
            $table->dropColumn('industry_id');
        });
        Schema::dropIfExists('industry_job_title');
        Schema::dropIfExists('industry_skill');
    }
};
