<?php

namespace App\Http\Controllers\Api;

use App\Exports\AdsMetricsExport;
use App\Http\Controllers\Controller;
use App\Models\ClientAdAccount;
use App\Models\CompanySetting;
use App\Models\DailyAdMetric;
use App\Services\Ads\AdsReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    // Days with no spend, impressions or conversions are noise in a report
    private const HAS_ACTIVITY = 'SUM(spend) > 0 OR SUM(impressions) > 0 OR SUM(conversions) > 0';

    public function __construct(private AdsReportService $reports) {}

    /**
     * Validate and gather export data.
     */
    private function getExportData(Request $request): array
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

        $account = ClientAdAccount::findOrFail($accountId);
        $timezone = $account->timezone ?: config('app.timezone');

        $baseQuery = fn () => DailyAdMetric::where('client_ad_account_id', $accountId)
            ->where('level', $level)
            ->whereBetween('date', $dateRange);

        // Summary
        $sums = $baseQuery()
            ->selectRaw('COALESCE(SUM(spend), 0) as spend, COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(conversions), 0) as conversions, COALESCE(SUM(revenue), 0) as revenue')
            ->first();

        $summary = [
            'total_spend' => (float) $sums->spend,
            'total_impressions' => (int) $sums->impressions,
            'total_clicks' => (int) $sums->clicks,
            'total_conversions' => (float) $sums->conversions,
            'ctr' => $sums->impressions > 0 ? round(($sums->clicks / $sums->impressions) * 100, 2) : 0,
        ] + $this->reports->derivedMetrics((float) $sums->spend, (int) $sums->clicks, (float) $sums->conversions, (float) $sums->revenue);

        // Period totals per campaign / ad set / ad, biggest spend first
        [$idColumn, $nameColumn] = match ($level) {
            'campaign' => ['campaign_id', 'campaign_name'],
            'adset'    => ['adset_id', 'adset_name'],
            'ad'       => ['ad_id', 'ad_name'],
        };

        $totals = $baseQuery()
            ->select($idColumn, "{$nameColumn} as name")
            ->selectRaw('SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions')
            ->groupBy($idColumn, $nameColumn)
            ->havingRaw(self::HAS_ACTIVITY)
            ->orderByDesc('spend')
            ->get();

        // Detail rows
        $groupColumns = match ($level) {
            'campaign' => ['date', 'campaign_id', 'campaign_name'],
            'adset'    => ['date', 'campaign_id', 'campaign_name', 'adset_id', 'adset_name'],
            'ad'       => ['date', 'campaign_id', 'campaign_name', 'adset_id', 'adset_name', 'ad_id', 'ad_name'],
        };

        $selectParts = array_merge($groupColumns, [
            DB::raw('SUM(spend) as spend'),
            DB::raw('SUM(impressions) as impressions'),
            DB::raw('SUM(clicks) as clicks'),
            DB::raw('SUM(reach) as reach'),
            DB::raw('SUM(conversions) as conversions'),
        ]);

        $rows = $baseQuery()
            ->select($selectParts)
            ->groupBy($groupColumns)
            ->havingRaw(self::HAS_ACTIVITY)
            ->orderBy('date')
            ->get();

        $company = CompanySetting::first();

        // Same rule as the dashboard: a messaging action type means the conversions are WhatsApp chats
        $conversionType = $account->conversion_action_type
            ?: ($account->platform === 'meta' ? config('services.meta.default_conversion_action_type') : '');
        $isMessaging = str_contains((string) $conversionType, 'messaging');

        return [
            'account' => $account,
            'level' => $level,
            'level_label' => ['campaign' => 'Campaign', 'adset' => 'Ad set', 'ad' => 'Ad'][$level],
            'platform_label' => $account->platform === 'google' ? 'Google Ads' : 'Meta Ads',
            // The Google sync doesn't fetch reach, so its column would be all zeros
            'show_reach' => $account->platform !== 'google',
            'conversion_label' => $isMessaging ? 'Chat WA' : 'Konversi',
            'cost_per_conversion_label' => $isMessaging ? 'Biaya per chat WA' : 'Biaya per konversi',
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'period_label' => $this->formatDate($validated['start_date']) . ' s/d ' . $this->formatDate($validated['end_date']),
            'summary' => $summary,
            'totals' => $totals,
            'rows' => $rows,
            'company' => $company,
            'generated_at' => $this->formatDateTime(now($timezone)),
            'synced_at' => $account->last_synced_at ? $this->formatDateTime($account->last_synced_at->copy()->setTimezone($timezone)) : null,
        ];
    }

    private function formatDate(string $date): string
    {
        return Carbon::parse($date)->locale('id')->translatedFormat('j M Y');
    }

    private function formatDateTime(Carbon $moment): string
    {
        return $moment->locale('id')->translatedFormat('j M Y H:i') . ' ' . $moment->format('T');
    }

    /**
     * Export to PDF.
     */
    public function exportPdf(Request $request)
    {
        $data = $this->getExportData($request);

        $pdf = Pdf::loadView('exports.ads-report', $data)
            ->setPaper('a4', 'landscape');

        $filename = "ads-report-{$data['account']->account_name}-{$data['start_date']}-to-{$data['end_date']}.pdf";
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '_', $filename);

        return $pdf->download($filename);
    }

    /**
     * Export to Excel.
     */
    public function exportExcel(Request $request)
    {
        $data = $this->getExportData($request);

        $filename = "ads-report-{$data['account']->account_name}-{$data['start_date']}-to-{$data['end_date']}.xlsx";
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '_', $filename);

        $export = new AdsMetricsExport($data);

        return $export->download($filename);
    }
}
