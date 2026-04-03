<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name',
        'logo_path',
        'login_banner_path',
        'tagline',
    ];

    protected $appends = ['logo_url', 'login_banner_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? url('storage/' . $this->logo_path)
            : null;
    }

    public function getLoginBannerUrlAttribute(): ?string
    {
        return $this->login_banner_path
            ? url('storage/' . $this->login_banner_path)
            : null;
    }
}
