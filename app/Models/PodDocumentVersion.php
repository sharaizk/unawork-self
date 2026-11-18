<?php

namespace App\Models;

use App\Enums\PodDocument\PodDocumentUploadStatus;
use App\Enums\PodDocument\PodDocumentVersionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A historical or draft version of a POD Knowledge Base document (UNA-020).
 *
 * Versions that have been published or archived are immutable and cannot be
 * deleted or overwritten.
 */
class PodDocumentVersion extends Model
{
    use HasFactory;

    protected $table = 'pod_document_versions';

    protected $fillable = [
        'pod_document_id',
        'version_number',
        'title',
        'content',
        'change_summary',
        'file_path',
        'file_name',
        'link_url',
        'file_size',
        'file_mime',
        'status',
        'upload_status',
        'failure_message',
        'created_by',
        'published_at',
        'archived_at',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'file_size' => 'integer',
        'status' => PodDocumentVersionStatus::class,
        'upload_status' => PodDocumentUploadStatus::class,
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(PodDocument::class, 'pod_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), PodDocumentVersionStatus::PUBLISHED->value);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), PodDocumentVersionStatus::DRAFT->value);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), PodDocumentVersionStatus::ARCHIVED->value);
    }

    public function scopeUploadReady(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('upload_status'), PodDocumentUploadStatus::READY->value);
    }

    public function scopeUploadFailed(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('upload_status'), PodDocumentUploadStatus::FAILED->value);
    }

    public function isPublished(): bool
    {
        return $this->status === PodDocumentVersionStatus::PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === PodDocumentVersionStatus::DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === PodDocumentVersionStatus::ARCHIVED;
    }

    public function isUploadReady(): bool
    {
        return $this->upload_status === PodDocumentUploadStatus::READY;
    }

    public function isUploadPending(): bool
    {
        return $this->upload_status === PodDocumentUploadStatus::PENDING;
    }

    public function isUploadFailed(): bool
    {
        return $this->upload_status === PodDocumentUploadStatus::FAILED;
    }

    public function isFile(): bool
    {
        return !empty($this->file_path);
    }

    public function isLink(): bool
    {
        return !empty($this->link_url);
    }

    public function isTextOnly(): bool
    {
        return empty($this->file_path) && empty($this->link_url) && !empty($this->content);
    }

    public function readableFileSize(): ?string
    {
        if ($this->file_size === null || $this->file_size <= 0) {
            return null;
        }

        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    public function fileExtension(): ?string
    {
        if (!empty($this->file_name)) {
            $ext = pathinfo($this->file_name, PATHINFO_EXTENSION);
            return $ext ? strtoupper($ext) : null;
        }
        if (!empty($this->file_path)) {
            $ext = pathinfo($this->file_path, PATHINFO_EXTENSION);
            return $ext ? strtoupper($ext) : null;
        }
        return null;
    }

    /**
     * Whether this version is immutable (published, archived, or has a published timestamp).
     */
    public function isImmutable(): bool
    {
        return $this->isPublished() || $this->isArchived() || $this->published_at !== null;
    }
}
