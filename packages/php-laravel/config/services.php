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

    'r2' => [
        'internal_host' => env('GOX_R2_INTERNAL_HOST', 'r2.internal'),
        'mock_resolve_target' => env('GOX_R2_MOCK_RESOLVE_TARGET', '127.0.0.1:3000'),
    ],

    'transcoder' => [
        'ffmpeg_path' => env('GOX_FFMPEG_PATH', 'ffmpeg'),
        'ffprobe_path' => env('GOX_FFPROBE_PATH', 'ffprobe'),
    ],

];
