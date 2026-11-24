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
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Refund\RefundApprovalService;
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
| RefundApprovalService — persistence `Payment::create` negatif +
| `$booking->update()` + `$refund->update()`.
|
| Yang di-test di sini (yang di-skip di unit test):
| - `approve` — Refund SUCCEEDED + Payment negatif + booking CANCELLED +
|   notifikasi.
| - `reject` — Refund CANCELLED + catatan + notifikasi (sudah di unit
|   test, tapi di sini verifikasi DB langsung).
|
| Catatan:
| - `NotificationDispatcherInterface` di-mock — fokus persistence.
| - `Log::channel('audit')` di-mock — `AuditLogger::writer()`.
| - Service `final` — tapi test pakai real service + real DB (tidak mock).
|
*/

beforeEach(function () {
    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);
    $this->notifier->shouldReceive('send')->byDefault()->andReturnNull();

    $this->service = new RefundApprovalService(
        app(RefundRepositoryInterface::class),
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
| HELPER — buat booking + refund
|==========================================================================
*/
function makeBookingWithRefund(User $client, User $mitra, array $bookingOverrides = [], array $refundOverrides = []): array
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED,
            'total_harga' => 1_000_000,
            'catatan' => null,
        ], $bookingOverrides));

    $refund = Refund::factory()->create(array_merge([
        'booking_id' => $booking->id,
        'mitra_id' => $mitra->id,
        'amount' => 500_000,
        'currency' => 'IDR',
        'reason' => RefundReason::CANCELLATION->value,
        'status' => RefundStatus::PENDING->value,
        'initiated_by_mitra' => $mitra->id,
        'catatan' => null,
    ], $refundOverrides));

    return [$booking, $refund];
}

/*
|==========================================================================
| KATEGORI 1 — APPROVE (PERSISTENCE)
|==========================================================================
*/

describe('approve — persistence', function () {
    it('approve → Refund SUCCEEDED', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $result = $this->service->approve($refund, $this->mitra, 'Approved');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::SUCCEEDED->value,
        ]);

        expect($result->status)->toBe(RefundStatus::SUCCEEDED);
    });

    it('approve → Payment negatif tersimpan', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $this->service->approve($refund, $this->mitra, 'Approved');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'jumlah' => -500_000,
            'metode' => PaymentMetode::TRANSFER_MANUAL->value,
            'verified_by' => $this->mitra->id,
        ]);
    });

    it('approve → booking CANCELLED', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $this->service->approve($refund, $this->mitra, 'Approved');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);
    });

    it('approve → catatan refund di-append [Approved]', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra, [], [
            'catatan' => 'Catatan lama',
        ]);

        $result = $this->service->approve($refund, $this->mitra, 'Alasan approved');

        expect($result->catatan)
            ->toContain('Catatan lama')
            ->toContain('[Approved]')
            ->toContain('Alasan approved');
    });

    it('approve tanpa catatan → [Approved] tanpa alasan', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $result = $this->service->approve($refund, $this->mitra, null);

        expect($result->catatan)->toContain('[Approved]')
            ->and($result->catatan)->not->toContain(' - ');
    });

    it('approve → notifikasi ke client dikirim', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->client->id),
                tipe: 'refund_succeeded',
                judul: 'Refund Berhasil',
                pesan: Mockery::type('string'),
                data: Mockery::on(fn ($d) => isset($d['refund_id']) && isset($d['booking_id'])),
            );

        $this->service->approve($refund, $this->mitra, 'Approved');
    });
});

/*
|==========================================================================
| KATEGORI 2 — REJECT (PERSISTENCE)
|==========================================================================
*/

describe('reject — persistence', function () {
    it('reject → Refund CANCELLED', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $result = $this->service->reject($refund, $this->mitra, 'Data rekening salah');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::CANCELLED->value,
        ]);

        expect($result->status)->toBe(RefundStatus::CANCELLED);
    });

    it('reject → catatan refund di-append [Rejected] + alasan', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $result = $this->service->reject($refund, $this->mitra, 'Data rekening salah');

        expect($result->catatan)
            ->toContain('[Rejected]')
            ->toContain('Data rekening salah');
    });

    it('reject → booking TIDAK berubah (tetap CONFIRMED)', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $this->service->reject($refund, $this->mitra, 'Data rekening salah');

        $booking->refresh();
        expect($booking->status)->toBe(BookingStatus::CONFIRMED);
    });

    it('reject → TIDAK membuat Payment', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $countBefore = Payment::where('booking_id', $booking->id)->count();

        $this->service->reject($refund, $this->mitra, 'Data rekening salah');

        expect(Payment::where('booking_id', $booking->id)->count())->toBe($countBefore);
    });

    it('reject → notifikasi ke client dikirim', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->client->id),
                tipe: 'refund_rejected',
                judul: 'Pengajuan Refund Ditolak',
                pesan: Mockery::on(fn ($p) => str_contains($p, 'Data rekening salah')),
                data: Mockery::any(),
            );

        $this->service->reject($refund, $this->mitra, 'Data rekening salah');
    });
});

/*
|==========================================================================
| KATEGORI 3 — GUARD (STATUS & OWNERSHIP)
|==========================================================================
*/

describe('approve — status guard', function () {
    it('status SUCCEEDED → ValidationException', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra, [], [
            'status' => RefundStatus::SUCCEEDED->value,
        ]);

        expect(fn () => $this->service->approve($refund, $this->mitra, 'Approved'))
            ->toThrow(ValidationException::class);
    });

    it('status CANCELLED → ValidationException', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra, [], [
            'status' => RefundStatus::CANCELLED->value,
        ]);

        expect(fn () => $this->service->approve($refund, $this->mitra, 'Approved'))
            ->toThrow(ValidationException::class);
    });
});

describe('approve — ownership guard', function () {
    it('mitra bukan owner refund → ValidationException', function () {
        $otherMitra = User::factory()->mitra()->create(['must_change_password' => false]);
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        expect(fn () => $this->service->approve($refund, $otherMitra, 'Approved'))
            ->toThrow(ValidationException::class);
    });

    it('booking milik mitra lain → ValidationException', function () {
        $otherMitra = User::factory()->mitra()->create(['must_change_password' => false]);
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        // Ubah booking mitra_id jadi otherMitra
        $booking->update(['mitra_id' => $otherMitra->id]);

        expect(fn () => $this->service->approve($refund->fresh(), $this->mitra, 'Approved'))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('approve 2x → refund kedua ditolak (status guard)', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $this->service->approve($refund, $this->mitra, 'Approved');

        expect(fn () => $this->service->approve($refund->fresh(), $this->mitra, 'Approved lagi'))
            ->toThrow(ValidationException::class);

        // Payment hanya 1 (tidak double)
        expect(Payment::where('booking_id', $booking->id)->where('jumlah', '<', 0)->count())->toBe(1);
    });

    it('reject 2x → refund kedua ditolak (status guard)', function () {
        [$booking, $refund] = makeBookingWithRefund($this->client, $this->mitra);

        $this->service->reject($refund, $this->mitra, 'Alasan 1');

        expect(fn () => $this->service->reject($refund->fresh(), $this->mitra, 'Alasan 2'))
            ->toThrow(ValidationException::class);
    });
});
