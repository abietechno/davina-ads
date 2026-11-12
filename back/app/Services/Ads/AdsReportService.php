<?php

namespace App\Services\Ads;

use App\Models\AdGeoMetric;
use App\Models\ClientAdAccount;
use App\Models\DailyAdMetric;
use App\Services\MetaAdsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for ad metric aggregation, shared by the dashboard API
 * (and later the MCP tools) so every surface reports the same numbers.
 */
class AdsReportService
{
    public function __construct(private GeoLocator $geo) {}

    public function dashboardStats(ClientAdAccount $account, string $level, string $startDate, string $endDate): array
    {
        $dateRange = [$startDate, $endDate];

        $baseQuery = fn (): Builder => DailyAdMetric::where('client_ad_account_id', $account->id)
            ->where('level', $level)
            ->whereBetween('date', $dateRange);

        // ── Summary ──
        $totals = $baseQuery()
            ->selectRaw('COALESCE(SUM(spend), 0) as spend, COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(reach), 0) as reach, COALESCE(SUM(conversions), 0) as conversions, COALESCE(SUM(revenue), 0) as revenue')
            ->first();

        $spend = (float) $totals->spend;
        $impressions = (int) $totals->impressions;
        $clicks = (int) $totals->clicks;
        $conversions = (float) $totals->conversions;
        $revenue = (float) $totals->revenue;

        $summary = [
            'total_spend' => $spend,
            'total_impressions' => $impressions,
            'total_clicks' => $clicks,
            'total_reach' => (int) $totals->reach,
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
            'total_conversions' => $conversions,
            'total_revenue' => $revenue,
        ] + $this->derivedMetrics($spend, $clicks, $conversions, $revenue);

        // ── Detail table rows ──
        $nameColumn = match ($level) {
            'campaign' => 'campaign_name',
            'adset'    => 'adset_name',
            'ad'       => 'ad_name',
        };

        $groupColumns = match ($level) {
            'campaign' => ['date', 'campaign_id', 'campaign_name'],
            'adset'    => ['date', 'campaign_id', 'campaign_name', 'adset_id', 'adset_name'],
            'ad'       => ['date', 'campaign_id', 'campaign_name', 'adset_id', 'adset_name', 'ad_id', 'ad_name'],
        };

        $daily = $baseQuery()
            ->select(array_merge($groupColumns, [
                DB::raw('SUM(spend) as spend'),
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(reach) as reach'),
                DB::raw('SUM(conversions) as conversions'),
                DB::raw('SUM(revenue) as revenue'),
            ]))
            ->groupBy($groupColumns)
            ->orderBy('date')
            ->get();

        // ── Chart: daily trend (aggregated per day) ──
        $trend = $baseQuery()
            ->selectRaw('DATE(date) as date, SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(reach) as reach, SUM(conversions) as conversions, SUM(revenue) as revenue')
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('date')
            ->get();

        // ── Chart: breakdown by name (top 10) ──
        $idColumn = match ($level) {
            'campaign' => 'campaign_id',
            'adset'    => 'adset_id',
            'ad'       => 'ad_id',
        };

        $breakdown = $baseQuery()
            ->selectRaw("{$idColumn}, {$nameColumn} as name, SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions")
            ->groupBy($idColumn, $nameColumn)
            ->orderByDesc('spend')
            ->limit(10)
            ->get();

        $lastRun = $account->syncRuns()->latest('id')->first();

        $geo = $this->geoStats($account, $startDate, $endDate);
        // Spend no location row accounts for: Google leaves some traffic unplaced, and older dates may predate the location sync
        $geo['unmapped_spend'] = max(0, round($spend - $geo['domestic_spend'] - $geo['abroad_spend'], 2));

        return [
            'summary' => $summary,
            'daily' => $daily,
            'trend' => $trend,
            'breakdown' => $breakdown,
            'geo' => $geo,
            'level' => $level,
            'account' => [
                'platform' => $account->platform,
                'currency' => $account->currency,
                'timezone' => $account->timezone,
                'conversion_action_type' => $account->conversion_action_type,
                'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                // Lets the dashboard explain an empty report (e.g. API access not approved) without opening the console
                'last_sync_status' => $lastRun?->status,
                'last_sync_error' => $lastRun?->status === 'failed' ? $lastRun->error_message : null,
                'last_sync_attempt_at' => $lastRun?->started_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Delivery per map point. Abroad is grouped per country; rows that can't be placed
     * (unknown location, see GeoLocator) only count towards unmapped spend.
     */
    public function geoStats(ClientAdAccount $account, string $startDate, string $endDate): array
    {
        $rows = AdGeoMetric::where('client_ad_account_id', $account->id)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->selectRaw('country_code, region, city, SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions')
            ->groupBy('country_code', 'region', 'city')
            ->get();

        $points = [];

        foreach ($rows as $row) {
            $point = $this->geo->locate($row->country_code, $row->region, $row->city);
            if (! $point) continue;

            $points[$point['key']] ??= $point + ['spend' => 0.0, 'impressions' => 0, 'clicks' => 0, 'conversions' => 0.0];
            $points[$point['key']]['spend'] += (float) $row->spend;
            $points[$point['key']]['impressions'] += (int) $row->impressions;
            $points[$point['key']]['clicks'] += (int) $row->clicks;
            $points[$point['key']]['conversions'] += (float) $row->conversions;
        }

        $points = collect($points)
            ->filter(fn (array $p) => $p['spend'] > 0 || $p['impressions'] > 0)
            ->sortByDesc('spend')
            ->values();

        $abroad = $points->where('scope', 'abroad');
        $conversionType = $account->conversion_action_type ?: config('services.meta.default_conversion_action_type');

        return [
            'points' => $points->all(),
            'home_country' => GeoLocator::HOME_COUNTRY,
            'domestic_spend' => round($points->where('scope', 'domestic')->sum('spend'), 2),
            'abroad_spend' => round($abroad->sum('spend'), 2),
            'abroad_conversions' => $abroad->sum('conversions'),
            // Meta doesn't report every conversion action per region; the map then counts the closest one
            'conversions_approximate' => $account->platform === 'meta' && isset(MetaAdsService::GEO_CONVERSION_FALLBACKS[$conversionType]),
        ];
    }

    /**
     * CPC, cost per conversion and ROAS are always computed at query time, never stored.
     * Each is null when its denominator is zero so the UI can show "–" instead of a fake 0.
     */
    public function derivedMetrics(float $spend, int $clicks, float $conversions, float $revenue): array
    {
        return [
            'cpc' => $clicks > 0 ? round($spend / $clicks, 2) : null,
            'cost_per_conversion' => $conversions > 0 ? round($spend / $conversions, 2) : null,
            'roas' => $spend > 0 ? round($revenue / $spend, 2) : null,
        ];
    }
}
