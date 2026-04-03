<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientAdAccount;
use App\Services\GoogleAdsService;
use App\Services\MetaAdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function sync(Request $request, ClientAdAccount $clientAdAccount): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'level' => 'sometimes|in:campaign,adset,ad',
        ]);

        $level = $validated['level'] ?? 'campaign';

        $service = match ($clientAdAccount->platform) {
            'meta' => new MetaAdsService(),
            'google' => new GoogleAdsService(),
        };

        $result = $service->sync(
            $clientAdAccount,
            $validated['start_date'],
            $validated['end_date'],
            $level
        );

        if (! empty($result['errors'])) {
            return response()->json([
                'success' => false,
                'message' => 'Sync completed with errors.',
                'data' => $result,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Synced {$result['synced']} records ({$level} level).",
            'data' => $result,
        ]);
    }

    public function syncAll(Request $request): JsonResponse
    {
        $level = $request->input('level', 'campaign');
        $accounts = ClientAdAccount::all();
        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');
        $totalSynced = 0;
        $allErrors = [];

        foreach ($accounts as $account) {
            $service = match ($account->platform) {
                'meta' => new MetaAdsService(),
                'google' => new GoogleAdsService(),
            };

            $result = $service->sync($account, $startDate, $endDate, $level);
            $totalSynced += $result['synced'];

            if (! empty($result['errors'])) {
                $allErrors[$account->account_name] = $result['errors'];
            }
        }

        return response()->json([
            'success' => empty($allErrors),
            'message' => "Synced {$totalSynced} records across {$accounts->count()} accounts.",
            'data' => ['synced' => $totalSynced, 'errors' => $allErrors],
        ]);
    }
}
