<?php

use App\Models\Refund;
use App\Services\Payment\Doku\DokuIdempotencyKey;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Request-Id DOKU maksimal 128 karakter dan dipakai sebagai idempotency key.
| Kunci harus deterministik per booking + purpose agar retry tidak membuat
| charge/refund ganda, tetapi berbeda antar transaksi dan purpose.
|
*/

describe('DokuIdempotencyKey', function () {
    it('stabil untuk booking dan purpose yang sama', function () {
        $booking = makeBookingMock(['id' => 42]);

        $first = DokuIdempotencyKey::forCharge($booking);
        $second = DokuIdempotencyKey::forCharge($booking);

        expect($first)->toBe($second);
    });

    it('berbeda untuk booking berbeda', function () {
        $first = DokuIdempotencyKey::forCharge(makeBookingMock(['id' => 42]));
        $second = DokuIdempotencyKey::forCharge(makeBookingMock(['id' => 43]));

        expect($first)->not->toBe($second);
    });

    it('berbeda untuk purpose berbeda', function () {
        $booking = makeBookingMock(['id' => 42]);

        expect(DokuIdempotencyKey::forCharge($booking))
            ->not->toBe(DokuIdempotencyKey::forRelease($booking));
    });

    it('berbeda untuk refund berbeda', function () {
        $first = new Refund;
        $first->id = 7;

        $second = new Refund;
        $second->id = 8;

        expect(DokuIdempotencyKey::forRefund($first))
            ->not->toBe(DokuIdempotencyKey::forRefund($second));
    });

    it('memenuhi batas panjang dan karakter Request-Id DOKU', function () {
        $key = DokuIdempotencyKey::forCharge(makeBookingMock(['id' => 42]));

        expect(strlen($key))->toBeLessThanOrEqual(128)
            ->and($key)->toMatch('/^[A-Za-z0-9-]+$/');
    });
});
