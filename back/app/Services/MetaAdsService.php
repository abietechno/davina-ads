<?php

namespace App\Services;

use App\Models\ClientAdAccount;
use App\Models\DailyAdMetric;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Read-only Meta Marketing API client. Never call any endpoint that creates,
 * edits, pauses or deletes campaigns, ads or budgets from here.
 */
class MetaAdsService
{
    /** Meta error codes that mean "throttled / temporary, try again later". */
    private const RETRYABLE_ERROR_CODES = [1, 2, 4, 17, 32, 341, 613, 80000, 80003, 80004, 80014];

    /**
     * Wait (ms) before each retry: exponential backoff, max 3 retries. Kept short (13s total) so a sync
     * started from the dashboard stays under PHP's 30s limit; longer waits belong to the queue job's backoff.
     */
    private const RETRY_DELAYS_MS = [1000, 3000, 9000];

    /**
     * Conversion actions Meta leaves out of region-broken-down insights => the closest action it does return there
     * (30d Davina: 51 conversations started vs 52 messaging connections).
     */
    public const GEO_CONVERSION_FALLBACKS = [
        'onsite_conversion.messaging_conversation_started_7d' => 'onsite_conversion.total_messaging_connection',
    ];

    private function baseUrl(): string
    {
        return 'https://graph.facebook.com/' . config('services.meta.api_version');
    }

    /**
     * Token is sent as a Bearer header, never in the query string, so it can't leak into logs or paging URLs.
     */
    private function client(ClientAdAccount $account): PendingRequest
    {
        $token = config('services.meta.system_user_token') ?: $account->access_token;

        if (! $token) {
            throw new RuntimeException('META_SYSTEM_USER_TOKEN is not configured in .env.');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->timeout(60)
            ->retry(self::RETRY_DELAYS_MS, when: fn ($e) => $this->isRetryable($e), throw: false);
    }

    private function isRetryable(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            // SSL/certificate problems are server misconfiguration and won't fix themselves on retry
            return ! str_contains($e->getMessage(), 'SSL');
        }

        if ($e instanceof RequestException) {
            return $e->response->serverError()
                || in_array((int) $e->response->json('error.code'), self::RETRYABLE_ERROR_CODES, true);
        }

        return false;
    }

    private function errorMessage(Response $response): string
    {
        $code = $response->json('error.code');
        $message = $response->json('error.message', 'Unknown Meta API error');

        return $code ? "Meta API error {$code}: {$message}" : $message;
    }

    /**
     * Fields to request per level.
     */
    private function fieldsForLevel(string $level): string
    {
        $metrics = 'spend,impressions,clicks,reach,actions,action_values';

        return match ($level) {
            'campaign' => "campaign_id,campaign_name,{$metrics}",
            'adset'    => "campaign_id,campaign_name,adset_id,adset_name,{$metrics}",
            'ad'       => "campaign_id,campaign_name,adset_id,adset_name,ad_id,ad_name,{$metrics}",
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
            // Carbon (not a plain string) so the lookup matches how the `date` cast stores it on every DB driver
            'date' => Carbon::parse($row['date_start'])->startOfDay(),
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
    private function valuesForLevel(string $level, array $row, string $conversionType): array
    {
        $base = [
            'campaign_name' => $row['campaign_name'] ?? 'Unknown',
            'spend' => (float) ($row['spend'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'reach' => (int) ($row['reach'] ?? 0),
            'conversions' => $this->actionValue($row['actions'] ?? [], $conversionType),
            'revenue' => $this->actionValue($row['action_values'] ?? [], $conversionType),
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
     * Pick the value of one action_type out of Meta's actions / action_values list (0 when absent).
     */
    private function actionValue(array $actions, string $actionType): float
    {
        foreach ($actions as $action) {
            if (($action['action_type'] ?? null) === $actionType) {
                return (float) ($action['value'] ?? 0);
            }
        }

        return 0.0;
    }

    private function conversionType(ClientAdAccount $account): string
    {
        return $account->conversion_action_type ?: config('services.meta.default_conversion_action_type');
    }

    private function adAccountPath(ClientAdAccount $account): string
    {
        return 'act_' . preg_replace('/^act_/', '', $account->account_id);
    }

    /**
     * Ad accounts the token can read (used to discover / verify accounts).
     */
    public function listAdAccounts(ClientAdAccount $account): array
    {
        $response = $this->client($account)->get($this->baseUrl() . '/me/adaccounts', [
            'fields' => 'account_id,name,currency,timezone_name,account_status',
            'limit' => 200,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response));
        }

        return $response->json('data', []);
    }

    /**
     * Sync daily insights from Meta Ads API. Dates are in the ad account's own timezone.
     *
     * @param string $level  campaign|adset|ad
     */
    public function sync(ClientAdAccount $account, string $startDate, string $endDate, string $level = 'campaign'): array
    {
        $adAccountId = $this->adAccountPath($account);
        $conversionType = $this->conversionType($account);
        $results = ['synced' => 0, 'errors' => []];

        try {
            $client = $this->client($account);

            $response = $client->get($this->baseUrl() . "/{$adAccountId}/insights", [
                'level' => $level,
                'fields' => $this->fieldsForLevel($level),
                'time_range' => json_encode(['since' => $startDate, 'until' => $endDate]),
                'time_increment' => 1,
                'limit' => 500,
            ]);

            $page = 1;

            while (true) {
                if ($response->failed()) {
                    $error = $this->errorMessage($response);
                    Log::error('Meta Ads API error', [
                        'account_id' => $adAccountId,
                        'level' => $level,
                        'page' => $page,
                        'status' => $response->status(),
                        'error' => $error,
                    ]);
                    // A failed page means the data is incomplete: report it instead of silently stopping
                    $results['errors'][] = $page > 1 ? "Page {$page}: {$error}" : $error;
                    break;
                }

                $data = $response->json('data', []);
                $this->logActionTypesOnce($account, $data);
                $results['synced'] += $this->processPage($data, $account->id, $level, $conversionType);

                $nextUrl = $response->json('paging.next');
                if (! $nextUrl || empty($data)) {
                    break;
                }

                $page++;
                $response = $client->get($nextUrl);
            }
        } catch (\Exception $e) {
            Log::error('Meta Ads sync exception', [
                'account_id' => $adAccountId,
                'message' => $e->getMessage(),
            ]);
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Daily account delivery per audience location. Meta breaks down no deeper than region (province).
     *
     * @return list<array{date: string, country_code: ?string, region: ?string, city: null, spend: float, impressions: int, clicks: int, conversions: float}>
     */
    public function fetchGeo(ClientAdAccount $account, string $startDate, string $endDate): array
    {
        $conversionType = $this->conversionType($account);
        $conversionType = self::GEO_CONVERSION_FALLBACKS[$conversionType] ?? $conversionType;
        $client = $this->client($account);

        $response = $client->get($this->baseUrl() . '/' . $this->adAccountPath($account) . '/insights', [
            'level' => 'account',
            'breakdowns' => 'country,region',
            'fields' => 'spend,impressions,clicks,actions',
            'time_range' => json_encode(['since' => $startDate, 'until' => $endDate]),
            'time_increment' => 1,
            'limit' => 500,
        ]);

        $rows = [];

        while (true) {
            if ($response->failed()) {
                throw new RuntimeException($this->errorMessage($response));
            }

            $data = $response->json('data', []);
            foreach ($data as $row) {
                $country = strtoupper((string) ($row['country'] ?? ''));
                $region = $row['region'] ?? null;

                $rows[] = [
                    'date' => Carbon::parse($row['date_start'])->toDateString(),
                    'country_code' => preg_match('/^[A-Z]{2}$/', $country) ? $country : null,
                    'region' => $region && strcasecmp($region, 'Unknown') !== 0 ? $region : null,
                    'city' => null,
                    'spend' => (float) ($row['spend'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'conversions' => $this->actionValue($row['actions'] ?? [], $conversionType),
                ];
            }

            $nextUrl = $response->json('paging.next');
            if (! $nextUrl || empty($data)) {
                return $rows;
            }

            $response = $client->get($nextUrl);
        }
    }

    private function processPage(array $data, int $accountId, string $level, string $conversionType): int
    {
        return DB::transaction(function () use ($data, $accountId, $level, $conversionType) {
            foreach ($data as $row) {
                DailyAdMetric::updateOrCreate(
                    $this->uniqueKeyForLevel($level, $accountId, $row),
                    $this->valuesForLevel($level, $row, $conversionType),
                );
            }

            return count($data);
        });
    }

    /**
     * Log the distinct action_types seen for an account once, so the right
     * conversion_action_type (purchase, lead, messaging, ...) can be chosen per client.
     */
    private function logActionTypesOnce(ClientAdAccount $account, array $data): void
    {
        $types = collect($data)->pluck('actions')->flatten(1)->pluck('action_type')->filter()->unique()->sort()->values();

        if ($types->isNotEmpty() && Cache::add("meta_action_types_logged:{$account->id}", true, now()->addDays(30))) {
            Log::info('Meta action_types for account', [
                'client_ad_account_id' => $account->id,
                'action_types' => $types->all(),
            ]);
        }
    }
}
