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
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Booking\BookingCancellationService;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| BookingCancellationService — persistence `Refund::create` +
| `Payment::create` negatif + status booking CANCELLED.
|
| Yang di-test di sini (yang di-skip di unit test):
| - `cancelByMitra` (dengan refund) — `Refund::create` + `Payment`
|   negatif + booking CANCELLED + notifikasi.
| - `cancelByMitra` (tanpa refund) — booking CANCELLED saja.
| - `cancelByClient` — booking CANCELLED + notifikasi.
| - `expirePendingBookings` — auto-cancel + refund (jika ada).
|
| Catatan:
| - `NotificationDispatcherInterface` di-mock — fokus persistence.
| - `Log::channel('audit')` di-mock — `AuditLogger::writer()`.
| - `Storage::fake('local')` — isolasi file system.
| - `Event::fake()` — tidak perlu (tidak ada event dispatch).
|
*/

beforeEach(function () {
    Storage::fake('local');

    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);
    $this->notifier->shouldReceive('send')->byDefault()->andReturnNull();

    $this->service = new BookingCancellationService(
        app(BookingRepositoryInterface::class),
        $this->notifier,
    );

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
function makeBookingForCancellation(User $client, User $mitra, array $overrides = []): Booking
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => null,
            'catatan' => null,
            'total_harga' => 1_000_000,
        ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — CANCEL BY MITRA (DENGAN REFUND)
|==========================================================================
*/

describe('cancelByMitra — dengan refund', function () {
    it('cancelByMitra status CONFIRMED → Refund + Payment negatif', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        $result = $this->service->cancelByMitra($booking, $this->mitra, 'Cuaca buruk');

        // Refund tersimpan
        $this->assertDatabaseHas('refunds', [
            'booking_id' => $booking->id,
            'mitra_id' => $this->mitra->id,
            'amount' => 1_000_000,
            'status' => RefundStatus::PENDING->value,
            'reason' => RefundReason::CANCELLATION->value,
            'initiated_by_mitra' => $this->mitra->id,
        ]);

        // Payment negatif tersimpan
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'jumlah' => -1_000_000,
            'metode' => PaymentMetode::TRANSFER_MANUAL->value,
            'verified_by' => $this->mitra->id,
        ]);

        // Booking CANCELLED
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);

        expect($result->status)->toBe(BookingStatus::CANCELLED);
    });

    it('cancelByMitra status IN_PROGRESS → Refund + Payment negatif', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::IN_PROGRESS,
        ]);

        $this->service->cancelByMitra($booking, $this->mitra, 'Force majeure');

        $this->assertDatabaseHas('refunds', ['booking_id' => $booking->id]);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'jumlah' => -1_000_000,
        ]);
    });

    it('cancelByMitra status PAYMENT_VERIFIED → Refund + Payment negatif', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::PAYMENT_VERIFIED,
        ]);

        $this->service->cancelByMitra($booking, $this->mitra, 'Force majeure');

        $this->assertDatabaseHas('refunds', ['booking_id' => $booking->id]);
    });

    it('cancelByMitra → catatan di-append dengan timestamp', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
            'catatan' => 'Catatan lama',
        ]);

        $result = $this->service->cancelByMitra($booking, $this->mitra, 'Cuaca buruk');

        expect($result->catatan)
            ->toContain('Catatan lama')
            ->toContain('Dibatalkan oleh mitra: Cuaca buruk');
    });

    it('cancelByMitra → payment_proof dihapus', function () {
        Storage::disk('local')->put('payment_proofs/old.jpg', 'content');

        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
            'payment_proof_path' => 'payment_proofs/old.jpg',
        ]);

        $this->service->cancelByMitra($booking, $this->mitra, 'Cuaca buruk');

        Storage::disk('local')->assertMissing('payment_proofs/old.jpg');
    });

    it('cancelByMitra → notifikasi ke client dikirim', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->client->id),
                tipe: 'booking_cancelled',
                judul: 'Booking Dibatalkan oleh Mitra',
                pesan: Mockery::on(fn ($p) => str_contains($p, 'Cuaca buruk')),
                data: Mockery::any(),
            );

        $this->service->cancelByMitra($booking, $this->mitra, 'Cuaca buruk');
    });
});

/*
|==========================================================================
| KATEGORI 2 — CANCEL BY MITRA (TANPA REFUND)
|==========================================================================
*/

describe('cancelByMitra — tanpa refund', function () {
    it('cancelByMitra status PENDING_PAYMENT → cancel tanpa Refund', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::PENDING_PAYMENT,
        ]);

        $this->service->cancelByMitra($booking, $this->mitra, 'Cuaca buruk');

        // Booking CANCELLED
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);

        // TIDAK ada Refund
        $this->assertDatabaseMissing('refunds', ['booking_id' => $booking->id]);
    });

    it('cancelByMitra → status guard COMPLETED → ValidationException', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::COMPLETED,
        ]);

        expect(fn () => $this->service->cancelByMitra($booking, $this->mitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });

    it('cancelByMitra → status guard CANCELLED → ValidationException', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::CANCELLED,
        ]);

        expect(fn () => $this->service->cancelByMitra($booking, $this->mitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });

    it('cancelByMitra → mitra bukan owner → ValidationException', function () {
        $otherMitra = User::factory()->mitra()->create(['must_change_password' => false]);
        $booking = makeBookingForCancellation($this->client, $this->mitra);

        expect(fn () => $this->service->cancelByMitra($booking, $otherMitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 3 — CANCEL BY CLIENT
|==========================================================================
*/

describe('cancelByClient', function () {
    it('cancelByClient status PENDING_PAYMENT → booking CANCELLED', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::PENDING_PAYMENT,
        ]);

        $result = $this->service->cancelByClient($booking);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);

        expect($result->status)->toBe(BookingStatus::CANCELLED);
    });

    it('cancelByClient → notifikasi ke mitra dikirim', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->mitra->id),
                tipe: 'booking_cancelled',
                judul: 'Booking Dibatalkan oleh Client',
                pesan: Mockery::type('string'),
                data: Mockery::any(),
            );

        $this->service->cancelByClient($booking);
    });

    it('cancelByClient status CONFIRMED → ValidationException', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        expect(fn () => $this->service->cancelByClient($booking))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — EXPIRE PENDING BOOKINGS
|==========================================================================
*/

describe('expirePendingBookings — persistence', function () {
    it('expirePendingBookings 1 booking expired → count 1 + CANCELLED', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::PENDING_PAYMENT,
            'expired_at' => now()->subHours(2),
        ]);

        $result = $this->service->expirePendingBookings();

        expect($result)->toBe(1);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);
    });

    it('expirePendingBookings 2 booking expired → count 2', function () {
        makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::PENDING_PAYMENT,
            'expired_at' => now()->subHours(2),
        ]);
        makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::PENDING_PAYMENT,
            'expired_at' => now()->subHours(3),
        ]);

        $result = $this->service->expirePendingBookings();

        expect($result)->toBe(2);
    });

    it('expirePendingBookings → notifikasi ke client dikirim', function () {
        makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::PENDING_PAYMENT,
            'expired_at' => now()->subHours(2),
        ]);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->client->id),
                tipe: 'booking_cancelled',
                judul: 'Booking Expired',
                pesan: Mockery::type('string'),
                data: Mockery::any(),
            );

        $this->service->expirePendingBookings();
    });
});

/*
|==========================================================================
| KATEGORI 5 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('cancelByMitra 2x → refund hanya 1 (idempoten via status guard)', function () {
        $booking = makeBookingForCancellation($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        $this->service->cancelByMitra($booking, $this->mitra, 'Alasan');

        // Coba cancel lagi — status sudah CANCELLED → error
        expect(fn () => $this->service->cancelByMitra($booking->fresh(), $this->mitra, 'Alasan'))
            ->toThrow(ValidationException::class);

        // Refund hanya 1
        expect(Refund::where('booking_id', $booking->id)->count())->toBe(1);
        expect(Payment::where('booking_id', $booking->id)->where('jumlah', '<', 0)->count())->toBe(1);
    });
});
