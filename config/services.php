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
        // The Picker is a separate API and wants a browser key, not the OAuth
        // pair above. Without it the "Choose from Drive" button stays hidden.
        'picker_api_key' => env('GOOGLE_PICKER_API_KEY'),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY', '6LeGxAcTAAAAAL5mwDbg83TS7mhh10e95o1L42Cg'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY', '6LeGxAcTAAAAAD19URmB6m1OSQC9FLgT3IQCOgNY'),
    ],

    'ollama' => [
        'model' => env('OLLAMA_MODEL', 'qwen2.5:7B'),
        'embed_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),

        // How long Ollama holds a model in memory after answering. Its own
        // default is five minutes, and a duplication check takes longer than
        // that to work through a team's titles - so the model was being
        // unloaded partway and read back off disk again, repeatedly, which is
        // where the minutes went. '-1' keeps it resident until something
        // else needs the memory.
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '30m'),

        // How many of a check's candidates get an AI-written verdict. The
        // rest keep the embedding line they were already showing. Each
        // verdict is a model call, so this is the main lever on how long a
        // check takes on a weak connection.
        'judge_limit' => env('OLLAMA_JUDGE_LIMIT', 4),

        // ...and how close a candidate has to be to be worth explaining.
        // The top match is always explained whatever it scored.
        'judge_min_score' => env('OLLAMA_JUDGE_MIN_SCORE', 40),
    ],

    'ssh_tunnel' => [
        'host' => env('SSH_TUNNEL_HOST'),
        'port' => env('SSH_TUNNEL_PORT', 22),
        'user' => env('SSH_TUNNEL_USER'),
        'key_path' => env('SSH_TUNNEL_KEY_PATH'),
    ],

];
