<?php

namespace Tests\Feature;

use App\Models\ClientAdAccount;
use App\Models\DailyAdMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdsStatsTest extends TestCase
{
    use RefreshDatabase;

    private function metaAccount(): ClientAdAccount
    {
        return ClientAdAccount::create([
            'platform' => 'meta',
            'account_name' => 'Davina Ads Group',
            'account_id' => '1501772011366761',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'conversion_action_type' => 'onsite_conversion.messaging_conversation_started_7d',
        ]);
    }

    private function metric(ClientAdAccount $account, string $date, array $values): void
    {
        DailyAdMetric::create($values + [
            'client_ad_account_id' => $account->id,
            'level' => 'campaign',
            'date' => $date,
            'campaign_id' => '1',
            'campaign_name' => 'Campaign WA',
        ]);
    }

    public function test_stats_include_conversions_and_derived_metrics(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user', 'status' => 'approved']));
        $account = $this->metaAccount();

        $this->metric($account, '2026-10-25', ['spend' => 400000, 'impressions' => 30000, 'clicks' => 250, 'conversions' => 10]);
        $this->metric($account, '2026-10-26', ['spend' => 426669, 'impressions' => 25750, 'clicks' => 219, 'conversions' => 9]);
        // Outside the range: must not be counted
        $this->metric($account, '2026-11-01', ['spend' => 999999, 'impressions' => 1, 'clicks' => 1, 'conversions' => 1]);

        $response = $this->getJson('/api/ads/stats?' . http_build_query([
            'client_ad_account_id' => $account->id,
            'start_date' => '2026-10-25',
            'end_date' => '2026-10-31',
        ]));

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                // Existing fields consumed by the Vue dashboard must stay
                'summary' => ['total_spend', 'total_impressions', 'total_clicks', 'total_reach', 'ctr'],
                'daily', 'trend', 'breakdown', 'level',
            ]])
            ->assertJsonPath('data.summary.total_spend', 826669)
            ->assertJsonPath('data.summary.total_clicks', 469)
            ->assertJsonPath('data.summary.ctr', 0.84)
            ->assertJsonPath('data.summary.total_conversions', 19)
            ->assertJsonPath('data.summary.cost_per_conversion', 43508.89)
            ->assertJsonPath('data.summary.cpc', 1762.62)
            ->assertJsonPath('data.summary.roas', 0)
            ->assertJsonPath('data.account.conversion_action_type', 'onsite_conversion.messaging_conversation_started_7d')
            ->assertJsonCount(2, 'data.trend');
    }

    public function test_derived_metrics_are_null_without_denominator(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user', 'status' => 'approved']));
        $account = $this->metaAccount();

        $response = $this->getJson('/api/ads/stats?' . http_build_query([
            'client_ad_account_id' => $account->id,
            'start_date' => '2026-10-25',
            'end_date' => '2026-10-31',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.summary.total_spend', 0)
            ->assertJsonPath('data.summary.cpc', null)
            ->assertJsonPath('data.summary.cost_per_conversion', null)
            ->assertJsonPath('data.summary.roas', null);
    }

    public function test_stats_explain_the_last_failed_sync(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user', 'status' => 'approved']));
        $account = $this->metaAccount();
        $account->syncRuns()->create([
            'platform' => 'meta', 'date_from' => '2026-10-25', 'date_to' => '2026-10-31',
            'status' => 'failed', 'error_message' => 'Meta API error 190: token expired', 'started_at' => now(),
        ]);

        $this->getJson('/api/ads/stats?' . http_build_query([
            'client_ad_account_id' => $account->id,
            'start_date' => '2026-10-25',
            'end_date' => '2026-10-31',
        ]))
            ->assertOk()
            ->assertJsonPath('data.account.last_sync_status', 'failed')
            ->assertJsonPath('data.account.last_sync_error', 'Meta API error 190: token expired');
    }

    public function test_account_can_be_created_without_access_token(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'approved']));

        $this->postJson('/api/client-ad-accounts', [
            'platform' => 'google',
            'account_name' => 'Google Ads Davina',
            'account_id' => '704-565-1001',
        ])->assertCreated();

        $this->assertDatabaseHas('client_ad_accounts', ['account_id' => '704-565-1001', 'access_token' => null]);
    }
}
