<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileCertification extends Model
{
    protected $table = 'profile_certifications';

    protected $fillable = ['name', 'issuing_organization', 'issue_date', 'expiration_date', 'credential_id', 'credential_url'];

    protected $casts = [
        'issue_date' => 'date',
        'expiration_date' => 'date',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
