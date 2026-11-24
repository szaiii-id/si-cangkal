<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'payment.doku.client_id' => 'MCH-SANDBOX-001',
        'payment.doku.secret_key' => 'secret-sandbox',
        'payment.doku.notification_url' => 'https://tunnel.lhr.life/payments/doku/webhook',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'message' => ['SUCCESS'],
            'response' => [
                'order' => ['session_id' => 'sess-1'],
                'payment' => ['url' => 'https://sandbox.doku.com/checkout-link-v2/abc'],
            ],
        ]),
    ]);

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);

    $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

    $this->booking = Booking::factory()
        ->forPackage($package)
        ->forClient($this->client)
        ->create([
            'mitra_id' => $this->mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_harga' => 500_000,
        ]);
});

function makeDokuBankAccount(User $mitra, array $overrides = []): MitraBankAccount
{
    return MitraBankAccount::create(array_merge([
        'user_id' => $mitra->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BCA',
        'nomor_rekening' => '1234567890',
        'nama_pemilik' => 'Mitra Travel',
        'is_primary' => true,
        'doku_profile_id' => 'PF-123',
        'doku_account_no' => '9001234567',
        'doku_split_rule_id' => 'SR-456',
    ], $overrides));
}

describe('checkout dengan Sub Account V2', function () {
    it('mengirim account.id dan split_rule_id ke DOKU', function () {
        makeDokuBankAccount($this->mitra);

        app(PaymentChargeService::class)->createForBooking($this->booking);

        Http::assertSent(function (Request $request): bool {
            $additional = $request->data()['additional_info'] ?? [];

            return $request->url() === 'https://api-sandbox.doku.com/checkout/v1/payment'
                && ($additional['account']['id'] ?? null) === '9001234567'
                && ($additional['account']['split_rule_id'] ?? null) === 'SR-456'
                && ! isset($additional['settlement']);
        });
    });

    it('fallback ke settlement lama bila sub-account belum terdaftar', function () {
        makeDokuBankAccount($this->mitra, [
            'doku_profile_id' => null,
            'doku_account_no' => null,
            'doku_split_rule_id' => null,
            'settlement_bank_account_id' => 'SBA-0001-123',
            'settlement_verified_at' => now(),
        ]);

        app(PaymentChargeService::class)->createForBooking($this->booking);

        Http::assertSent(function (Request $request): bool {
            $additional = $request->data()['additional_info'] ?? [];

            return isset($additional['settlement'][0])
                && $additional['settlement'][0]['bank_account_settlement_id'] === 'SBA-0001-123'
                && ! isset($additional['account']);
        });
    });

    it('menolak data sub-account yang tidak lengkap', function () {
        makeDokuBankAccount($this->mitra, [
            'doku_profile_id' => 'PF-123',
            'doku_account_no' => null,
            'doku_split_rule_id' => 'SR-456',
        ]);

        expect(fn () => app(PaymentChargeService::class)->createForBooking($this->booking))
            ->toThrow(RuntimeException::class);

        Http::assertNothingSent();
        expect(Payment::where('booking_id', $this->booking->id)->count())->toBe(0);
    });
});
