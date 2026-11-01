<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_LOGIN_CLIENT_ID'),
        'client_secret' => env('GOOGLE_LOGIN_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_LOGIN_REDIRECT_URI'),
    ],

    'app_admin' => [
        'email' => env('ADMIN_EMAIL', 'abietechno.id@gmail.com'),
    ],

    'meta' => [
        'api_version' => env('META_API_VERSION', 'v26.0'),
        // Business Manager System User token (never expires); per-account tokens are only a fallback
        'system_user_token' => env('META_SYSTEM_USER_TOKEN'),
        'default_conversion_action_type' => env('META_DEFAULT_CONVERSION_ACTION_TYPE', 'purchase'),
    ],

    'google_ads' => [
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'mcc_id' => env('GOOGLE_ADS_MCC_ID'),
        'api_version' => env('GOOGLE_ADS_API_VERSION', 'v26'),
        // Optional fallback; normally the refresh token comes from the admin "Connect Google Ads" button (ad_platform_connections)
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
        // Defaults to APP_URL/api/auth/google-ads/callback; must also be listed in Google Cloud Console
        'redirect' => env('GOOGLE_ADS_REDIRECT_URI'),
    ],

];
