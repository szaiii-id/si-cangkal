<?php

use App\Data\Doku\DokuNotification;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Events\BookingConfirmed;
use App\Jobs\ProcessDokuNotificationJob;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Payment\DokuNotificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| Proses notifikasi DOKU di dalam transaksi: lock booking, cek ulang status
| dan amount, update payment + booking, lalu efek samping setelah commit.
|
*/

beforeEach(function () {
    Event::fake([BookingConfirmed::class]);

    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);
    $this->notifier->shouldReceive('send')->byDefault()->andReturnNull();
    $this->app->instance(NotificationDispatcherInterface::class, $this->notifier);

    $audit = Mockery::mock(LoggerInterface::class);
    $audit->shouldReceive('info')->byDefault()->andReturnNull();

    Log::shouldReceive('channel')->with('audit')->byDefault()->andReturn($audit);
    Log::shouldReceive('driver')->byDefault()->andReturn($audit);
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
    Log::shouldReceive('info')->byDefault()->andReturnNull();

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
});

function prepareDokuBooking(User $mitra, User $client): Booking
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_harga' => 1_000_000,
        ]);

    Payment::create([
        'booking_id' => $booking->id,
        'jumlah' => 1_000_000,
        'metode' => PaymentMetode::DOKU,
        'gateway' => PaymentGateway::DOKU,
        'gateway_order_id' => $booking->kode,
        'gateway_request_id' => 'charge-1',
        'gateway_status' => 'pending',
    ]);

    return $booking;
}

function dokuNotification(string $invoice, int $amount = 1_000_000, string $status = 'SUCCESS', string $requestId = 'notif-1'): DokuNotification
{
    return new DokuNotification(
        requestId: $requestId,
        invoiceNumber: $invoice,
        amount: $amount,
        status: $status,
        payload: [],
    );
}

function runDokuJob(DokuNotification $notification): void
{
    (new ProcessDokuNotificationJob($notification))
        ->handle(app(DokuNotificationProcessor::class));
}

describe('ProcessDokuNotificationJob', function () {
    it('sukses: update payment & booking lalu dispatch BookingConfirmed', function () {
        $booking = prepareDokuBooking($this->mitra, $this->client);

        runDokuJob(dokuNotification($booking->kode));

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'gateway_status' => 'SUCCESS',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);

        expect($booking->fresh()->payment_verified_at)->not->toBeNull();

        Event::assertDispatchedTimes(BookingConfirmed::class, 1);
        $this->notifier->shouldHaveReceived('send')->once();
    });

    it('idempoten: notifikasi kedua dengan request id berbeda tidak mengulang event', function () {
        $booking = prepareDokuBooking($this->mitra, $this->client);

        runDokuJob(dokuNotification($booking->kode, requestId: 'notif-1'));
        runDokuJob(dokuNotification($booking->kode, requestId: 'notif-2'));

        Event::assertDispatchedTimes(BookingConfirmed::class, 1);
        expect(Payment::where('booking_id', $booking->id)->count())->toBe(1);
    });

    it('amount tidak cocok → ditolak tanpa mengubah state', function () {
        $booking = prepareDokuBooking($this->mitra, $this->client);

        runDokuJob(dokuNotification($booking->kode, amount: 999_999));

        expect($booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT);
        Event::assertNotDispatched(BookingConfirmed::class);
    });

    it('status FAILED diabaikan (checkout user bisa ganti metode)', function () {
        $booking = prepareDokuBooking($this->mitra, $this->client);

        runDokuJob(dokuNotification($booking->kode, status: 'FAILED'));

        expect($booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT);
        Event::assertNotDispatched(BookingConfirmed::class);
    });

    it('payment tidak ditemukan → dilewati tanpa exception', function () {
        runDokuJob(dokuNotification('BKG-TIDAK-ADA'));

        Event::assertNotDispatched(BookingConfirmed::class);
        expect(true)->toBeTrue();
    });
});
