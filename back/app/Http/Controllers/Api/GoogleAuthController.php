<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth consent screen.
     */
    public function redirect()
    {
        return response()->json([
            'url' => Socialite::driver('google')
                ->stateless()
                ->redirect()
                ->getTargetUrl(),
        ]);
    }

    /**
     * Handle callback from Google, create/find user, return token.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            $frontendUrl = env('FRONTEND_URL', 'https://ads.pulsepowerhub.id');
            return redirect($frontendUrl . '/login?error=google_auth_failed');
        }

        // Find existing user or create new one
        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(24)),
            ]);
        }

        // Create Sanctum token
        $token = $user->createToken('google-login')->plainTextToken;

        // Redirect to frontend with token
        $frontendUrl = env('FRONTEND_URL', 'https://ads.pulsepowerhub.id');

        return redirect($frontendUrl . '/auth/callback?token=' . $token . '&name=' . urlencode($user->name));
    }
}
