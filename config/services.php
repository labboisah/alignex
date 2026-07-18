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

    'bulksms_nigeria' => [
        'base_url' => env('BULKSMS_NIGERIA_BASE_URL', 'https://www.bulksmsnigeria.com/api/sandbox/v2'),
        'api_token' => env('BULKSMS_NIGERIA_API_TOKEN'),
        'sender_id' => env('BULKSMS_NIGERIA_SENDER_ID', 'AlignEx'),
        'gateway' => env('BULKSMS_NIGERIA_GATEWAY'),
        'dry_run' => env('BULKSMS_NIGERIA_DRY_RUN', env('NOTIFICATIONS_DRY_RUN', true)),
    ],

];
