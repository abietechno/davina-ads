<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientAdAccount;
use App\Services\Ads\AdsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsAnalyticsController extends Controller
{
    public function getDashboardStats(Request $request, AdsReportService $reports): JsonResponse
    {
        $validated = $request->validate([
            'client_ad_account_id' => 'required|exists:client_ad_accounts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'level' => 'sometimes|in:campaign,adset,ad',
        ]);

        $data = $reports->dashboardStats(
            ClientAdAccount::findOrFail($validated['client_ad_account_id']),
            $validated['level'] ?? 'campaign',
            $validated['start_date'],
            $validated['end_date'],
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
