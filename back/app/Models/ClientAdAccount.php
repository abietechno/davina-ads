<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientAdAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'account_name',
        'account_id',
        'currency',
        'timezone',
        'conversion_action_type',
        'is_active',
        'access_token',
        'api_key_or_refresh_token',
        'last_synced_at',
    ];

    protected $hidden = [
        'access_token',
        'api_key_or_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function dailyAdMetrics(): HasMany
    {
        return $this->hasMany(DailyAdMetric::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(AdSyncRun::class);
    }
}
