<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-023: Add is_mandatory flag to pod_documents table to designate required
 * vs optional learning materials for Outer Circle users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pod_documents', function (Blueprint $table) {
            $table->boolean('is_mandatory')->default(false)->after('position');
            $table->index(['pod_id', 'is_mandatory'], 'pod_documents_pod_is_mandatory_index');
        });
    }

    public function down(): void
    {
        Schema::table('pod_documents', function (Blueprint $table) {
            $table->dropIndex('pod_documents_pod_is_mandatory_index');
            $table->dropColumn('is_mandatory');
        });
    }
};
