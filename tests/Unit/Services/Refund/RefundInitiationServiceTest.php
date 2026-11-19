<?php

use App\Enums\BookingStatus;
use App\Enums\RefundReason;
use App\Models\Refund;
use App\Models\User;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Refund\RefundInitiationService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);
    $this->service = new RefundInitiationService($this->notifier);

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
| HELPER
|==========================================================================
*/

function invokeEnsureStatusEligible(RefundInitiationService $service, $booking): void
{
    $ref = new ReflectionMethod($service, 'ensureBookingStatusEligible');
    $ref->setAccessible(true);
    $ref->invoke($service, $booking);
}

function invokeResolvePaidAmount(RefundInitiationService $service, $booking): float
{
    $ref = new ReflectionMethod($service, 'resolvePaidAmount');
    $ref->setAccessible(true);

    return $ref->invoke($service, $booking);
}

function invokeResolveRefundAmount(RefundInitiationService $service, ?float $amount, float $paid): float
{
    $ref = new ReflectionMethod($service, 'resolveRefundAmount');
    $ref->setAccessible(true);

    return $ref->invoke($service, $amount, $paid);
}

/*
|==========================================================================
| KATEGORI 1 — BVA (resolveRefundAmount via reflection)
|==========================================================================
*/

describe('BVA — resolveRefundAmount', function () {
    it('amount null → pakai paid', function () {
        expect(invokeResolveRefundAmount($this->service, null, 500_000))->toBe(500_000.0);
    });

    it('amount = paid → return paid', function () {
        expect(invokeResolveRefundAmount($this->service, 500_000, 500_000))->toBe(500_000.0);
    });

    it('amount = 0 → ValidationException', function () {
        expect(fn () => invokeResolveRefundAmount($this->service, 0.0, 500_000))
            ->toThrow(ValidationException::class);
    });

    it('amount negatif → ValidationException', function () {
        expect(fn () => invokeResolveRefundAmount($this->service, -100, 500_000))
            ->toThrow(ValidationException::class);
    });

    it('amount > paid → ValidationException', function () {
        expect(fn () => invokeResolveRefundAmount($this->service, 600_000, 500_000))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — EDGE CASES (resolvePaidAmount via reflection)
|==========================================================================
*/

describe('Edge Cases — resolvePaidAmount', function () {
    it('paid dari payment terakhir > 0 → return paid', function () {
        $booking = makeBookingMock(['total_harga' => '1000000.00']);

        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('orderByDesc')->with('verified_at')->andReturnSelf();
        $hasMany->shouldReceive('value')->with('jumlah')->andReturn('500000.00');
        $booking->shouldReceive('payments')->andReturn($hasMany);

        expect(invokeResolvePaidAmount($this->service, $booking))->toBe(500_000.0);
    });

    it('paid = null → fallback total_harga', function () {
        $booking = makeBookingMock(['total_harga' => '1000000.00']);

        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('orderByDesc')->andReturnSelf();
        $hasMany->shouldReceive('value')->andReturn(null);
        $booking->shouldReceive('payments')->andReturn($hasMany);

        expect(invokeResolvePaidAmount($this->service, $booking))->toBe(1_000_000.0);
    });

    it('paid = 0 → fallback total_harga', function () {
        $booking = makeBookingMock(['total_harga' => '1000000.00']);

        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('orderByDesc')->andReturnSelf();
        $hasMany->shouldReceive('value')->andReturn('0.00');
        $booking->shouldReceive('payments')->andReturn($hasMany);

        expect(invokeResolvePaidAmount($this->service, $booking))->toBe(1_000_000.0);
    });
});

/*
|==========================================================================
| KATEGORI 3 — ensureBookingStatusEligible (via reflection)
|==========================================================================
*/

describe('ensureBookingStatusEligible', function () {
    it('status PAYMENT_VERIFIED → lolos', function () {
        $booking = makeBookingMock(['status' => BookingStatus::PAYMENT_VERIFIED]);

        invokeEnsureStatusEligible($this->service, $booking);

        expect(true)->toBeTrue();
    });

    it('status CONFIRMED → lolos', function () {
        $booking = makeBookingMock(['status' => BookingStatus::CONFIRMED]);

        invokeEnsureStatusEligible($this->service, $booking);

        expect(true)->toBeTrue();
    });

    it('status IN_PROGRESS → lolos', function () {
        $booking = makeBookingMock(['status' => BookingStatus::IN_PROGRESS]);

        invokeEnsureStatusEligible($this->service, $booking);

        expect(true)->toBeTrue();
    });

    it('status PENDING_PAYMENT → ValidationException', function () {
        $booking = makeBookingMock(['status' => BookingStatus::PENDING_PAYMENT]);

        expect(fn () => invokeEnsureStatusEligible($this->service, $booking))
            ->toThrow(ValidationException::class);
    });

    it('status COMPLETED → ValidationException', function () {
        $booking = makeBookingMock(['status' => BookingStatus::COMPLETED]);

        expect(fn () => invokeEnsureStatusEligible($this->service, $booking))
            ->toThrow(ValidationException::class);
    });

    it('status CANCELLED → ValidationException', function () {
        $booking = makeBookingMock(['status' => BookingStatus::CANCELLED]);

        expect(fn () => invokeEnsureStatusEligible($this->service, $booking))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — initiate guard (public)
|==========================================================================
*/

describe('initiate — ownership guard', function () {
    it('mitra bukan pemilik → ValidationException', function () {
        $otherMitra = makeUser(['id' => 88]);
        $booking = makeBookingMock(['mitra_id' => 99]);

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
        $mitra = makeUser(['id' => 99]);
        $booking = makeBookingMock([
            'mitra_id' => 99,
            'status' => BookingStatus::PENDING_PAYMENT,
        ]);

        expect(fn () => $this->service->initiate(
            $booking,
            RefundReason::CANCELLATION,
            null,
            null,
            $mitra,
        ))->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 5 — initiate flow (SKIP — butuh Feature test)
|==========================================================================
*/

describe('initiate — flow', function () {
    it('happy path → Refund SUCCEEDED + Payment negatif + booking CANCELLED', function () {
        $this->markTestSkipped(
            'Refund::active() + Refund::create() + Payment::create() static '.
            'membutuhkan DB. Test full initiate di Feature test (RefreshDatabase).'
        );
    });
});

/*
|==========================================================================
| CATATAN: Feature test untuk persistence
|==========================================================================
|
|   it('initiate → Refund SUCCEEDED + Payment negatif + booking CANCELLED', function () {
|       $booking = Booking::factory()->create([
|           'status' => BookingStatus::CONFIRMED,
|           'total_harga' => 1_000_000,
|       ]);
|       $mitra = User::factory()->mitra()->create();
|       $booking->update(['mitra_id' => $mitra->id]);
|
|       $service = app(RefundInitiationService::class);
|       $refund = $service->initiate($booking, RefundReason::CANCELLATION, null, null, $mitra);
|
|       expect($refund->status)->toBe(RefundStatus::SUCCEEDED);
|       $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
|       $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'jumlah' => -1_000_000]);
|   });
|
*/
