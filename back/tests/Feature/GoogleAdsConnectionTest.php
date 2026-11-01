<?php

namespace Tests\Feature;

use App\Models\AdPlatformConnection;
use App\Models\User;
use App\Services\GoogleAdsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoogleAdsConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://api-ads.pulsepowerhub.id',
            'app.frontend_url' => 'https://ads.pulsepowerhub.id',
            'services.google_ads.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_ads.client_secret' => 'client-secret',
            'services.google_ads.redirect' => null,
        ]);
    }

    private function connectUrlState(): string
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'approved']));

        $url = $this->getJson('/api/google-ads/connect')->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('https://api-ads.pulsepowerhub.id/api/auth/google-ads/callback', $query['redirect_uri']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertStringContainsString('https://www.googleapis.com/auth/adwords', $query['scope']);

        return $query['state'];
    }

    public function test_only_admins_can_start_the_connection(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user', 'status' => 'approved']));

        $this->getJson('/api/google-ads/connect')->assertForbidden();
    }

    public function test_callback_stores_the_refresh_token_encrypted(): void
    {
        $state = $this->connectUrlState();

        Http::fake([
            GoogleAdsService::TOKEN_URL => Http::response(['access_token' => 'ya29.access', 'refresh_token' => '1//refresh-token']),
            'openidconnect.googleapis.com/*' => Http::response(['email' => 'abie@example.com']),
        ]);

        $this->get("/api/auth/google-ads/callback?state={$state}&code=auth-code")
            ->assertRedirect('https://ads.pulsepowerhub.id/settings?google_ads=connected');

        $connection = AdPlatformConnection::where('platform', 'google')->sole();
        $this->assertSame('1//refresh-token', $connection->refresh_token);
        $this->assertSame('abie@example.com', $connection->email);
        // Not stored in plain text
        $this->assertNotSame('1//refresh-token', DB::table('ad_platform_connections')->value('refresh_token'));

        Http::assertSent(fn (Request $request) => $request->url() === GoogleAdsService::TOKEN_URL
            && $request['grant_type'] === 'authorization_code'
            && $request['redirect_uri'] === 'https://api-ads.pulsepowerhub.id/api/auth/google-ads/callback');

        // The state is single-use
        $this->get("/api/auth/google-ads/callback?state={$state}&code=auth-code")
            ->assertRedirect('https://ads.pulsepowerhub.id/settings?google_ads=error&reason=invalid_state');
    }

    public function test_connection_failure_returns_to_settings_instead_of_500(): void
    {
        $state = $this->connectUrlState();

        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 60: SSL certificate problem'));

        $this->get("/api/auth/google-ads/callback?state={$state}&code=auth-code")
            ->assertRedirect('https://ads.pulsepowerhub.id/settings?google_ads=error&reason=connection_failed');

        $this->assertDatabaseCount('ad_platform_connections', 0);
    }

    public function test_callback_rejects_unknown_state(): void
    {
        Http::fake();

        $this->get('/api/auth/google-ads/callback?state=forged&code=auth-code')
            ->assertRedirect('https://ads.pulsepowerhub.id/settings?google_ads=error&reason=invalid_state');

        Http::assertNothingSent();
        $this->assertDatabaseCount('ad_platform_connections', 0);
    }

    public function test_status_never_exposes_the_token(): void
    {
        AdPlatformConnection::create(['platform' => 'google', 'refresh_token' => '1//secret', 'email' => 'abie@example.com']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'approved']));

        $response = $this->getJson('/api/google-ads/connection')
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.email', 'abie@example.com');

        $this->assertStringNotContainsString('1//secret', $response->getContent());
    }
}
