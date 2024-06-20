<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'short_content',
        'newsable_id',
        'newsable_type',
        'created_by'
    ];

    public function newsable()
    {
        return $this->morphTo();
    }
}
