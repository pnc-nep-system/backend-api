<?php

return [

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

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
        'timeout' => env('MISTRAL_TIMEOUT', 30),
        'retry_attempts' => env('MISTRAL_RETRY_ATTEMPTS', 2),
        'retry_delay' => env('MISTRAL_RETRY_DELAY', 500),
    ],

    'imagekit' => [
        'public_key'   => env('IMAGEKIT_PUBLIC_KEY'),
        'private_key'  => env('IMAGEKIT_PRIVATE_KEY'),
        'url_endpoint' => env('IMAGEKIT_URL_ENDPOINT'),
    ],

];