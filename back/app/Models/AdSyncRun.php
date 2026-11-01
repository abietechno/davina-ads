<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSyncRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'client_ad_account_id',
        'platform',
        'date_from',
        'date_to',
        'status',
        'rows_upserted',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'rows_upserted' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function clientAdAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class);
    }
}
