<?php

namespace App\Services;

use App\Models\ClientAdAccount;
use App\Models\DailyAdMetric;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaAdsService
{
    private const BASE_URL = 'https://graph.facebook.com/v21.0';

    /**
     * Fields to request per level.
     */
    private function fieldsForLevel(string $level): string
    {
        return match ($level) {
            'campaign' => 'campaign_id,campaign_name,spend,impressions,clicks,reach',
            'adset'    => 'campaign_id,campaign_name,adset_id,adset_name,spend,impressions,clicks,reach',
            'ad'       => 'campaign_id,campaign_name,adset_id,adset_name,ad_id,ad_name,spend,impressions,clicks,reach',
        };
    }

    /**
     * Unique key columns for updateOrCreate per level.
     */
    private function uniqueKeyForLevel(string $level, int $accountId, array $row): array
    {
        $base = [
            'client_ad_account_id' => $accountId,
            'level' => $level,
            'date' => $row['date_start'],
        ];

        return match ($level) {
            'campaign' => $base + ['campaign_id' => $row['campaign_id']],
            'adset'    => $base + ['campaign_id' => $row['campaign_id'], 'adset_id' => $row['adset_id']],
            'ad'       => $base + ['campaign_id' => $row['campaign_id'], 'adset_id' => $row['adset_id'], 'ad_id' => $row['ad_id']],
        };
    }

    /**
     * Values to upsert per level.
     */
    private function valuesForLevel(string $level, array $row): array
    {
        $base = [
            'campaign_name' => $row['campaign_name'] ?? 'Unknown',
            'spend' => (float) ($row['spend'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'reach' => (int) ($row['reach'] ?? 0),
        ];

        if (in_array($level, ['adset', 'ad'])) {
            $base['adset_name'] = $row['adset_name'] ?? null;
        }

        if ($level === 'ad') {
            $base['ad_name'] = $row['ad_name'] ?? null;
        }

        return $base;
    }

    /**
     * Sync insights from Meta Ads API.
     *
     * @param string $level  campaign|adset|ad
     */
    public function sync(ClientAdAccount $account, string $startDate, string $endDate, string $level = 'campaign'): array
    {
        $token = $account->access_token;
        $adAccountId = $account->account_id;

        if (! str_starts_with($adAccountId, 'act_')) {
            $adAccountId = 'act_' . $adAccountId;
        }

        $results = ['synced' => 0, 'errors' => []];

        try {
            $response = Http::get(self::BASE_URL . "/{$adAccountId}/insights", [
                'access_token' => $token,
                'level' => $level,
                'fields' => $this->fieldsForLevel($level),
                'time_range' => json_encode([
                    'since' => $startDate,
                    'until' => $endDate,
                ]),
                'time_increment' => 1,
                'limit' => 500,
            ]);

            if ($response->failed()) {
                $error = $response->json('error.message', 'Unknown Meta API error');
                Log::error('Meta Ads API error', [
                    'account_id' => $adAccountId,
                    'level' => $level,
                    'error' => $error,
                ]);
                $results['errors'][] = $error;
                return $results;
            }

            $results['synced'] += $this->processPage($response->json('data', []), $account->id, $level);

            // Pagination
            $nextUrl = $response->json('paging.next');
            while ($nextUrl) {
                $pageResponse = Http::get($nextUrl);
                if ($pageResponse->failed()) break;

                $pageData = $pageResponse->json('data', []);
                if (empty($pageData)) break;

                $results['synced'] += $this->processPage($pageData, $account->id, $level);
                $nextUrl = $pageResponse->json('paging.next');
            }

            $account->update(['last_synced_at' => now()]);

        } catch (\Exception $e) {
            Log::error('Meta Ads sync exception', [
                'account_id' => $adAccountId,
                'message' => $e->getMessage(),
            ]);
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    private function processPage(array $data, int $accountId, string $level): int
    {
        $count = 0;

        foreach ($data as $row) {
            DailyAdMetric::updateOrCreate(
                $this->uniqueKeyForLevel($level, $accountId, $row),
                $this->valuesForLevel($level, $row),
            );
            $count++;
        }

        return $count;
    }
}
