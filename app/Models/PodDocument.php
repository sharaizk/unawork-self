<?php

namespace App\Models;

use App\Enums\PodDocument\PodDocumentStatus;
use App\Enums\PodDocument\PodDocumentType;
use App\Enums\PodDocument\PodDocumentUploadStatus;
use App\Enums\PodDocument\PodDocumentVersionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A POD Knowledge Base document (UNA-020).
 *
 * Owned by a POD, with typed classification, lifecycle status, and one or more
 * historical versions.
 */
class PodDocument extends Model
{
    use HasFactory;

    protected $table = 'pod_documents';

    protected $fillable = [
        'pod_id',
        'title',
        'description',
        'type',
        'status',
        'position',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => PodDocumentType::class,
        'status' => PodDocumentStatus::class,
        'position' => 'integer',
    ];

    public function pod(): BelongsTo
    {
        return $this->belongsTo(Pod::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PodDocumentVersion::class, 'pod_document_id')->orderBy('version_number', 'desc');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), PodDocumentStatus::PUBLISHED->value);
    }

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), PodDocumentStatus::DRAFT->value);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), PodDocumentStatus::ARCHIVED->value);
    }

    public function scopeOfType(Builder $query, PodDocumentType|string $type): Builder
    {
        $val = $type instanceof PodDocumentType ? $type->value : $type;
        return $query->where($query->qualifyColumn('type'), $val);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy($query->qualifyColumn('position'), 'asc')
            ->orderBy($query->qualifyColumn('id'), 'asc');
    }

    public function isPublished(): bool
    {
        return $this->status === PodDocumentStatus::PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === PodDocumentStatus::DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === PodDocumentStatus::ARCHIVED;
    }

    /**
     * The active published version, if any. Deterministic selection.
     * Only versions with READY upload status qualify.
     */
    public function publishedVersion(): ?PodDocumentVersion
    {
        return $this->versions()
            ->where('status', PodDocumentVersionStatus::PUBLISHED->value)
            ->where('upload_status', PodDocumentUploadStatus::READY->value)
            ->orderByDesc('version_number')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The latest draft version, if any.
     */
    public function draftVersion(): ?PodDocumentVersion
    {
        return $this->versions()
            ->where('status', PodDocumentVersionStatus::DRAFT->value)
            ->first();
    }

    /**
     * The latest version by version number.
     */
    public function latestVersion(): ?PodDocumentVersion
    {
        return $this->versions()->first();
    }
}
