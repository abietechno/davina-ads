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
        'access_token',
        'api_key_or_refresh_token',
        'last_synced_at',
    ];

    protected $hidden = [
        'access_token',
        'api_key_or_refresh_token',
    ];

    public function dailyAdMetrics(): HasMany
    {
        return $this->hasMany(DailyAdMetric::class);
    }
}
