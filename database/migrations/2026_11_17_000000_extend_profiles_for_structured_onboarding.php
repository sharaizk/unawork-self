<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-007: structured employee profile fields.
 *
 * - The legacy required columns become nullable so an employee can save an incomplete draft.
 *   Existing values are untouched (only nullability changes).
 * - New nullable matching columns; the filterable ones are indexed. The existing `website`
 *   column stays the personal website (no duplicate column).
 *
 * down() drops the new columns. It does not restore NOT NULL on the legacy columns, because rows
 * saved as drafts may legitimately hold nulls by then and forcing NOT NULL would fail or lose data.
 */
return new class extends Migration
{
    private const LEGACY_NULLABLE = ['country', 'city', 'zip_code', 'address', 'phone_number', 'cv_file'];

    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            foreach (self::LEGACY_NULLABLE as $column) {
                $table->string($column)->nullable()->change();
            }
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->decimal('years_experience', 4, 1)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->string('github_url', 500)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('availability_status', 32)->nullable();
            $table->date('available_from')->nullable();
            $table->unsignedSmallInteger('hours_per_week')->nullable();
            $table->string('preferred_work_mode', 32)->nullable();
            $table->string('preferred_engagement_type', 32)->nullable();
            $table->boolean('open_to_relocation')->nullable();
            $table->boolean('willing_to_travel')->nullable();

            $table->index('years_experience', 'profiles_years_experience_index');
            $table->index('timezone', 'profiles_timezone_index');
            $table->index('availability_status', 'profiles_availability_status_index');
            $table->index('preferred_work_mode', 'profiles_preferred_work_mode_index');
            $table->index('preferred_engagement_type', 'profiles_preferred_engagement_type_index');
            $table->index(['country', 'city'], 'profiles_country_city_index');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex('profiles_years_experience_index');
            $table->dropIndex('profiles_timezone_index');
            $table->dropIndex('profiles_availability_status_index');
            $table->dropIndex('profiles_preferred_work_mode_index');
            $table->dropIndex('profiles_preferred_engagement_type_index');
            $table->dropIndex('profiles_country_city_index');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'years_experience', 'linkedin_url', 'github_url', 'timezone', 'availability_status',
                'available_from', 'hours_per_week', 'preferred_work_mode', 'preferred_engagement_type',
                'open_to_relocation', 'willing_to_travel',
            ]);
        });
    }
};
