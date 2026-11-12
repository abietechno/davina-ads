<?php

namespace Tests\Feature;

use App\Models\AdSyncRun;
use App\Models\ClientAdAccount;
use App\Models\DailyAdMetric;
use App\Services\Ads\AdSyncService;
use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MetaSyncTest extends TestCase
{
    use RefreshDatabase;

    private const WA = 'onsite_conversion.messaging_conversation_started_7d';
    private const INSIGHTS_URL = 'https://graph.facebook.com/v26.0/act_1501772011366761/insights*';
    private const PAGE_2_URL = 'https://graph.facebook.com/v26.0/act_1501772011366761/insights?after=CURSOR2';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta.api_version' => 'v26.0',
            'services.meta.system_user_token' => 'system-user-token',
        ]);
        Sleep::fake();
    }

    private function account(): ClientAdAccount
    {
        return ClientAdAccount::create([
            'platform' => 'meta',
            'account_name' => 'Davina Ads Group',
            'account_id' => 'act_1501772011366761',
            'access_token' => 'old-expired-user-token',
            'timezone' => 'Asia/Makassar',
            'conversion_action_type' => self::WA,
        ]);
    }

    private function row(string $date, string $campaignId, array $overrides = []): array
    {
        return $overrides + [
            'campaign_id' => $campaignId,
            'campaign_name' => "Campaign {$campaignId}",
            'spend' => '413334.50',
            'impressions' => '27875',
            'clicks' => '234',
            'reach' => '20000',
            'actions' => [
                ['action_type' => 'link_click', 'value' => '141'],
                ['action_type' => self::WA, 'value' => '9'],
            ],
            'date_start' => $date,
            'date_stop' => $date,
        ];
    }

    private function fakeTwoPages(): void
    {
        Http::fake([
            self::PAGE_2_URL => Http::response(['data' => [$this->row('2026-10-26', '1')]]),
            self::INSIGHTS_URL => Http::response([
                'data' => [$this->row('2026-10-25', '1'), $this->row('2026-10-25', '2', ['actions' => [['action_type' => 'link_click', 'value' => '5']]])],
                'paging' => ['next' => self::PAGE_2_URL],
            ]),
        ]);
    }

    public function test_sync_follows_paging_and_extracts_whatsapp_conversations(): void
    {
        $this->fakeTwoPages();
        $account = $this->account();

        $result = app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-26');

        $this->assertSame(['synced' => 3, 'errors' => []], $result);
        $this->assertSame(3, DailyAdMetric::count());
        $this->assertEquals(9, DailyAdMetric::where('campaign_id', '1')->whereDate('date', '2026-10-25')->value('conversions'));
        // Campaign without the configured action_type → 0 conversions, not an error
        $this->assertEquals(0, DailyAdMetric::where('campaign_id', '2')->value('conversions'));

        $run = AdSyncRun::sole();
        $this->assertSame('success', $run->status);
        $this->assertSame(3, $run->rows_upserted);
        $this->assertNotNull($account->fresh()->last_synced_at);

        // Token goes in the Authorization header (System User token wins over the stored one), never in the URL
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer system-user-token')
            && ! str_contains($request->url(), 'access_token'));
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://graph.facebook.com/v26.0/')
            && ($request['time_range'] ?? null) === '{"since":"2026-10-25","until":"2026-10-26"}');
    }

    public function test_running_sync_twice_does_not_duplicate_rows(): void
    {
        $this->fakeTwoPages();
        $account = $this->account();

        app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-26');
        app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-26');

        $this->assertSame(3, DailyAdMetric::count());
    }

    public function test_rate_limit_is_retried_with_backoff(): void
    {
        Http::fake([
            self::INSIGHTS_URL => Http::sequence()
                ->push(['error' => ['code' => 17, 'message' => 'User request limit reached']], 400)
                ->push(['data' => [$this->row('2026-10-25', '1')]])
                ->push(['data' => []]), // location breakdown
        ]);

        $result = app(AdSyncService::class)->sync($this->account(), '2026-10-25', '2026-10-25');

        $this->assertSame(['synced' => 1, 'errors' => []], $result);
        Http::assertSentCount(3);
        Sleep::assertSleptTimes(1);
    }

    public function test_failed_page_marks_run_failed_instead_of_silently_stopping(): void
    {
        Http::fake([
            self::PAGE_2_URL => Http::response(['error' => ['code' => 100, 'message' => 'Invalid parameter']], 400),
            self::INSIGHTS_URL => Http::response([
                'data' => [$this->row('2026-10-25', '1')],
                'paging' => ['next' => self::PAGE_2_URL],
            ]),
        ]);
        $account = $this->account();

        $result = app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-26');

        $this->assertSame(1, $result['synced']);
        $this->assertSame(['Page 2: Meta API error 100: Invalid parameter'], $result['errors']);
        $this->assertSame('failed', AdSyncRun::sole()->status);
        $this->assertNull($account->fresh()->last_synced_at);
    }

    public function test_ssl_errors_are_not_retried_and_stale_runs_are_closed(): void
    {
        $account = $this->account();
        $stale = AdSyncRun::create([
            'client_ad_account_id' => $account->id, 'platform' => 'meta', 'date_from' => '2026-10-01', 'date_to' => '2026-10-01',
            'status' => 'running', 'started_at' => now()->subHour(),
        ]);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 60: SSL certificate OpenSSL verify result: unable to get local issuer certificate'));

        $result = app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-25');

        $this->assertStringContainsString('SSL certificate', $result['errors'][0]);
        Sleep::assertNeverSlept();
        $this->assertSame('failed', $stale->fresh()->status);
    }

    public function test_default_range_uses_account_timezone(): void
    {
        // 23:30 UTC on Oct 31 is already Nov 1 in Makassar (UTC+8)
        $this->travelTo(\Carbon\Carbon::parse('2026-10-31 23:30:00', 'UTC'));

        [$from, $to] = app(AdSyncService::class)->rangeForDays($this->account(), 7);

        $this->assertSame(['2026-10-25', '2026-11-01'], [$from, $to]);
    }
}
