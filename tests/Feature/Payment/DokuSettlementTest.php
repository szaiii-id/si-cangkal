<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Services\Trip\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| Dana ditahan saat pembayaran (hold settlement) dan dilepas setelah
| trip diselesaikan mitra. Release memakai endpoint sandbox DOKU.
|
*/

beforeEach(function () {
    config([
        'payment.doku.client_id' => 'MCH-SANDBOX-001',
        'payment.doku.secret_key' => 'secret-sandbox',
        'payment.doku.base_url' => 'https://api-sandbox.doku.com',
        'payment.doku.release_path' => '/finance/v1/release',
    ]);

    Http::preventStrayRequests();

    $audit = Mockery::mock(LoggerInterface::class);
    $audit->shouldReceive('info')->byDefault()->andReturnNull();

    Log::shouldReceive('channel')->with('audit')->byDefault()->andReturn($audit);
    Log::shouldReceive('driver')->byDefault()->andReturn($audit);
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
    Log::shouldReceive('error')->byDefault()->andReturnNull();
    Log::shouldReceive('info')->byDefault()->andReturnNull();

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);

    MitraBankAccount::create([
        'user_id' => $this->mitra->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BCA',
        'nomor_rekening' => '1234567890',
        'nama_pemilik' => 'Mitra Travel',
        'is_primary' => true,
        'settlement_bank_account_id' => 'SBA-0001-123',
        'settlement_verified_at' => now(),
    ]);
});

function prepareActiveDokuTrip(User $mitra, User $client): array
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED,
            'total_harga' => 1_000_000,
        ]);

    Payment::create([
        'booking_id' => $booking->id,
        'jumlah' => 1_000_000,
        'metode' => PaymentMetode::DOKU,
        'gateway' => PaymentGateway::DOKU,
        'gateway_order_id' => $booking->kode,
        'gateway_request_id' => 'charge-1',
        'gateway_status' => 'SUCCESS',
    ]);

    $trip = Trip::factory()
        ->forBooking($booking)
        ->create([
            'mitra_id' => $mitra->id,
            'status' => TripStatus::ACTIVE,
            'package_id' => $package->id,
        ]);

    return [$booking, $trip];
}

function fakeDokuReleaseSuccess(): void
{
    Http::fake([
        'api-sandbox.doku.com/finance/v1/release' => Http::response([
            'order' => [
                'invoice_number' => 'BKG-DUMMY',
                'amount' => 1_000_000,
                'currency' => 'IDR',
            ],
            'transaction' => [
                'original_request_id' => 'charge-1',
                'settlement_date' => '2026-11-25',
                'settlement_amount' => 993_000,
            ],
        ]),
    ]);
}

describe('release settlement DOKU', function () {
    it('melepas settlement setelah trip selesai', function () {
        [$booking, $trip] = prepareActiveDokuTrip($this->mitra, $this->client);
        fakeDokuReleaseSuccess();

        app(TripService::class)->complete($trip);

        Http::assertSent(function (Request $request) use ($booking): bool {
            if ($request->method() !== 'POST'
                || $request->url() !== 'https://api-sandbox.doku.com/finance/v1/release') {
                return false;
            }

            $data = $request->data();

            return $request->hasHeader('Request-Id')
                && str_starts_with((string) $request->header('Signature')[0], 'HMACSHA256=')
                && $data['order']['invoice_number'] === $booking->kode
                && $data['order']['amount'] === 1_000_000
                && $data['transaction']['original_request_id'] === 'charge-1'
                && $data['override_settlement'][0]['bank_account_settlement_id'] === 'SBA-0001-123';
        });

        $payment = Payment::where('booking_id', $booking->id)->firstOrFail();

        expect($payment->released_at)->not->toBeNull()
            ->and($payment->settled_at->toDateString())->toBe('2026-11-25')
            ->and($payment->gateway_status)->toBe('SETTLED');
    });

    it('tidak memanggil release untuk trip tanpa pembayaran gateway', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();
        $booking = Booking::factory()
            ->forPackage($package)
            ->forClient($this->client)
            ->create([
                'mitra_id' => $this->mitra->id,
                'status' => BookingStatus::CONFIRMED,
            ]);
        Payment::create([
            'booking_id' => $booking->id,
            'jumlah' => 1_000_000,
            'metode' => PaymentMetode::TRANSFER_MANUAL,
            'gateway' => PaymentGateway::MANUAL,
            'verified_at' => now(),
        ]);
        $trip = Trip::factory()->forBooking($booking)->create([
            'mitra_id' => $this->mitra->id,
            'status' => TripStatus::ACTIVE,
            'package_id' => $package->id,
        ]);

        fakeDokuReleaseSuccess();

        app(TripService::class)->complete($trip);

        Http::assertNothingSent();
    });
});
