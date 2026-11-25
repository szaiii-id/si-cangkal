<?php

use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;

describe('konfigurasi Xendit', function () {
    it('menyediakan base URL sandbox dan versi API yang dipakai adapter', function () {
        expect(config('payment.xendit.base_url'))->toBe('https://api.xendit.co')
            ->and(config('payment.xendit.payment_api_version'))->toBe('2024-11-11')
            ->and(config('payment.xendit.webhook_path'))->toBe('/payments/xendit/webhook');
    });

    it('tidak menyimpan konfigurasi DOKU lagi', function () {
        expect(array_key_exists('doku', config('payment')))->toBeFalse();
    });

    it('mengenali xendit sebagai gateway dan metode pembayaran', function () {
        expect(PaymentGateway::tryFrom('xendit'))->not->toBeNull()
            ->and(PaymentMetode::tryFrom('xendit'))->not->toBeNull();
    });
});
