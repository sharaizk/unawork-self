<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('allow_pod_project_view')->default(true)->after('max_projects'); 
            $table->boolean('allow_workspace_project_view')->default(true)->after('allow_pod_project_view');
            $table->boolean('allow_gannt_chart_project_management')->default(true)->after('allow_workspace_project_view');
            $table->boolean('allow_bug_report_project_management')->default(true)->after('allow_gannt_chart_project_management');
            $table->boolean('allow_expenses_project_management')->default(true)->after('allow_bug_report_project_management');
            $table->boolean('allow_files_project_management')->default(true)->after('allow_expenses_project_management');
            $table->boolean('allow_chat_project_management')->default(true)->after('allow_files_project_management');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['allow_pod_project_view', 'allow_workspace_project_view', 'allow_gann_chart_project_management', 'allow_bug_report_project_management', 'allow_expenses_project_management', 'allow_files_project_management', 'allow_chat_project_management']);
        });
    }
};
