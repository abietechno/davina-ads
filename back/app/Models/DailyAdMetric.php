<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAdMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_ad_account_id',
        'level',
        'date',
        'campaign_name',
        'campaign_id',
        'adset_id',
        'adset_name',
        'ad_id',
        'ad_name',
        'spend',
        'impressions',
        'clicks',
        'reach',
        'conversions',
        'revenue',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'spend' => 'decimal:2',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'reach' => 'integer',
            'conversions' => 'decimal:2',
            'revenue' => 'decimal:2',
        ];
    }

    public function clientAdAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class);
    }
}
