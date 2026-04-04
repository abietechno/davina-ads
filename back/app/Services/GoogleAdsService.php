<?php

namespace App\Services;

use App\Models\ClientAdAccount;
use App\Models\DailyAdMetric;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const ADS_API_URL = 'https://googleads.googleapis.com/v17/customers';

    private function buildQuery(string $level, string $startDate, string $endDate): string
    {
        $fields = match ($level) {
            'campaign' => 'campaign.id, campaign.name',
            'adset'    => 'campaign.id, campaign.name, ad_group.id, ad_group.name',
            'ad'       => 'campaign.id, campaign.name, ad_group.id, ad_group.name, ad_group_ad.ad.id, ad_group_ad.ad.name',
        };

        $resource = match ($level) {
            'campaign' => 'campaign',
            'adset'    => 'ad_group',
            'ad'       => 'ad_group_ad',
        };

        $statusField = match ($level) {
            'campaign' => 'campaign.status',
            'adset'    => 'ad_group.status',
            'ad'       => 'ad_group_ad.status',
        };

        return "SELECT {$fields}, metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.reach, segments.date "
            . "FROM {$resource} "
            . "WHERE segments.date BETWEEN '{$startDate}' AND '{$endDate}' "
            . "AND {$statusField} != 'REMOVED' "
            . "ORDER BY segments.date ASC";
    }

    public function sync(ClientAdAccount $account, string $startDate, string $endDate, string $level = 'campaign'): array
    {
        $results = ['synced' => 0, 'errors' => []];

        $clientId = config('services.google_ads.client_id');
        $clientSecret = config('services.google_ads.client_secret');
        $developerToken = config('services.google_ads.developer_token');
        $refreshToken = $account->api_key_or_refresh_token;

        if (! $clientId || ! $clientSecret || ! $developerToken) {
            $results['errors'][] = 'Google Ads API credentials not configured in .env';
            return $results;
        }

        if (! $refreshToken) {
            $results['errors'][] = 'No refresh token found for this account.';
            return $results;
        }

        // Exchange refresh token
        $tokenResponse = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($tokenResponse->failed()) {
            $results['errors'][] = $tokenResponse->json('error_description', 'Failed to obtain access token');
            return $results;
        }

        $accessToken = $tokenResponse->json('access_token');
        $customerId = str_replace('-', '', $account->account_id);

        try {
            $query = $this->buildQuery($level, $startDate, $endDate);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'developer-token' => $developerToken,
            ])->post(self::ADS_API_URL . "/{$customerId}/googleAds:searchStream", [
                'query' => $query,
            ]);

            if ($response->failed()) {
                $error = $response->json('error.message', 'Google Ads API query failed');
                Log::error('Google Ads API error', ['customer_id' => $customerId, 'error' => $error]);
                $results['errors'][] = $error;
                return $results;
            }

            foreach ($response->json() as $batch) {
                foreach ($batch['results'] ?? [] as $row) {
                    $date = $row['segments']['date'] ?? null;
                    $campaignId = $row['campaign']['id'] ?? null;
                    if (! $date || ! $campaignId) continue;

                    $spend = ($row['metrics']['costMicros'] ?? 0) / 1_000_000;

                    $uniqueKey = [
                        'client_ad_account_id' => $account->id,
                        'level' => $level,
                        'date' => $date,
                        'campaign_id' => $campaignId,
                    ];

                    $values = [
                        'campaign_name' => $row['campaign']['name'] ?? 'Unknown',
                        'spend' => $spend,
                        'impressions' => (int) ($row['metrics']['impressions'] ?? 0),
                        'clicks' => (int) ($row['metrics']['clicks'] ?? 0),
                        'reach' => (int) ($row['metrics']['reach'] ?? 0),
                    ];

                    if (\in_array($level, ['adset', 'ad'])) {
                        $uniqueKey['adset_id'] = $row['adGroup']['id'] ?? null;
                        $values['adset_name'] = $row['adGroup']['name'] ?? null;
                    }

                    if ($level === 'ad') {
                        $uniqueKey['ad_id'] = $row['adGroupAd']['ad']['id'] ?? null;
                        $values['ad_name'] = $row['adGroupAd']['ad']['name'] ?? null;
                    }

                    DailyAdMetric::updateOrCreate($uniqueKey, $values);
                    $results['synced']++;
                }
            }

            $account->update(['last_synced_at' => now()]);

        } catch (\Exception $e) {
            Log::error('Google Ads sync exception', ['message' => $e->getMessage()]);
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }
}
