<?php

use App\Enums\BookingStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Refund\RefundApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Cakupan:
| - `approve` guard: status, ownership.
| - `reject` guard: status, ownership.
| - `reject` flow: mock `$refund->update` + `refresh` + `load` (bisa).
| - `approve` flow: SKIP (static Payment::create + booking->update).
|
*/

beforeEach(function () {
    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);
    $this->refundRepo = Mockery::mock(RefundRepositoryInterface::class);
    $this->bookingRepo = Mockery::mock(BookingRepositoryInterface::class);

    $this->service = new RefundApprovalService(
        $this->refundRepo,
        $this->bookingRepo,
        $this->notifier,
    );

    // Lock baris: default null => service memakai model yang diberikan.
    $this->refundRepo->shouldReceive('findByIdForUpdate')->byDefault()->andReturnNull();
    $this->bookingRepo->shouldReceive('findByIdForUpdate')->byDefault()->andReturnNull();

    DB::shouldReceive('transaction')
        ->byDefault()
        ->andReturnUsing(fn ($callback) => $callback());

    $auditLoggerMock = Mockery::mock(LoggerInterface::class);
    $auditLoggerMock->shouldReceive('info')->byDefault()->andReturnNull();

    Log::shouldReceive('channel')->with('audit')->byDefault()->andReturn($auditLoggerMock);
    Log::shouldReceive('driver')->byDefault()->andReturn($auditLoggerMock);
    Log::shouldReceive('info')->byDefault()->andReturnNull();
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
    Log::shouldReceive('error')->byDefault()->andReturnNull();
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| HELPER — mock Refund dengan relasi
|==========================================================================
*/

function makeRefundMockForApproval(array $attrs = [], $booking = null, $client = null): MockInterface
{
    $refund = Mockery::mock(Refund::class)->makePartial();
    $refund->forceFill(array_merge([
        'id' => 1,
        'booking_id' => 10,
        'mitra_id' => 99,
        'amount' => '500000.00',
        'currency' => 'IDR',
        'reason' => RefundReason::CANCELLATION,
        'status' => RefundStatus::PENDING,
        'catatan' => null,
        'initiated_by_mitra' => 99,
    ], $attrs));

    if ($booking !== null) {
        $refund->setRelation('booking', $booking);
    }

    return $refund;
}

/*
|==========================================================================
| KATEGORI 1 — approve guard
|==========================================================================
*/

describe('approve — status guard', function () {
    it('status bukan PENDING → ValidationException', function () {
        $mitra = makeUser(['id' => 99]);
        $refund = makeRefundMockForApproval(['status' => RefundStatus::SUCCEEDED]);

        expect(fn () => $this->service->approve($refund, $mitra, null))
            ->toThrow(ValidationException::class);
    });

    it('status CANCELLED → ValidationException', function () {
        $mitra = makeUser(['id' => 99]);
        $refund = makeRefundMockForApproval(['status' => RefundStatus::CANCELLED]);

        expect(fn () => $this->service->approve($refund, $mitra, null))
            ->toThrow(ValidationException::class);
    });
});

describe('approve — ownership guard', function () {
    it('mitra bukan pemilik refund → ValidationException', function () {
        $otherMitra = makeUser(['id' => 88]);
        $refund = makeRefundMockForApproval(['mitra_id' => 99]);

        expect(fn () => $this->service->approve($refund, $otherMitra, null))
            ->toThrow(ValidationException::class);
    });

    it('booking null → ValidationException', function () {
        $mitra = makeUser(['id' => 99]);
        $refund = makeRefundMockForApproval(['mitra_id' => 99]);
        $refund->setRelation('booking', null);

        expect(fn () => $this->service->approve($refund, $mitra, null))
            ->toThrow(ValidationException::class);
    });

    it('booking milik mitra lain → ValidationException', function () {
        $mitra = makeUser(['id' => 99]);
        $booking = makeBookingMock(['id' => 10, 'mitra_id' => 88]);
        $refund = makeRefundMockForApproval(['mitra_id' => 99], $booking);

        expect(fn () => $this->service->approve($refund, $mitra, null))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — reject guard
|==========================================================================
*/

describe('reject — status guard', function () {
    it('status bukan PENDING → ValidationException', function () {
        $mitra = makeUser(['id' => 99]);
        $refund = makeRefundMockForApproval(['status' => RefundStatus::SUCCEEDED]);

        expect(fn () => $this->service->reject($refund, $mitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });
});

describe('reject — ownership guard', function () {
    it('mitra bukan pemilik refund → ValidationException', function () {
        $otherMitra = makeUser(['id' => 88]);
        $refund = makeRefundMockForApproval(['mitra_id' => 99]);

        expect(fn () => $this->service->reject($refund, $otherMitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 3 — reject flow (bisa, tanpa static)
|==========================================================================
*/

describe('reject — flow', function () {
    it('sukses → $refund->update status CANCELLED + notif client', function () {
        $mitra = makeUser(['id' => 99]);
        $client = makeUser(['id' => 10]);

        $booking = makeBookingMock(['id' => 10, 'mitra_id' => 99]);
        $booking->setRelation('client', $client);

        $refund = makeRefundMockForApproval(
            ['id' => 1, 'booking_id' => 10, 'mitra_id' => 99, 'status' => RefundStatus::PENDING, 'catatan' => null],
            $booking,
        );

        $refreshed = makeRefundMockForApproval(
            ['id' => 1, 'status' => RefundStatus::CANCELLED],
            $booking,
        );

        $refund->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn ($p) => $p['status'] === RefundStatus::CANCELLED->value
                && str_contains($p['catatan'], '[Rejected]')))
            ->andReturn(true);

        $refund->shouldReceive('refresh')->once()->andReturn($refreshed);
        $refreshed->shouldReceive('load')->once()->with('booking.client')->andReturnSelf();

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: $client,
                tipe: 'refund_rejected',
                judul: 'Pengajuan Refund Ditolak',
                pesan: Mockery::on(fn ($p) => str_contains($p, 'Alasan: Data rekening salah')),
                data: Mockery::any(),
            );

        $result = $this->service->reject($refund, $mitra, 'Data rekening salah');

        expect($result)->toBe($refreshed);
    });
});

/*
|==========================================================================
| KATEGORI 4 — approve flow (SKIP — static)
|==========================================================================
*/

describe('approve — flow', function () {
    it('happy path → Refund SUCCEEDED + Payment negatif + booking CANCELLED', function () {
        $this->markTestSkipped(
            'Payment::create() static + $booking->update() (dengan relasi client) '.
            'butuh DB. Test full approve di Feature test (RefreshDatabase).'
        );
    });
});

/*
|==========================================================================
| CATATAN: Feature test untuk persistence
|==========================================================================
|
|   it('approve → Refund SUCCEEDED + Payment negatif + booking CANCELLED', function () {
|       $booking = Booking::factory()->create([
|           'status' => BookingStatus::CONFIRMED,
|           'total_harga' => 1_000_000,
|       ]);
|       $mitra = User::factory()->mitra()->create();
|       $booking->update(['mitra_id' => $mitra->id]);
|       $refund = Refund::factory()->create([
|           'booking_id' => $booking->id,
|           'mitra_id' => $mitra->id,
|           'amount' => 500_000,
|           'status' => RefundStatus::PENDING,
|       ]);
|
|       $service = app(RefundApprovalService::class);
|       $service->approve($refund, $mitra, 'Approved');
|
|       $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => 'SUCCEEDED']);
|       $this->assertDatabaseHas('payments', [
|           'booking_id' => $booking->id,
|           'jumlah' => -500_000,
|       ]);
|       $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
|   });
|
*/
