<?php

namespace App\Services\Ads;

use App\Models\AdGeoMetric;
use App\Models\AdSyncRun;
use App\Models\ClientAdAccount;
use App\Services\GoogleAdsService;
use App\Services\MetaAdsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Entry point for every sync (button, artisan command, scheduler): picks the
 * platform client, logs the run in ad_sync_runs and stamps last_synced_at on success.
 */
class AdSyncService
{
    private const DEFAULT_TIMEZONE = 'Asia/Jakarta';

    public function __construct(
        private MetaAdsService $meta,
        private GoogleAdsService $google,
    ) {}

    /**
     * Last $days days through today, as dates in the ad account's own timezone
     * (Meta/Google report "a day" in account time, not server time).
     *
     * @return array{0: string, 1: string}
     */
    public function rangeForDays(ClientAdAccount $account, int $days): array
    {
        $today = CarbonImmutable::now($account->timezone ?: self::DEFAULT_TIMEZONE);

        return [$today->subDays($days)->toDateString(), $today->toDateString()];
    }

    public function syncLastDays(ClientAdAccount $account, int $days, string $level = 'campaign'): array
    {
        [$from, $to] = $this->rangeForDays($account, $days);

        return $this->sync($account, $from, $to, $level);
    }

    public function sync(ClientAdAccount $account, string $from, string $to, string $level = 'campaign'): array
    {
        // A run still "running" long after it started was killed (PHP timeout / crash) and will never finish itself
        AdSyncRun::where('client_ad_account_id', $account->id)
            ->where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(15))
            ->update(['status' => 'failed', 'error_message' => 'Interrupted (timeout or crash).', 'finished_at' => now()]);

        $run = AdSyncRun::create([
            'client_ad_account_id' => $account->id,
            'platform' => $account->platform,
            'date_from' => $from,
            'date_to' => $to,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = match ($account->platform) {
                'meta' => $this->meta->sync($account, $from, $to, $level),
                'google' => $this->google->sync($account, $from, $to, $level),
            };
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'finished_at' => now()]);
            throw $e;
        }

        // Location data is account-wide, so it rides along with the campaign level only (not 3x per --level=all).
        // Skipped when the main sync failed: same API, same problem, and the error would show twice.
        if ($level === 'campaign' && empty($result['errors'])) {
            $result['errors'] = array_merge($result['errors'], $this->syncGeo($account, $from, $to));
        }

        $failed = ! empty($result['errors']);

        $run->update([
            'status' => $failed ? 'failed' : 'success',
            'rows_upserted' => $result['synced'],
            'error_message' => $failed ? implode("\n", $result['errors']) : null,
            'finished_at' => now(),
        ]);

        if (! $failed) {
            $account->update(['last_synced_at' => now()]);
        }

        return $result;
    }

    /**
     * Replace (not upsert) the range's location rows: a place that dropped to zero must disappear.
     *
     * @return list<string> errors
     */
    private function syncGeo(ClientAdAccount $account, string $from, string $to): array
    {
        try {
            $rows = match ($account->platform) {
                'meta' => $this->meta->fetchGeo($account, $from, $to),
                'google' => $this->google->fetchGeo($account, $from, $to),
            };
        } catch (\Throwable $e) {
            Log::error('Ad location sync failed', ['client_ad_account_id' => $account->id, 'message' => $e->getMessage()]);

            return ['Location data: ' . $e->getMessage()];
        }

        // Several API rows can land on the same day and place (e.g. Google splits targeted / not targeted)
        $merged = [];
        foreach ($rows as $row) {
            $key = implode('|', [$row['date'], $row['country_code'], $row['region'], $row['city']]);

            if (! isset($merged[$key])) {
                $merged[$key] = $row;
                continue;
            }

            foreach (['spend', 'impressions', 'clicks', 'conversions'] as $metric) {
                $merged[$key][$metric] += $row[$metric];
            }
        }

        DB::transaction(function () use ($account, $from, $to, $merged) {
            AdGeoMetric::where('client_ad_account_id', $account->id)
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->delete();

            $now = now();
            foreach (array_chunk(array_values($merged), 500) as $chunk) {
                AdGeoMetric::insert(array_map(
                    fn (array $row) => $row + ['client_ad_account_id' => $account->id, 'created_at' => $now, 'updated_at' => $now],
                    $chunk,
                ));
            }
        });

        return [];
    }
}
