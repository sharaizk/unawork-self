<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-022: Add fields for asynchronous text extraction, processing status,
 * extraction timestamps, and failure messages on pod_document_versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pod_document_versions', function (Blueprint $table) {
            $table->longText('extracted_text')->nullable()->after('content');
            $table->string('extraction_status', 20)->default('pending')->after('upload_status');
            $table->string('extraction_token', 64)->nullable()->after('extraction_status');
            $table->timestamp('extracted_at')->nullable()->after('published_at');
            $table->text('extraction_failure_message')->nullable()->after('failure_message');

            $table->index(['pod_document_id', 'extraction_status'], 'pod_doc_versions_doc_extraction_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('pod_document_versions', function (Blueprint $table) {
            $table->dropIndex('pod_doc_versions_doc_extraction_status_index');
            $table->dropColumn(['extracted_text', 'extraction_status', 'extraction_token', 'extracted_at', 'extraction_failure_message']);
        });
    }
};
