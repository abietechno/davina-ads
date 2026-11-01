<?php

namespace Tests\Feature;

use App\Models\AdPlatformConnection;
use App\Models\AdSyncRun;
use App\Models\ClientAdAccount;
use App\Models\DailyAdMetric;
use App\Services\Ads\AdSyncService;
use App\Services\GoogleAdsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class GoogleSyncTest extends TestCase
{
    use RefreshDatabase;

    private const STREAM_URL = 'https://googleads.googleapis.com/v26/customers/7045651001/googleAds:searchStream';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_ads.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_ads.client_secret' => 'client-secret',
            'services.google_ads.mcc_id' => '804-583-9792',
            'services.google_ads.api_version' => 'v26',
            'services.google_ads.refresh_token' => null,
        ]);
        Sleep::fake();

        AdPlatformConnection::create(['platform' => 'google', 'refresh_token' => 'refresh-from-button', 'email' => 'abie@example.com']);
    }

    private function account(): ClientAdAccount
    {
        return ClientAdAccount::create([
            'platform' => 'google',
            'account_name' => 'Google Ads Davina',
            'account_id' => '704-565-1001',
        ]);
    }

    private function row(string $date, string $campaignId, int $costMicros, float $conversions): array
    {
        return [
            'campaign' => ['id' => $campaignId, 'name' => "Search {$campaignId}"],
            'metrics' => [
                'costMicros' => (string) $costMicros,
                'impressions' => '1200',
                'clicks' => '80',
                'conversions' => $conversions,
                'conversionsValue' => 0,
            ],
            'segments' => ['date' => $date],
        ];
    }

    private function fakeGoogle(array $streamBatches): void
    {
        Http::fake(function (Request $request) use ($streamBatches) {
            if ($request->url() === GoogleAdsService::TOKEN_URL) {
                return Http::response(['access_token' => 'ya29.access', 'expires_in' => 3599]);
            }

            if (str_ends_with($request['query'] ?? '', 'FROM customer')) {
                return Http::response([['results' => [['customer' => ['currencyCode' => 'IDR', 'timeZone' => 'Asia/Jakarta']]]]]);
            }

            return Http::response($streamBatches);
        });
    }

    public function test_sync_converts_cost_micros_and_stores_conversions(): void
    {
        // searchStream returns results split over several batches
        $this->fakeGoogle([
            ['results' => [$this->row('2026-10-25', '111', 150_500_000, 3.0)]],
            ['results' => [$this->row('2026-10-26', '111', 99_000_000, 1.5)]],
        ]);
        $account = $this->account();

        $result = app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-26');

        $this->assertSame(['synced' => 2, 'errors' => []], $result);
        $row = DailyAdMetric::whereDate('date', '2026-10-25')->sole();
        $this->assertEquals(150.50, $row->spend);
        $this->assertEquals(3.0, $row->conversions);
        $this->assertEquals(1.5, DailyAdMetric::whereDate('date', '2026-10-26')->value('conversions'));

        // Timezone/currency are filled from the account itself
        $this->assertSame(['IDR', 'Asia/Jakarta'], [$account->fresh()->currency, $account->fresh()->timezone]);
        $this->assertSame('success', AdSyncRun::sole()->status);

        Http::assertSent(fn (Request $request) => $request->url() === self::STREAM_URL
            && $request->hasHeader('Authorization', 'Bearer ya29.access')
            // Deprecated by Google (access is per Cloud project now) and rejected by future API versions
            && ! $request->hasHeader('developer-token')
            && $request->hasHeader('login-customer-id', '8045839792')
            && str_contains($request['query'], "segments.date BETWEEN '2026-10-25' AND '2026-10-26'")
            && str_contains($request['query'], 'metrics.conversions'));
        Http::assertSent(fn (Request $request) => $request->url() === GoogleAdsService::TOKEN_URL
            && $request['refresh_token'] === 'refresh-from-button');
    }

    public function test_access_token_is_cached_between_syncs_and_rows_are_not_duplicated(): void
    {
        $this->fakeGoogle([['results' => [$this->row('2026-10-25', '111', 1_000_000, 1)]]]);
        $account = $this->account();

        app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-25');
        app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-25');

        $this->assertSame(1, DailyAdMetric::count());
        Http::assertSentCount(4); // 1 token + 1 customer lookup + 2 searchStream
    }

    public function test_cloud_project_access_error_is_reported_with_a_hint(): void
    {
        Http::fake([
            GoogleAdsService::TOKEN_URL => Http::response(['access_token' => 'ya29.access']),
            'googleads.googleapis.com/*' => Http::response([[
                'error' => [
                    'code' => 403,
                    'message' => 'The caller does not have permission',
                    'details' => [['errors' => [[
                        'errorCode' => ['authorizationError' => 'CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION'],
                        'message' => 'The Google Cloud project is only approved for use with test accounts.',
                    ]]]],
                ],
            ]], 403),
        ]);
        $account = $this->account();

        $result = app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-25');

        $this->assertStringStartsWith('CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION: The Google Cloud project is only approved', $result['errors'][0]);
        $this->assertStringContainsString('Explorer access', $result['errors'][0]);
        $this->assertSame('failed', AdSyncRun::sole()->status);
        $this->assertNull($account->fresh()->last_synced_at);
    }

    public function test_sync_fails_clearly_when_google_is_not_connected(): void
    {
        AdPlatformConnection::query()->delete();
        Http::fake();

        $result = app(AdSyncService::class)->sync($this->account(), '2026-10-25', '2026-10-25');

        $this->assertSame(['Google Ads is not connected yet. Use Settings → Connect Google Ads.'], $result['errors']);
        Http::assertNothingSent();
    }
}
