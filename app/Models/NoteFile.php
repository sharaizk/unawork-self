<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'note_id',
        'file_name',
        'file_path',
    ];

    public function note(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}
