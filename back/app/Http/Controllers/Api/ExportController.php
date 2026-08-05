<?php

namespace App\Http\Controllers\Api;

use App\Exports\AdsMetricsExport;
use App\Http\Controllers\Controller;
use App\Models\ClientAdAccount;
use App\Models\CompanySetting;
use App\Models\DailyAdMetric;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ExportController extends Controller
{
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

        // Summary
        $baseQuery = DailyAdMetric::where('client_ad_account_id', $accountId)
            ->where('level', $level)
            ->whereBetween('date', $dateRange);

        $summary = [
            'total_spend' => (float) (clone $baseQuery)->sum('spend'),
            'total_impressions' => (int) (clone $baseQuery)->sum('impressions'),
            'total_clicks' => (int) (clone $baseQuery)->sum('clicks'),
            'total_reach' => (int) (clone $baseQuery)->sum('reach'),
            'ctr' => 0,
        ];

        if ($summary['total_impressions'] > 0) {
            $summary['ctr'] = round(($summary['total_clicks'] / $summary['total_impressions']) * 100, 2);
        }

        // Detail rows
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

        $rows = DailyAdMetric::where('client_ad_account_id', $accountId)
            ->where('level', $level)
            ->whereBetween('date', $dateRange)
            ->select($selectParts)
            ->groupBy($groupColumns)
            ->orderBy('date')
            ->get();

        $company = CompanySetting::first();

        return [
            'account' => $account,
            'level' => $level,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'summary' => $summary,
            'rows' => $rows,
            'company' => $company,
        ];
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
