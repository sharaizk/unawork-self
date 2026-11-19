<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-023: Create table to track completion per user, POD, material, and specific published version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_document_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pod_id');
            $table->unsignedBigInteger('pod_document_id');
            $table->unsignedBigInteger('pod_document_version_id');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['user_id', 'pod_document_version_id'], 'pod_doc_completions_user_version_unique');
            $table->index(['user_id', 'pod_id'], 'pod_doc_completions_user_pod_index');
            $table->index(['pod_document_id', 'user_id'], 'pod_doc_completions_doc_user_index');
            $table->index(['pod_id', 'user_id'], 'pod_doc_completions_pod_user_index');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('pod_id')->references('id')->on('pods')->onDelete('cascade');
            $table->foreign('pod_document_id')->references('id')->on('pod_documents')->onDelete('cascade');
            $table->foreign('pod_document_version_id')->references('id')->on('pod_document_versions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_document_completions');
    }
};
