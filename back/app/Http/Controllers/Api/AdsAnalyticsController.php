<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyAdMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsAnalyticsController extends Controller
{
    public function getDashboardStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_ad_account_id' => 'required|exists:client_ad_accounts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'level' => 'sometimes|in:campaign,adset,ad',
        ]);

        $level = $validated['level'] ?? 'campaign';
        $accountId = $validated['client_ad_account_id'];
        $dateRange = [$validated['start_date'], $validated['end_date']];

        $baseQuery = DailyAdMetric::where('client_ad_account_id', $accountId)
            ->where('level', $level)
            ->whereBetween('date', $dateRange);

        // ── Summary ──
        $aggregated = [
            'total_spend' => (float) (clone $baseQuery)->sum('spend'),
            'total_impressions' => (int) (clone $baseQuery)->sum('impressions'),
            'total_clicks' => (int) (clone $baseQuery)->sum('clicks'),
            'total_reach' => (int) (clone $baseQuery)->sum('reach'),
            'ctr' => 0,
        ];

        if ($aggregated['total_impressions'] > 0) {
            $aggregated['ctr'] = round(($aggregated['total_clicks'] / $aggregated['total_impressions']) * 100, 2);
        }

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

        $selectParts = array_merge($groupColumns, [
            \DB::raw('SUM(spend) as spend'),
            \DB::raw('SUM(impressions) as impressions'),
            \DB::raw('SUM(clicks) as clicks'),
            \DB::raw('SUM(reach) as reach'),
        ]);

        $daily = DailyAdMetric::where('client_ad_account_id', $accountId)
            ->where('level', $level)
            ->whereBetween('date', $dateRange)
            ->select($selectParts)
            ->groupBy($groupColumns)
            ->orderBy('date')
            ->get();

        // ── Chart: daily trend (aggregated per day) ──
        $trend = DailyAdMetric::where('client_ad_account_id', $accountId)
            ->where('level', $level)
            ->whereBetween('date', $dateRange)
            ->selectRaw('DATE(date) as date, SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(reach) as reach')
            ->groupBy(\DB::raw('DATE(date)'))
            ->orderBy('date')
            ->get();

        // ── Chart: breakdown by name (top 10) ──
        $idColumn = match ($level) {
            'campaign' => 'campaign_id',
            'adset'    => 'adset_id',
            'ad'       => 'ad_id',
        };

        $breakdown = DailyAdMetric::where('client_ad_account_id', $accountId)
            ->where('level', $level)
            ->whereBetween('date', $dateRange)
            ->selectRaw("{$idColumn}, {$nameColumn} as name, SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks")
            ->groupBy($idColumn, $nameColumn)
            ->orderByDesc('spend')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $aggregated,
                'daily' => $daily,
                'trend' => $trend,
                'breakdown' => $breakdown,
                'level' => $level,
            ],
        ]);
    }
}
