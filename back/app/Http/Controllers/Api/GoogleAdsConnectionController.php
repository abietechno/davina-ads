<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdPlatformConnection;
use App\Services\GoogleAdsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * One-time OAuth flow that gives the app an offline (refresh) token for the
 * Google login that manages the MCC. Scope is the Google Ads API only; the app
 * only ever reads reports with it.
 */
class GoogleAdsConnectionController extends Controller
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const SCOPES = 'https://www.googleapis.com/auth/adwords openid email';

    private function redirectUri(): string
    {
        return config('services.google_ads.redirect')
            ?: rtrim(config('app.url'), '/') . '/api/auth/google-ads/callback';
    }

    private function backToSettings(string $status, ?string $reason = null): RedirectResponse
    {
        $query = http_build_query(array_filter(['google_ads' => $status, 'reason' => $reason]));

        return redirect(rtrim(config('app.frontend_url'), '/') . '/settings?' . $query);
    }

    public function show(): JsonResponse
    {
        $connection = AdPlatformConnection::where('platform', 'google')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'connected' => (bool) ($connection || config('services.google_ads.refresh_token')),
                'source' => $connection ? 'button' : (config('services.google_ads.refresh_token') ? 'env' : null),
                'email' => $connection?->email,
                'connected_at' => $connection?->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function redirect(Request $request): JsonResponse
    {
        $state = Str::random(40);
        Cache::put("google_ads_oauth_state:{$state}", $request->user()->id, now()->addMinutes(10));

        $url = self::AUTH_URL . '?' . http_build_query([
            'client_id' => config('services.google_ads.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            // Force the consent screen so Google always returns a refresh_token
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return response()->json(['success' => true, 'url' => $url]);
    }

    public function callback(Request $request): RedirectResponse
    {
        // The state ties this browser redirect back to the admin who clicked "Connect"
        $userId = Cache::pull('google_ads_oauth_state:' . $request->query('state'));
        if (! $request->query('state') || ! $userId) {
            return $this->backToSettings('error', 'invalid_state');
        }

        if ($request->query('error') || ! $request->query('code')) {
            return $this->backToSettings('error', $request->query('error', 'no_code'));
        }

        try {
            $tokenResponse = Http::asForm()->post(GoogleAdsService::TOKEN_URL, [
                'code' => $request->query('code'),
                'client_id' => config('services.google_ads.client_id'),
                'client_secret' => config('services.google_ads.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]);
        } catch (ConnectionException $e) {
            // e.g. server can't reach Google or has no CA bundle for SSL
            Log::error('Google Ads OAuth connection failed', ['message' => $e->getMessage()]);
            return $this->backToSettings('error', 'connection_failed');
        }

        $refreshToken = $tokenResponse->json('refresh_token');
        if ($tokenResponse->failed() || ! $refreshToken) {
            Log::error('Google Ads OAuth code exchange failed', [
                'status' => $tokenResponse->status(),
                'error' => $tokenResponse->json('error'),
                'error_description' => $tokenResponse->json('error_description'),
            ]);
            return $this->backToSettings('error', $tokenResponse->json('error', 'no_refresh_token'));
        }

        try {
            $email = Http::withToken($tokenResponse->json('access_token'))->get(self::USERINFO_URL)->json('email');
        } catch (ConnectionException) {
            $email = null; // only used for display; the refresh token is what matters
        }

        $previous = AdPlatformConnection::where('platform', 'google')->first();
        if ($previous) {
            GoogleAdsService::forgetAccessToken($previous->refresh_token);
        }

        AdPlatformConnection::updateOrCreate(
            ['platform' => 'google'],
            ['refresh_token' => $refreshToken, 'email' => $email, 'connected_by' => $userId],
        );

        return $this->backToSettings('connected');
    }

    public function destroy(): JsonResponse
    {
        $connection = AdPlatformConnection::where('platform', 'google')->first();

        if ($connection) {
            GoogleAdsService::forgetAccessToken($connection->refresh_token);
            $connection->delete();
        }

        return response()->json(['success' => true, 'message' => 'Google Ads disconnected.']);
    }
}
