<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPlatformConnection extends Model
{
    protected $fillable = [
        'platform',
        'refresh_token',
        'email',
        'connected_by',
    ];

    protected $hidden = [
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
        ];
    }
}
