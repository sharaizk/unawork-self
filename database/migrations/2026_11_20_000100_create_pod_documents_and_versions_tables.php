<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-020: POD Knowledge Base documents and version history.
 *
 * - pod_documents: knowledge base documents owned by a POD with title, description,
 *   type, status, author and timestamps.
 * - pod_document_versions: immutable published/archived versions and editable draft
 *   versions, preserving full content history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_id')->constrained('pods', 'id', 'pod_documents_pod_foreign')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 32);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users', 'id', 'pod_documents_created_by_foreign');
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id', 'pod_documents_updated_by_foreign')->nullOnDelete();
            $table->timestamps();

            $table->index(['pod_id', 'status'], 'pod_documents_pod_status_index');
            $table->index(['pod_id', 'type'], 'pod_documents_pod_type_index');
        });

        Schema::create('pod_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_document_id')->constrained('pod_documents', 'id', 'pod_doc_versions_document_foreign')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title')->nullable();
            $table->longText('content');
            $table->string('change_summary')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users', 'id', 'pod_doc_versions_created_by_foreign');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['pod_document_id', 'version_number'], 'pod_doc_versions_doc_version_unique');
            $table->index(['pod_document_id', 'status'], 'pod_doc_versions_doc_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_document_versions');
        Schema::dropIfExists('pod_documents');
    }
};
