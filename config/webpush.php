<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID keys
    |--------------------------------------------------------------------------
    | VAPID is how a browser's push service knows the message really came from
    | TRYBE. You generate the pair once and put them in .env — never commit
    | the private key.
    |
    | Generate with:  php artisan trybe:vapid
    */
    'subject'     => env('VAPID_SUBJECT', 'mailto:admin@trybe.test'),
    'public_key'  => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),

    /*
    | When false, notifications are still written to the database and shown in
    | the bell menu — the browser push step is simply skipped. This is what
    | keeps the page working before you have generated your keys.
    */
    'enabled' => env('VAPID_PUBLIC_KEY') !== null && env('VAPID_PRIVATE_KEY') !== null,
];
