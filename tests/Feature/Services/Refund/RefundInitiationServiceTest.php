<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMetode;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Refund\RefundInitiationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| RefundInitiationService — persistence `Refund::create` +
| `Payment::create` negatif + booking CANCELLED.
|
| Yang di-test di sini (yang di-skip di unit test):
| - `initiate` — `Refund::create` SUCCEEDED + `Payment` negatif +
|   booking CANCELLED + notifikasi.
| - `Refund::active()` — query untuk cek refund aktif.
| - `resolvePaidAmount` — dari `payments` terakhir (DB).
|
| Catatan:
| - `NotificationDispatcherInterface` di-mock — fokus persistence.
| - `Log::channel('audit')` di-mock — `AuditLogger::writer()`.
|
*/

beforeEach(function () {
    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);
    $this->notifier->shouldReceive('send')->byDefault()->andReturnNull();

    $this->service = new RefundInitiationService($this->notifier);

    // Mock Log::channel('audit') — AuditLogger::writer()
    $auditLoggerMock = Mockery::mock(LoggerInterface::class);
    $auditLoggerMock->shouldReceive('info')->byDefault()->andReturnNull();

    Log::shouldReceive('channel')->with('audit')->byDefault()->andReturn($auditLoggerMock);
    Log::shouldReceive('driver')->byDefault()->andReturn($auditLoggerMock);

    $this->client = User::factory()->client()->create([
        'name' => 'Budi Client',
        'must_change_password' => false,
    ]);

    $this->mitra = User::factory()->mitra()->create([
        'name' => 'Mitra Travel',
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| HELPER — buat booking
|==========================================================================
*/
function makeBookingForRefund(User $client, User $mitra, array $overrides = []): Booking
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED,
            'total_harga' => 1_000_000,
            'catatan' => null,
        ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — INITIATE (PERSISTENCE)
|==========================================================================
*/

describe('initiate — persistence', function () {
    it('initiate → Refund AWAITING_PROOF tersimpan di DB', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        $refund = $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            'Cuaca buruk',
            $this->mitra,
        );

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'booking_id' => $booking->id,
            'mitra_id' => $this->mitra->id,
            'amount' => 1_000_000,
            'status' => RefundStatus::AWAITING_PROOF->value,
            'reason' => RefundReason::CANCELLATION->value,
            'initiated_by_mitra' => $this->mitra->id,
        ]);

        expect($refund->status)->toBe(RefundStatus::AWAITING_PROOF)
            ->and($refund->catatan)->toContain('Cuaca buruk');
    });

    it('initiate → belum ada Payment negatif sebelum bukti diunggah', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        );

        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id,
            'jumlah' => -1_000_000,
        ]);
    });

    it('initiate → booking tetap CONFIRMED', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        );

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);
    });

    it('initiate → catatan booking belum diubah', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra, [
            'catatan' => 'Catatan lama',
        ]);

        $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            'Alasan refund',
            $this->mitra,
        );

        $booking->refresh();
        expect($booking->catatan)->toBe('Catatan lama');
    });

    it('initiate → notifikasi sukses belum dikirim ke client', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        $this->notifier->shouldNotReceive('send');

        $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        );
    });
});

/*
|==========================================================================
| KATEGORI 2 — INITIATE AMOUNT
|==========================================================================
*/

describe('initiate — amount', function () {
    it('initiate amount null → pakai paid amount dari payment terakhir', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra, [
            'total_harga' => 1_000_000,
        ]);

        // Buat payment sebelumnya — 500.000
        Payment::create([
            'booking_id' => $booking->id,
            'jumlah' => 500_000,
            'metode' => PaymentMetode::TRANSFER_MANUAL->value,
            'verified_by' => $this->mitra->id,
            'verified_at' => now(),
        ]);

        $refund = $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        );

        expect((float) $refund->amount)->toBe(500_000.0);
    });

    it('initiate amount eksplisit → pakai amount', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        $refund = $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            300_000,
            null,
            $this->mitra,
        );

        expect((float) $refund->amount)->toBe(300_000.0);
    });

    it('initiate amount > paid → ValidationException', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        expect(fn () => $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            2_000_000,  // > total_harga 1.000.000
            null,
            $this->mitra,
        ))->toThrow(ValidationException::class);
    });

    it('initiate amount 0 → ValidationException', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        expect(fn () => $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            0.0,
            null,
            $this->mitra,
        ))->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 3 — INITIATE GUARD
|==========================================================================
*/

describe('initiate — ownership guard', function () {
    it('mitra bukan owner → ValidationException', function () {
        $otherMitra = User::factory()->mitra()->create(['must_change_password' => false]);
        $booking = makeBookingForRefund($this->client, $this->mitra);

        expect(fn () => $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $otherMitra,
        ))->toThrow(ValidationException::class);
    });
});

describe('initiate — status guard', function () {
    it('status PENDING_PAYMENT → ValidationException', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra, [
            'status' => BookingStatus::PENDING_PAYMENT,
        ]);

        expect(fn () => $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        ))->toThrow(ValidationException::class);
    });

    it('status COMPLETED → ValidationException', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra, [
            'status' => BookingStatus::COMPLETED,
        ]);

        expect(fn () => $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        ))->toThrow(ValidationException::class);
    });

    it('status CANCELLED → ValidationException', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra, [
            'status' => BookingStatus::CANCELLED,
        ]);

        expect(fn () => $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        ))->toThrow(ValidationException::class);
    });
});

describe('initiate — refund aktif guard', function () {
    it('sudah ada refund PENDING → ValidationException', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        Refund::factory()->create([
            'booking_id' => $booking->id,
            'mitra_id' => $this->mitra->id,
            'status' => RefundStatus::PENDING->value,
            'amount' => 500_000,
        ]);

        expect(fn () => $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        ))->toThrow(ValidationException::class);
    });

    it('sudah ada refund SUCCEEDED → ValidationException', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        Refund::factory()->succeeded()->create([
            'booking_id' => $booking->id,
            'mitra_id' => $this->mitra->id,
            'amount' => 500_000,
        ]);

        expect(fn () => $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        ))->toThrow(ValidationException::class);
    });

    it('refund CANCELLED → TIDAK menghalangi initiate', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        Refund::factory()->create([
            'booking_id' => $booking->id,
            'mitra_id' => $this->mitra->id,
            'status' => RefundStatus::CANCELLED->value,
            'amount' => 500_000,
        ]);

        $refund = $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        );

        expect($refund->status)->toBe(RefundStatus::AWAITING_PROOF);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('initiate 2x → refund kedua ditolak (refund aktif guard)', function () {
        $booking = makeBookingForRefund($this->client, $this->mitra);

        $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        );

        expect(fn () => $this->service->initiate(
            $booking->fresh(),
            RefundReason::CANCELLATION,
            null,
            null,
            $this->mitra,
        ))->toThrow(ValidationException::class);

        expect(Refund::where('booking_id', $booking->id)->count())->toBe(1);
    });
});
