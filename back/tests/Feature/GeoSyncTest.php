<?php

namespace Tests\Feature;

use App\Models\AdGeoMetric;
use App\Models\AdPlatformConnection;
use App\Models\ClientAdAccount;
use App\Models\DailyAdMetric;
use App\Models\User;
use App\Services\Ads\AdSyncService;
use App\Services\Ads\GeoLocator;
use App\Services\GoogleAdsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeoSyncTest extends TestCase
{
    use RefreshDatabase;

    private const WA = 'onsite_conversion.messaging_conversation_started_7d';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta.api_version' => 'v26.0',
            'services.meta.system_user_token' => 'system-user-token',
            'services.google_ads.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_ads.client_secret' => 'client-secret',
            'services.google_ads.mcc_id' => '804-583-9792',
            'services.google_ads.api_version' => 'v26',
        ]);
        Sleep::fake();
    }

    private function metaAccount(): ClientAdAccount
    {
        return ClientAdAccount::create([
            'platform' => 'meta',
            'account_name' => 'Davina Ads Group',
            'account_id' => 'act_1501772011366761',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'conversion_action_type' => self::WA,
        ]);
    }

    private function googleAccount(): ClientAdAccount
    {
        AdPlatformConnection::create(['platform' => 'google', 'refresh_token' => 'refresh-from-button']);

        return ClientAdAccount::create([
            'platform' => 'google',
            'account_name' => 'Google Ads Davina',
            'account_id' => '704-565-1001',
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    private function metaGeoRow(string $date, string $country, string $region, string $spend, int $connections): array
    {
        return [
            'spend' => $spend,
            'impressions' => '1000',
            'clicks' => '10',
            // Meta leaves the conversation_started action out of region breakdowns
            'actions' => [['action_type' => 'onsite_conversion.total_messaging_connection', 'value' => (string) $connections]],
            'date_start' => $date,
            'date_stop' => $date,
            'country' => $country,
            'region' => $region,
        ];
    }

    public function test_meta_sync_stores_regions_with_the_messaging_fallback(): void
    {
        Http::fake(function (Request $request) {
            if (($request['breakdowns'] ?? null) === 'country,region') {
                return Http::response(['data' => [
                    $this->metaGeoRow('2026-10-25', 'ID', 'Bali', '300000', 7),
                    $this->metaGeoRow('2026-10-25', 'ID', 'Unknown', '50', 0),
                ]]);
            }

            return Http::response(['data' => []]);
        });
        $account = $this->metaAccount();

        $result = app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-25');

        $this->assertSame([], $result['errors']);
        $bali = AdGeoMetric::where('region', 'Bali')->sole();
        $this->assertSame('ID', $bali->country_code);
        $this->assertEquals(7, $bali->conversions);
        $this->assertNull(AdGeoMetric::where('spend', 50)->sole()->region);
    }

    public function test_location_rows_are_replaced_not_duplicated_and_skipped_for_adset_level(): void
    {
        Http::fake(function (Request $request) {
            if (($request['breakdowns'] ?? null) === 'country,region') {
                return Http::response(['data' => [$this->metaGeoRow('2026-10-25', 'ID', 'Bali', '300000', 7)]]);
            }

            return Http::response(['data' => []]);
        });
        $account = $this->metaAccount();
        // A place that no longer shows up in the range must disappear
        AdGeoMetric::create(['client_ad_account_id' => $account->id, 'date' => '2026-10-25', 'country_code' => 'ID', 'region' => 'East Java', 'spend' => 1]);

        app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-25');
        app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-25');
        app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-25', 'adset');

        $this->assertSame(['Bali'], AdGeoMetric::pluck('region')->all());
        Http::assertSentCount(5); // 2x (campaign + location) + 1 adset
    }

    public function test_google_sync_resolves_cities_and_caches_geo_constants(): void
    {
        $constantLookups = 0;
        Http::fake(function (Request $request) use (&$constantLookups) {
            if ($request->url() === GoogleAdsService::TOKEN_URL) {
                return Http::response(['access_token' => 'ya29.access', 'expires_in' => 3599]);
            }

            $query = $request['query'] ?? '';

            if (str_contains($query, 'FROM user_location_view')) {
                $row = fn (string $location, int $country, int $micros) => [
                    'userLocationView' => ['countryCriterionId' => (string) $country],
                    'segments' => ['date' => '2026-10-25', 'geoTargetMostSpecificLocation' => $location],
                    'metrics' => ['costMicros' => (string) $micros, 'impressions' => '100', 'clicks' => '5', 'conversions' => 2],
                ];

                return Http::response([['results' => [
                    $row('geoTargetConstants/9072532', 2360, 1_000_000), // targeted
                    $row('geoTargetConstants/9072532', 2360, 500_000),   // same place, not targeted
                    $row('geoTargetConstants/1007824', 2356, 250_000),
                ]]]);
            }

            if (str_contains($query, 'FROM geo_target_constant')) {
                $constantLookups++;

                return Http::response([['results' => [
                    ['geoTargetConstant' => ['resourceName' => 'geoTargetConstants/9072532', 'canonicalName' => 'Kuta,Kuta,Bali,Indonesia', 'countryCode' => 'ID']],
                    ['geoTargetConstant' => ['resourceName' => 'geoTargetConstants/1007824', 'canonicalName' => 'Lucknow,Uttar Pradesh,India', 'countryCode' => 'IN']],
                ]]]);
            }

            return Http::response([['results' => []]]);
        });
        $account = $this->googleAccount();

        app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-25');
        app(AdSyncService::class)->sync($account, '2026-10-25', '2026-10-25');

        $this->assertSame(1, $constantLookups);
        $kuta = AdGeoMetric::where('city', 'Kuta')->sole();
        $this->assertSame(['ID', 'Bali'], [$kuta->country_code, $kuta->region]);
        $this->assertEquals(1.5, $kuta->spend);
        $this->assertSame('Uttar Pradesh', AdGeoMetric::where('country_code', 'IN')->sole()->region);
    }

    public function test_stats_group_points_and_report_abroad_and_unmapped_spend(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user', 'status' => 'approved']));
        $account = $this->metaAccount();

        DailyAdMetric::create(['client_ad_account_id' => $account->id, 'level' => 'campaign', 'date' => '2026-10-25', 'campaign_id' => '1', 'campaign_name' => 'WA', 'spend' => 1000]);
        $geo = fn (array $values) => AdGeoMetric::create($values + ['client_ad_account_id' => $account->id, 'date' => '2026-10-25', 'country_code' => 'ID']);
        $geo(['region' => 'Bali', 'spend' => 500, 'conversions' => 3]);
        $geo(['region' => 'Bali', 'spend' => 100, 'conversions' => 1, 'date' => '2026-10-26']);
        $geo(['region' => 'East Java', 'spend' => 200]);
        $geo(['country_code' => 'IN', 'region' => 'Uttar Pradesh', 'spend' => 120]);
        $geo(['country_code' => 'IN', 'region' => 'Bihar', 'spend' => 30]);
        $geo(['region' => 'Indonesia Urban', 'spend' => 50]); // can't be placed
        $geo(['region' => 'Bali', 'spend' => 999, 'date' => '2026-11-01']); // outside the range

        $response = $this->getJson('/api/ads/stats?' . http_build_query([
            'client_ad_account_id' => $account->id,
            'start_date' => '2026-10-25',
            'end_date' => '2026-10-31',
        ]));

        $response->assertOk();
        $geoStats = $response->json('data.geo');

        $this->assertSame(['ID|bali', 'ID|eastjava', 'IN'], array_column($geoStats['points'], 'key'));
        $this->assertEquals(600, $geoStats['points'][0]['spend']);
        $this->assertSame('Jawa Timur', $geoStats['points'][1]['label']);
        $this->assertEquals(150, $geoStats['points'][2]['spend']);
        $this->assertEquals(800, $geoStats['domestic_spend']);
        $this->assertEquals(150, $geoStats['abroad_spend']);
        $this->assertEquals(50, $geoStats['unmapped_spend']);
        $this->assertTrue($geoStats['conversions_approximate']);
    }

    public function test_locator_handles_platform_spellings(): void
    {
        $geo = new GeoLocator;

        $this->assertSame('Kab. Badung', $geo->locate('ID', 'Bali', 'Badung Regency')['label']);
        $this->assertSame('city', $geo->locate('id', 'Bali', 'Kuta')['precision']);
        $this->assertSame('Tibubeneng', $geo->locate('ID', 'Bali', 'Tibubeneng')['label']);
        // Postcode-only rows land on their regency
        $this->assertSame('Denpasar', $geo->locate('ID', 'Bali', '80221')['label']);
        $this->assertSame('Bali', $geo->locate('ID', 'Bali', '99999')['label']);
        // Kuta outside Bali (Lombok) is not the Bali point: falls back to its province
        $this->assertSame('Nusa Tenggara Barat', $geo->locate('ID', 'West Nusa Tenggara', 'Kuta')['label']);
        $this->assertSame('DI Yogyakarta', $geo->locate('ID', 'Special Region of Yogyakarta', null)['label']);
        $this->assertSame('Kepulauan Bangka Belitung', $geo->locate('ID', 'Bangka-Belitung Islands', null)['label']);
        $this->assertNull($geo->locate('ID', 'Indonesia Urban', null));
        $this->assertNull($geo->locate(null, null, null));
        $this->assertSame(['abroad', 'country'], array_values(array_intersect_key($geo->locate('IN', 'Uttar Pradesh', 'Lucknow'), array_flip(['scope', 'precision']))));
    }
}
