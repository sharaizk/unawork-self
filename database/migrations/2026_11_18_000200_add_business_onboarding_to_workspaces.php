<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-010: business onboarding on the company workspace. The existing company columns (company,
 * industry_id, logo, address, city, state, zipcode, country, telephone, description, website,
 * linkedin, facebook) are reused; this only adds the contact person, matching/approval defaults and
 * onboarding progress. Every column is nullable, so existing workspaces (company and POD) stay valid
 * and nothing is backfilled. Completion is always calculated, never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_position')->nullable();
            $table->string('matching_mode', 20)->nullable();
            $table->string('default_pod_application_approval', 20)->nullable();
            $table->string('default_inner_circle_approval', 20)->nullable();
            $table->timestamp('business_onboarding_started_at')->nullable();
            $table->timestamp('business_onboarding_completed_at')->nullable();
            $table->string('business_onboarding_step', 32)->nullable();

            $table->index('matching_mode', 'workspaces_matching_mode_index');
            $table->index('business_onboarding_completed_at', 'workspaces_business_onboarding_completed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex('workspaces_matching_mode_index');
            $table->dropIndex('workspaces_business_onboarding_completed_at_index');
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'contact_name', 'contact_email', 'contact_position', 'matching_mode',
                'default_pod_application_approval', 'default_inner_circle_approval',
                'business_onboarding_started_at', 'business_onboarding_completed_at', 'business_onboarding_step',
            ]);
        });
    }
};
