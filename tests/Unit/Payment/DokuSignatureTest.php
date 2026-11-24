<?php

use App\Services\Payment\Doku\DokuSignature;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Vektor tetap di bawah dihitung dari konstanta yang sama dengan contoh
| dokumentasi DOKU: Client-Id, Request-Id, Request-Timestamp, Request-Target,
| Digest = SHA256 base64 dari raw body, lalu HMAC-SHA256 base64.
|
*/

beforeEach(function () {
    config([
        'payment.doku.client_id' => 'MCH-SANDBOX-001',
        'payment.doku.secret_key' => 'secret-sandbox',
    ]);
});

describe('DokuSignature', function () {
    it('menghasilkan signature HMACSHA256 sesuai vektor tetap', function () {
        $body = '{"order":{"amount":100000}}';

        $signature = DokuSignature::forRequest(
            targetPath: '/payments/doku/webhook',
            body: $body,
            requestId: 'req-123',
            timestamp: '2026-11-24T00:00:00Z',
        );

        expect($signature)->toBe('HMACSHA256=6H+roH02pHzCzR1lgvint/NZqK3IqNzRszyuWlvS1Mk=');
    });

    it('menerima notifikasi dengan signature valid', function () {
        $body = '{"order":{"amount":100000}}';
        $signature = DokuSignature::forRequest(
            targetPath: '/payments/doku/webhook',
            body: $body,
            requestId: 'req-123',
            timestamp: '2026-11-24T00:00:00Z',
        );

        $valid = DokuSignature::verifyNotification(
            rawBody: $body,
            headers: [
                'Client-Id' => 'MCH-SANDBOX-001',
                'Request-Id' => 'req-123',
                'Request-Timestamp' => '2026-11-24T00:00:00Z',
                'Signature' => $signature,
            ],
            targetPath: '/payments/doku/webhook',
        );

        expect($valid)->toBeTrue();
    });

    it('menolak notifikasi ketika body diubah', function () {
        $body = '{"order":{"amount":100000}}';
        $signature = DokuSignature::forRequest(
            targetPath: '/payments/doku/webhook',
            body: $body,
            requestId: 'req-123',
            timestamp: '2026-11-24T00:00:00Z',
        );

        $valid = DokuSignature::verifyNotification(
            rawBody: '{"order":{"amount":999999}}',
            headers: [
                'Client-Id' => 'MCH-SANDBOX-001',
                'Request-Id' => 'req-123',
                'Request-Timestamp' => '2026-11-24T00:00:00Z',
                'Signature' => $signature,
            ],
            targetPath: '/payments/doku/webhook',
        );

        expect($valid)->toBeFalse();
    });

    it('menolak notifikasi ketika timestamp header berubah', function () {
        $body = '{"order":{"amount":100000}}';
        $signature = DokuSignature::forRequest(
            targetPath: '/payments/doku/webhook',
            body: $body,
            requestId: 'req-123',
            timestamp: '2026-11-24T00:00:00Z',
        );

        $valid = DokuSignature::verifyNotification(
            rawBody: $body,
            headers: [
                'Client-Id' => 'MCH-SANDBOX-001',
                'Request-Id' => 'req-123',
                'Request-Timestamp' => '2026-11-25T00:00:00Z',
                'Signature' => $signature,
            ],
            targetPath: '/payments/doku/webhook',
        );

        expect($valid)->toBeFalse();
    });

    it('menolak notifikasi ketika header tidak lengkap', function () {
        $valid = DokuSignature::verifyNotification(
            rawBody: '{}',
            headers: [],
            targetPath: '/payments/doku/webhook',
        );

        expect($valid)->toBeFalse();
    });
});
