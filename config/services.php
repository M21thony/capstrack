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

    // Web push. Empty keys are not an error - push simply stays off and
    // everything else (the bell, the emails) carries on as before.
    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:capstrack@cspc.edu.ph'),
    ],

    'ollama' => [
        // Where to reach Ollama directly over the web, e.g. a RunPod pod's
        // https://<pod-id>-11434.proxy.runpod.net. Empty: go through the SSH
        // tunnel below to the Windows laptop, as before.
        'url' => env('OLLAMA_URL'),

        'model' => env('OLLAMA_MODEL', 'qwen2.5:7B'),
        'embed_model' => env('OLLAMA_EMBED_MODEL'),

        // The model that judges one archived capstone against a pre-proposal,
        // when that should not be the model above.
        //
        // Empty, so it is the model above. The base model was tried here,
        // because the fine-tune recites one stock verdict on some cases - but
        // on the one case where the prompt itself states the expected answer
        // (CapsTrack against "Capstone Project and Thesis Class Information
        // Management System", which it says is 40-50) the fine-tune answers
        // 40 and the base answers 30. Neither reasons its way there; the
        // fine-tune simply recognises the worked example. Until the training
        // data has a middle for it to learn, the fine-tune is the better of
        // the two and the setting stays here to A/B a retrained one.
        'judge_model' => env('OLLAMA_JUDGE_MODEL'),

        // How long Ollama holds a model in memory after answering. Its own
        // default is five minutes, and a duplication check takes longer than
        // that to work through a team's titles - so the model was being
        // unloaded partway and read back off disk again, repeatedly, which is
        // where the minutes went. '-1' keeps it resident until something
        // else needs the memory.
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '30m'),

        // How many of a check's candidates get an AI-written verdict. Each
        // verdict is a model call, so this is the main lever on how long a
        // check takes.
        //
        // Ten, because that is how many the retriever returns and anything
        // less leaves some of them unread. Measured against the labelled
        // examples in the fine-tuning set: the genuine match is inside the
        // top four 92% of the time and inside the top ten 98%, so a limit of
        // four left about one check in twelve with the real duplicate never
        // looked at. Whatever is not judged is shown as "Not assessed"
        // rather than given a number.
        'judge_limit' => env('OLLAMA_JUDGE_LIMIT', 10),

        // ...and how close a candidate has to be to be worth explaining, as
        // the meaning match percentage (0 is the cosine of unrelated studies,
        // 100 of the same study; SimilarityService::embeddingPercent()).
        // The top match is always explained whatever it scored.
        //
        // 0, so every candidate the retriever keeps is read. The 40 this used
        // to be was on the old (cosine + 1) / 2 scale, where nothing scored
        // under 70 and it never held anything back; on this scale 40 would
        // leave real candidates unread.
        'judge_min_score' => env('OLLAMA_JUDGE_MIN_SCORE', 0),
    ],

    'ssh_tunnel' => [
        'host' => env('SSH_TUNNEL_HOST'),
        'port' => env('SSH_TUNNEL_PORT', 22),
        'user' => env('SSH_TUNNEL_USER'),
        'key_path' => env('SSH_TUNNEL_KEY_PATH'),
    ],

];
