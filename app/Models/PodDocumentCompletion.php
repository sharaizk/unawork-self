<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks completion of a specific published POD document version by a user (UNA-023).
 */
class PodDocumentCompletion extends Model
{
    use HasFactory;

    protected $table = 'pod_document_completions';

    protected $fillable = [
        'user_id',
        'pod_id',
        'pod_document_id',
        'pod_document_version_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pod(): BelongsTo
    {
        return $this->belongsTo(Pod::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PodDocument::class, 'pod_document_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PodDocumentVersion::class, 'pod_document_version_id');
    }
}
