<?php

use App\Enums\PaymentGateway;
use App\Models\MitraBankAccount;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\Schema;

/*
|==========================================================================
| SETUP — Integration Test (RefreshDatabase aktif untuk Feature)
|==========================================================================
|
| Memastikan schema gateway DOKU sudah tersedia sebelum service dipakai.
|
*/

describe('schema pembayaran gateway', function () {
    it('payments memiliki field gateway DOKU', function () {
        expect(Schema::hasColumns('payments', [
            'gateway',
            'gateway_order_id',
            'gateway_request_id',
            'gateway_status',
            'gateway_payload',
            'paid_at',
            'settled_at',
            'released_at',
        ]))->toBeTrue();
    });

    it('refunds memiliki field refund gateway', function () {
        expect(Schema::hasColumns('refunds', [
            'gateway_refund_id',
            'gateway_status',
        ]))->toBeTrue();
    });

    it('mitra_bank_accounts memiliki field settlement DOKU', function () {
        expect(Schema::hasColumns('mitra_bank_accounts', [
            'settlement_bank_account_id',
            'settlement_verified_at',
        ]))->toBeTrue();
    });

    it('enum PaymentGateway memiliki nilai doku dan manual', function () {
        expect(PaymentGateway::DOKU->value)->toBe('doku')
            ->and(PaymentGateway::MANUAL->value)->toBe('manual');
    });

    it('model meng-cast field gateway dengan benar', function () {
        expect((new Payment)->getCasts())
            ->toHaveKey('gateway', PaymentGateway::class)
            ->toHaveKey('gateway_payload', 'array')
            ->toHaveKey('paid_at', 'datetime')
            ->toHaveKey('settled_at', 'datetime')
            ->toHaveKey('released_at', 'datetime');

        expect((new Refund)->getCasts())->toHaveKey('gateway_status', 'string');

        expect((new MitraBankAccount)->getCasts())
            ->toHaveKey('settlement_verified_at', 'datetime');
    });
});
