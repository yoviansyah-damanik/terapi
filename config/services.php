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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'tte' => [
        'base_url' => env('TTE_BASE_URL'),
        'username' => env('TTE_USERNAME'),
        'password' => env('TTE_PASSWORD'),
    ],

    'snowstorm' => [
        'url' => env('SNOWSTORM_URL', 'http://simrs.rumkittnipsp.com:9876'),
        'branch' => env('SNOWSTORM_BRANCH', 'MAIN'),
    ],

    // OAuth RS — Authorization Server
    'oauth_rs' => [
        'base_url'      => env('OAUTH_RS_URL'),
        'client_id'     => env('OAUTH_RS_CLIENT_ID'),
        'client_secret' => env('OAUTH_RS_CLIENT_SECRET'),
        'redirect_uri'  => env('OAUTH_RS_REDIRECT_URI', env('APP_URL') . '/auth/oauth/callback'),
        'scopes'        => ['openid', 'profile', 'email', 'role'],
    ],
];
