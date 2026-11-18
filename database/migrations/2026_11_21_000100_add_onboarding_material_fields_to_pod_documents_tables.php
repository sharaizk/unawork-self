<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-021: Add fields for POD onboarding materials, private file uploads, links,
 * upload processing status, and reordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pod_documents', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('status');
            $table->index(['pod_id', 'position'], 'pod_documents_pod_position_index');
        });

        Schema::table('pod_document_versions', function (Blueprint $table) {
            $table->longText('content')->nullable()->change();
            $table->string('link_url', 2048)->nullable()->after('file_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('link_url');
            $table->string('file_mime', 100)->nullable()->after('file_size');
            $table->string('upload_status', 20)->default('ready')->after('status');
            $table->text('failure_message')->nullable()->after('upload_status');

            $table->index(['pod_document_id', 'upload_status'], 'pod_doc_versions_doc_upload_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('pod_document_versions', function (Blueprint $table) {
            $table->dropIndex('pod_doc_versions_doc_upload_status_index');
            $table->dropColumn(['link_url', 'file_size', 'file_mime', 'upload_status', 'failure_message']);
        });

        Schema::table('pod_documents', function (Blueprint $table) {
            $table->dropIndex('pod_documents_pod_position_index');
            $table->dropColumn('position');
        });
    }
};
