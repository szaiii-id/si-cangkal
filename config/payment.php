<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Payment Gateway
|--------------------------------------------------------------------------
|
| Fase 1 hanya mendukung environment sandbox DOKU. Kredensial produksi
| sengaja ditolak supaya tidak terjadi transaksi uang sungguhan sebelum
| seluruh alur webhook, hold, split settlement, dan refund terverifikasi.
|
| SEMUA pembacaan environment variable DOKU hanya terjadi di file ini.
| Kode lain wajib memakai config('payment.doku.*').
|
*/

$dokuEnv = env('DOKU_ENV', 'sandbox');

if ($dokuEnv !== 'sandbox') {
    throw new RuntimeException(
        'Fase 1 hanya mendukung DOKU sandbox. Set DOKU_ENV=sandbox.',
    );
}

return [
    'doku' => [
        'env' => $dokuEnv,

        'base_url' => 'https://api-sandbox.doku.com',

        'client_id' => env('DOKU_CLIENT_ID'),

        'secret_key' => env('DOKU_SECRET_KEY'),

        'notification_url' => env('DOKU_NOTIFICATION_URL'),

        'checkout_path' => '/checkout/v1/payment',

        'release_path' => '/finance/v1/release',

        'refund_path' => '/cancellation/credit-card/refund',

        // Fase 1b — Sub Account V2
        'b2b_token_path' => '/authorization/v1/access-token/b2b',

        'private_key_path' => env('DOKU_PRIVATE_KEY_PATH'),

        'sandbox_enabled' => env('DOKU_SANDBOX_ENABLED', false),
    ],
];
