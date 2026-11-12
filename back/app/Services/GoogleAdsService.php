<?php

namespace App\Services;

use App\Models\AdPlatformConnection;
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
 * Read-only Google Ads API client over REST (no grpc needed). Only googleAds:searchStream
 * is called; never mutate campaigns, ads or budgets from here.
 */
class GoogleAdsService
{
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Wait (ms) before each retry: exponential backoff, max 3 retries. Kept short (13s total) so a sync
     * started from the dashboard stays under PHP's 30s limit; longer waits belong to the queue job's backoff.
     */
    private const RETRY_DELAYS_MS = [1000, 3000, 9000];

    /** Errors that are not code bugs but account/access setup issues Abie has to fix. */
    private const SETUP_HINTS = [
        'CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION' => 'Apply for Explorer access on the Google Ads API overview page of the OAuth client\'s Google Cloud project.',
        'DEVELOPER_TOKEN_NOT_APPROVED' => 'Apply for Explorer access on the Google Ads API overview page of the OAuth client\'s Google Cloud project.',
        'USER_PERMISSION_DENIED' => 'The connected Google login has no access to this customer (check the MCC link / login-customer-id).',
        'CUSTOMER_NOT_ENABLED' => 'This Google Ads account is not enabled (cancelled or not yet set up).',
    ];

    private function apiUrl(): string
    {
        return 'https://googleads.googleapis.com/' . config('services.google_ads.api_version') . '/customers';
    }

    private static function digits(?string $id): string
    {
        return preg_replace('/\D/', '', (string) $id);
    }

    private function refreshToken(?ClientAdAccount $account): ?string
    {
        return $account?->api_key_or_refresh_token
            ?: AdPlatformConnection::where('platform', 'google')->first()?->refresh_token
            ?: config('services.google_ads.refresh_token');
    }

    /**
     * Access tokens live ~60 minutes; cache for 50 so every hourly sync doesn't hit the OAuth endpoint.
     */
    private function accessToken(string $refreshToken): string
    {
        return Cache::remember('google_ads_access_token:' . sha1($refreshToken), now()->addMinutes(50), function () use ($refreshToken) {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => config('services.google_ads.client_id'),
                'client_secret' => config('services.google_ads.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->failed() || ! $response->json('access_token')) {
                $reason = $response->json('error_description') ?: $response->json('error', 'Failed to obtain access token');
                throw new RuntimeException("Google OAuth: {$reason}. Reconnect Google Ads in Settings.");
            }

            return $response->json('access_token');
        });
    }

    public static function forgetAccessToken(string $refreshToken): void
    {
        Cache::forget('google_ads_access_token:' . sha1($refreshToken));
    }

    /**
     * API access level belongs to the Google Cloud project that owns the OAuth client (since Sept 2026).
     * The developer-token header is ignored by the API and will be rejected by a future version, so it isn't sent.
     */
    private function client(?ClientAdAccount $account = null): PendingRequest
    {
        foreach (['client_id', 'client_secret'] as $key) {
            if (! config("services.google_ads.{$key}")) {
                throw new RuntimeException('Google Ads API credentials not configured in .env (GOOGLE_ADS_' . strtoupper($key) . ').');
            }
        }

        $refreshToken = $this->refreshToken($account);
        if (! $refreshToken) {
            throw new RuntimeException('Google Ads is not connected yet. Use Settings → Connect Google Ads.');
        }

        $headers = [];
        if ($mccId = self::digits(config('services.google_ads.mcc_id'))) {
            $headers['login-customer-id'] = $mccId;
        }

        return Http::withToken($this->accessToken($refreshToken))
            ->withHeaders($headers)
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

        return $e instanceof RequestException
            && ($e->response->serverError() || $e->response->status() === 429);
    }

    /**
     * searchStream errors come back either as {error:{...}} or [{error:{...}}].
     */
    private function errorMessage(Response $response): string
    {
        $error = $response->json('error') ?? $response->json('0.error') ?? [];
        $detail = $error['details'][0]['errors'][0] ?? [];
        $errorCode = collect($detail['errorCode'] ?? [])->first();

        $message = $detail['message'] ?? $error['message'] ?? substr($response->body(), 0, 300);
        if ($errorCode) {
            $message = "{$errorCode}: {$message}";
        }

        return isset(self::SETUP_HINTS[$errorCode]) ? $message . ' — ' . self::SETUP_HINTS[$errorCode] : $message;
    }

    /**
     * Run a GAQL query and return all result rows (searchStream returns them in batches).
     */
    private function search(PendingRequest $client, string $customerId, string $query): array
    {
        $response = $client->post($this->apiUrl() . '/' . self::digits($customerId) . '/googleAds:searchStream', [
            'query' => $query,
        ]);

        if ($response->failed()) {
            $error = $this->errorMessage($response);
            Log::error('Google Ads API error', [
                'customer_id' => self::digits($customerId),
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new RuntimeException($error);
        }

        return collect($response->json() ?? [])->pluck('results')->filter()->flatten(1)->all();
    }

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

        // Re-format dates so nothing but YYYY-MM-DD can ever reach the GAQL string
        $from = Carbon::parse($startDate)->toDateString();
        $to = Carbon::parse($endDate)->toDateString();

        return "SELECT {$fields}, metrics.cost_micros, metrics.impressions, metrics.clicks, "
            . "metrics.conversions, metrics.conversions_value, segments.date "
            . "FROM {$resource} "
            . "WHERE segments.date BETWEEN '{$from}' AND '{$to}' "
            . "AND campaign.status != 'REMOVED' "
            . "ORDER BY segments.date ASC";
    }

    /**
     * Client (non-manager) accounts under the MCC, for discovering / verifying accounts.
     */
    public function listClientAccounts(): array
    {
        $mccId = self::digits(config('services.google_ads.mcc_id'));
        if (! $mccId) {
            throw new RuntimeException('GOOGLE_ADS_MCC_ID is not configured in .env.');
        }

        $rows = $this->search($this->client(), $mccId,
            'SELECT customer_client.id, customer_client.descriptive_name, customer_client.currency_code, '
            . 'customer_client.time_zone, customer_client.manager FROM customer_client WHERE customer_client.level <= 1'
        );

        return collect($rows)
            ->pluck('customerClient')
            ->reject(fn ($c) => $c['manager'] ?? false)
            ->map(fn ($c) => [
                'id' => (string) $c['id'],
                'name' => $c['descriptiveName'] ?? null,
                'currency' => $c['currencyCode'] ?? null,
                'timezone' => $c['timeZone'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Fill currency / timezone from the account itself the first time, so date ranges use account time.
     */
    private function ensureAccountSettings(PendingRequest $client, ClientAdAccount $account): void
    {
        if ($account->timezone && $account->currency) {
            return;
        }

        $customer = $this->search($client, $account->account_id, 'SELECT customer.currency_code, customer.time_zone FROM customer')[0]['customer'] ?? [];

        $account->update(array_filter([
            'currency' => $account->currency ?: ($customer['currencyCode'] ?? null),
            'timezone' => $account->timezone ?: ($customer['timeZone'] ?? null),
        ]));
    }

    public function sync(ClientAdAccount $account, string $startDate, string $endDate, string $level = 'campaign'): array
    {
        $results = ['synced' => 0, 'errors' => []];

        try {
            $client = $this->client($account);
            $this->ensureAccountSettings($client, $account);

            $rows = $this->search($client, $account->account_id, $this->buildQuery($level, $startDate, $endDate));

            $results['synced'] = DB::transaction(fn () => $this->storeRows($rows, $account->id, $level));
        } catch (\Exception $e) {
            Log::error('Google Ads sync exception', [
                'client_ad_account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Daily account delivery per audience physical location (user_location_view, so not
     * "area of interest"). Google leaves part of the spend without any location.
     *
     * @return list<array{date: string, country_code: ?string, region: ?string, city: ?string, spend: float, impressions: int, clicks: int, conversions: float}>
     */
    public function fetchGeo(ClientAdAccount $account, string $startDate, string $endDate): array
    {
        $client = $this->client($account);
        $from = Carbon::parse($startDate)->toDateString();
        $to = Carbon::parse($endDate)->toDateString();

        $rows = $this->search($client, $account->account_id,
            'SELECT user_location_view.country_criterion_id, segments.geo_target_most_specific_location, segments.date, '
            . 'metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions '
            . "FROM user_location_view WHERE segments.date BETWEEN '{$from}' AND '{$to}'"
        );

        // Most specific location when Google knows it, otherwise at least the country
        $locationOf = fn (array $row) => $row['segments']['geoTargetMostSpecificLocation']
            ?? (isset($row['userLocationView']['countryCriterionId']) ? 'geoTargetConstants/' . $row['userLocationView']['countryCriterionId'] : null);

        $places = $this->geoTargets($client, $account->account_id, collect($rows)->map($locationOf)->filter()->unique()->values()->all());

        return collect($rows)->map(function (array $row) use ($locationOf, $places) {
            $place = $places[$locationOf($row)] ?? [];

            return [
                'date' => $row['segments']['date'],
                'country_code' => $place['country_code'] ?? null,
                'region' => $place['region'] ?? null,
                'city' => $place['city'] ?? null,
                'spend' => ((int) ($row['metrics']['costMicros'] ?? 0)) / 1_000_000,
                'impressions' => (int) ($row['metrics']['impressions'] ?? 0),
                'clicks' => (int) ($row['metrics']['clicks'] ?? 0),
                'conversions' => (float) ($row['metrics']['conversions'] ?? 0),
            ];
        })->all();
    }

    /**
     * geo_target_constant resource name => country code, region and city, parsed from its canonical
     * name ("Kuta,Kuta,Bali,Indonesia": most specific first, country last). Cached forever: a
     * constant's ID never changes meaning.
     *
     * @param  list<string>  $resourceNames
     * @return array<string, array{country_code: ?string, region: ?string, city: ?string}>
     */
    private function geoTargets(PendingRequest $client, string $customerId, array $resourceNames): array
    {
        $places = [];
        $missing = [];

        foreach ($resourceNames as $name) {
            // Only API-shaped names reach the GAQL string below
            if (! preg_match('#^geoTargetConstants/\d+$#', $name)) continue;

            $cached = Cache::get("google_geo_target:{$name}");
            $cached ? $places[$name] = $cached : $missing[] = $name;
        }

        foreach (array_chunk($missing, 500) as $chunk) {
            $in = implode(',', array_map(fn ($name) => "'{$name}'", $chunk));
            $rows = $this->search($client, $customerId,
                'SELECT geo_target_constant.resource_name, geo_target_constant.canonical_name, geo_target_constant.country_code '
                . "FROM geo_target_constant WHERE geo_target_constant.resource_name IN ({$in})"
            );

            foreach ($rows as $row) {
                $target = $row['geoTargetConstant'];
                $parts = array_map('trim', explode(',', $target['canonicalName'] ?? ''));
                $count = count($parts);

                $place = [
                    'country_code' => $target['countryCode'] ?? null,
                    'region' => $count >= 2 ? $parts[$count - 2] : null,
                    'city' => $count >= 3 ? $parts[0] : null,
                ];

                Cache::forever("google_geo_target:{$target['resourceName']}", $place);
                $places[$target['resourceName']] = $place;
            }
        }

        return $places;
    }

    private function storeRows(array $rows, int $accountId, string $level): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $date = $row['segments']['date'] ?? null;
            $campaignId = $row['campaign']['id'] ?? null;
            if (! $date || ! $campaignId) continue;

            $uniqueKey = [
                'client_ad_account_id' => $accountId,
                'level' => $level,
                // Carbon (not a plain string) so the lookup matches how the `date` cast stores it on every DB driver
                'date' => Carbon::parse($date)->startOfDay(),
                'campaign_id' => (string) $campaignId,
            ];

            $values = [
                'campaign_name' => $row['campaign']['name'] ?? 'Unknown',
                'spend' => ((int) ($row['metrics']['costMicros'] ?? 0)) / 1_000_000,
                'impressions' => (int) ($row['metrics']['impressions'] ?? 0),
                'clicks' => (int) ($row['metrics']['clicks'] ?? 0),
                'conversions' => (float) ($row['metrics']['conversions'] ?? 0),
                'revenue' => (float) ($row['metrics']['conversionsValue'] ?? 0),
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
            $count++;
        }

        return $count;
    }
}
