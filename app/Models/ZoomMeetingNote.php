<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoomMeetingNote extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'meeting_id',
        'user_id',
        'text',
    ];

    /**
     * Зв'язок з користувачем.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Зв'язок з мітингом.
     */
    public function meeting()
    {
        return $this->belongsTo(ZoomMeeting::class, 'meeting_id');
    }
}
