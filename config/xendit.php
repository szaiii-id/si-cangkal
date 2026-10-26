<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Xendit API Credentials
    |--------------------------------------------------------------------------
    */
    'secret_key'     => env('XENDIT_SECRET_KEY'),
    'public_key'     => env('XENDIT_PUBLIC_KEY'),
    'callback_token' => env('XENDIT_CALLBACK_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Environment Mode
    |--------------------------------------------------------------------------
    */
    'mode' => env('XENDIT_MODE', 'test'),

    'is_production' => filter_var(env('XENDIT_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'callback_token_header' => 'x-callback-token',
    ],
];