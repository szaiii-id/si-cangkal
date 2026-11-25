<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Payment Gateway
|--------------------------------------------------------------------------
|
| SI-CANGKAL memakai Xendit Test Mode (sandbox). Seluruh fitur yang dipakai
| tersedia tanpa KYC, tanpa XenPlatform, dan tanpa aktivasi channel:
| Payment Session, webhook, refund, dan payout ke rekening bank.
|
| SEMUA pembacaan environment variable XENDIT hanya terjadi di file ini.
| Kode lain wajib memakai config('payment.xendit.*').
|
| Nilai XENDIT_SECRET_KEY dan XENDIT_CALLBACK_TOKEN diisi manual di .env
| lokal setelah secret key dan callback token dibuat di dashboard Xendit.
|
*/

return [
    'xendit' => [
        'base_url' => 'https://api.xendit.co',

        'secret_key' => env('XENDIT_SECRET_KEY'),

        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),

        'payment_api_version' => '2024-11-11',

        // Payout instan dipakai hanya untuk demo; default tetap menunggu
        // trip selesai seperti perilaku bisnis sebelumnya.
        'instant_payout_enabled' => env('XENDIT_INSTANT_PAYOUT_ENABLED', false),

        // Komisi dinas dihitung di aplikasi karena split rule butuh XenPlatform.
        'platform_fee_percent' => env('XENDIT_PLATFORM_FEE_PERCENT', 0),

        'webhook_path' => '/payments/xendit/webhook',
    ],
];
