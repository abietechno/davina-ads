<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientAdAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdAccountController extends Controller
{
    public function index(): JsonResponse
    {
        // Tokens/API keys are write-only via this endpoint; never returned in listings.
        $accounts = ClientAdAccount::select('id', 'platform', 'account_name', 'account_id', 'created_at', 'updated_at')->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|in:meta,google',
            'account_name' => 'required|string|max:255',
            'account_id' => 'required|string|max:255',
            'access_token' => 'required|string',
            'api_key_or_refresh_token' => 'nullable|string',
        ]);

        $account = ClientAdAccount::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ad account saved successfully.',
            'data' => $account,
        ], 201);
    }

    public function update(Request $request, ClientAdAccount $clientAdAccount): JsonResponse
    {
        $validated = $request->validate([
            'account_name' => 'sometimes|string|max:255',
            'account_id' => 'sometimes|string|max:255',
            'access_token' => 'nullable|string',
            'api_key_or_refresh_token' => 'nullable|string',
        ]);

        // Only update access_token if a new one is provided (not empty)
        if (empty($validated['access_token'])) {
            unset($validated['access_token']);
        }
        if (array_key_exists('api_key_or_refresh_token', $validated) && empty($validated['api_key_or_refresh_token'])) {
            unset($validated['api_key_or_refresh_token']);
        }

        $clientAdAccount->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ad account updated.',
            'data' => $clientAdAccount,
        ]);
    }

    public function destroy(ClientAdAccount $clientAdAccount): JsonResponse
    {
        $clientAdAccount->dailyAdMetrics()->delete();
        $clientAdAccount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ad account deleted.',
        ]);
    }
}
