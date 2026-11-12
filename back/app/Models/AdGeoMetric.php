<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdGeoMetric extends Model
{
    protected $fillable = [
        'client_ad_account_id',
        'date',
        'country_code',
        'region',
        'city',
        'spend',
        'impressions',
        'clicks',
        'conversions',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'spend' => 'decimal:2',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'conversions' => 'decimal:2',
        ];
    }

    public function clientAdAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class);
    }
}
