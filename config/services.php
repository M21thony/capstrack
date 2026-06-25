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
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY', '6LeGxAcTAAAAAL5mwDbg83TS7mhh10e95o1L42Cg'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY', '6LeGxAcTAAAAAD19URmB6m1OSQC9FLgT3IQCOgNY'),
    ],

    'ollama' => [
        'model'       => env('OLLAMA_MODEL', 'qwen2.5:3B'),
        'embed_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
    ],

    'ssh_tunnel' => [
        'host'     => env('SSH_TUNNEL_HOST'),
        'port'     => env('SSH_TUNNEL_PORT', 22),
        'user'     => env('SSH_TUNNEL_USER'),
        'key_path' => env('SSH_TUNNEL_KEY_PATH'),
    ],

];
