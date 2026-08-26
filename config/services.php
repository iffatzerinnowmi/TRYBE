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
    'sslcommerz' => [
        'store_id' => env('SSLCOMMERZ_STORE_ID'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
        'session_url' => env('SSLCOMMERZ_SESSION_URL', 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'),
        'validation_url' => env('SSLCOMMERZ_VALIDATION_URL', 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'),
    ],

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
    
    /*
    |--------------------------------------------------------------------------
    | AI provider  (Member 4 — skill-gap coach)
    |--------------------------------------------------------------------------
    |
    | Google Gemini via AI Studio. The key names are deliberately generic, so
    | switching to Groq or OpenRouter is a .env change and nothing in app/
    | moves — all three expose an OpenAI-compatible /chat/completions endpoint.
    |
    | NEVER read these with env() outside this file. php artisan config:cache
    | freezes config and env() then silently returns null everywhere else.
    |
    */
    'ai' => [
        'provider' => env('AI_PROVIDER', 'gemini'),
        'key'      => env('AI_API_KEY'),

        // Defaulted so the client still has somewhere to POST if AI_BASE_URL
        // is missing from .env — otherwise a blank value produces a confusing
        // "cannot resolve host" rather than an obvious misconfiguration.
        'base_url' => env('AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'),

        /*
        | Absolute path to a CA certificate bundle, for verifying the
        | provider's TLS certificate.
        |
        | WHY THIS EXISTS. PHP on Windows ships without a CA bundle, so every
        | outbound HTTPS request fails with "cURL error 60: unable to get
        | local issuer certificate" until curl.cainfo is set in php.ini. That
        | is machine configuration, it is not in version control, and it gets
        | silently undone by XAMPP updates and control-panel edits — we lost
        | it twice in one evening.
        |
        | Setting it here makes the app carry its own answer: pointing
        | AI_CA_BUNDLE at a bundle in .env fixes it for one developer without
        | touching anyone else's machine, and leaving it blank falls back to
        | php.ini exactly as before.
        |
        | NOTE: this still VERIFIES the certificate. It only says which roots
        | to trust. Do not be tempted to pass verify => false instead — that
        | turns the error off by turning the check off.
        */
        'ca_bundle' => env('AI_CA_BUNDLE'),

        'model'   => env('AI_MODEL', 'gemini-2.5-flash'),
        'timeout' => (int) env('AI_TIMEOUT', 8),
        'enabled' => env('AI_API_KEY') !== null,
    ],
];
