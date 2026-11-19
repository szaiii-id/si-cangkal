<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Booking\BookingCancellationService;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    Storage::fake('local');

    $this->bookingRepo = Mockery::mock(BookingRepositoryInterface::class);
    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);

    $this->service = new BookingCancellationService($this->bookingRepo, $this->notifier);

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

function invokeMergeCatatan(BookingCancellationService $service, ?string $existing, string $new): string
{
    $ref = new ReflectionMethod($service, 'mergeCatatan');
    $ref->setAccessible(true);

    return $ref->invoke($service, $existing, $new);
}

function invokeDeletePaymentProof(BookingCancellationService $service, ?string $path): void
{
    $ref = new ReflectionMethod($service, 'deletePaymentProof');
    $ref->setAccessible(true);
    $ref->invoke($service, $path);
}

/*
|==========================================================================
| KATEGORI 1 — BVA
|==========================================================================
*/

describe('BVA — cancelByClient status', function () {
    it('status confirmed → ValidationException', function () {
        $booking = makeBookingMock(['status' => BookingStatus::CONFIRMED]);

        expect(fn () => $this->service->cancelByClient($booking))
            ->toThrow(ValidationException::class);
    });

    it('status completed → ValidationException', function () {
        $booking = makeBookingMock(['status' => BookingStatus::COMPLETED]);

        expect(fn () => $this->service->cancelByClient($booking))
            ->toThrow(ValidationException::class);
    });
});

describe('BVA — cancelByClient payment guard', function () {
    it('ada payment terverifikasi → ValidationException', function () {
        $booking = makeBookingMock(['status' => BookingStatus::PENDING_PAYMENT]);
        $booking->shouldReceive('payments')->andReturnUsing(function () {
            $hasMany = Mockery::mock(HasMany::class);
            $hasMany->shouldReceive('whereNotNull')->with('verified_at')->andReturnSelf();
            $hasMany->shouldReceive('exists')->andReturn(true);

            return $hasMany;
        });

        expect(fn () => $this->service->cancelByClient($booking))
            ->toThrow(ValidationException::class);
    });
});

describe('BVA — cancelByMitra status', function () {
    it('status completed → ValidationException', function () {
        $mitra = makeUser(['id' => 99]);
        $booking = makeBookingMock(['status' => BookingStatus::COMPLETED, 'mitra_id' => 99]);

        expect(fn () => $this->service->cancelByMitra($booking, $mitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });

    it('status cancelled → ValidationException', function () {
        $mitra = makeUser(['id' => 99]);
        $booking = makeBookingMock(['status' => BookingStatus::CANCELLED, 'mitra_id' => 99]);

        expect(fn () => $this->service->cancelByMitra($booking, $mitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — EQUIVALENCE
|==========================================================================
*/

describe('Equivalence — cancelByMitra ownership', function () {
    it('mitra bukan pemilik → ValidationException', function () {
        $otherMitra = makeUser(['id' => 88]);
        $booking = makeBookingMock(['status' => BookingStatus::PENDING_PAYMENT, 'mitra_id' => 99]);

        expect(fn () => $this->service->cancelByMitra($booking, $otherMitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 3 — HAPPY PATH
|==========================================================================
*/

describe('Happy Path — cancelByClient', function () {
    it('sukses → update status cancelled + notif mitra', function () {
        $client = makeUser(['id' => 10]);
        $mitra = makeUser(['id' => 99]);

        $booking = makeBookingMock([
            'id' => 1,
            'kode' => 'BKG-2026-001',
            'user_id' => 10,
            'mitra_id' => 99,
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => null,
            'catatan' => null,
        ]);
        $booking->setRelation('mitra', $mitra);
        $booking->setRelation('client', $client);

        $booking->shouldReceive('payments')->andReturnUsing(function () {
            $hasMany = Mockery::mock(HasMany::class);
            $hasMany->shouldReceive('whereNotNull')->andReturnSelf();
            $hasMany->shouldReceive('exists')->andReturn(false);

            return $hasMany;
        });

        $updated = makeBookingMock([
            'id' => 1,
            'kode' => 'BKG-2026-001',
            'status' => BookingStatus::CANCELLED,
        ]);
        $updated->setRelation('mitra', $mitra);
        $updated->setRelation('client', $client);

        $this->bookingRepo->shouldReceive('update')
            ->once()
            ->with($booking, Mockery::on(fn ($p) => $p['status'] === BookingStatus::CANCELLED->value))
            ->andReturn($updated);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: $mitra,
                tipe: 'booking_cancelled',
                judul: 'Booking Dibatalkan oleh Client',
                pesan: Mockery::any(),
                data: Mockery::any(),
            );

        $result = $this->service->cancelByClient($booking);

        expect($result)->toBe($updated);
    });
});

describe('Happy Path — cancelByMitra (tanpa refund)', function () {
    it('status pending_payment → cancel tanpa refund', function () {
        $mitra = makeUser(['id' => 99]);

        $booking = makeBookingMock([
            'id' => 1,
            'kode' => 'BKG-2026-001',
            'mitra_id' => 99,
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => null,
            'catatan' => null,
            'total_harga' => '1000000.00',
        ]);

        $updated = makeBookingMock(['id' => 1, 'kode' => 'BKG-2026-001', 'status' => BookingStatus::CANCELLED]);
        $updated->setRelation('client', makeUser(['id' => 10]));

        $this->bookingRepo->shouldReceive('update')
            ->once()
            ->with($booking, Mockery::on(fn ($p) => $p['status'] === BookingStatus::CANCELLED->value
                && str_contains($p['catatan'], 'Dibatalkan oleh mitra: Cuaca buruk')))
            ->andReturn($updated);

        $this->notifier->shouldReceive('send')->once();

        $result = $this->service->cancelByMitra($booking, $mitra, 'Cuaca buruk');

        expect($result)->toBe($updated);
    });
});

describe('Happy Path — cancelByMitra (dengan refund)', function () {
    it('status confirmed → Refund::create + Payment::create', function () {
        $this->markTestSkipped(
            'Refund::create() + Payment::create() static membutuhkan DB. '.
            'Test full cancelByMitra (refund) di Feature test (RefreshDatabase).'
        );
    });
});

/*
|==========================================================================
| KATEGORI 4 — EXPIRE PENDING BOOKINGS
|==========================================================================
*/

describe('expirePendingBookings', function () {
    it('tidak ada expired → count 0', function () {
        $this->bookingRepo->shouldReceive('findExpired')->once()->andReturn(new Collection);

        $result = $this->service->expirePendingBookings();

        expect($result)->toBe(0);
    });

    it('1 expired → cancel + count 1', function () {
        $booking = makeBookingMock([
            'id' => 1,
            'kode' => 'BKG-2026-001',
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => null,
            'catatan' => null,
        ]);

        $updated = makeBookingMock(['id' => 1]);
        $updated->setRelation('client', makeUser(['id' => 10]));

        $this->bookingRepo->shouldReceive('findExpired')->once()->andReturn(new Collection([$booking]));
        $this->bookingRepo->shouldReceive('update')->once()->andReturn($updated);
        $this->notifier->shouldReceive('send')->once();

        $result = $this->service->expirePendingBookings();

        expect($result)->toBe(1);
    });

    it('error di 1 booking → log error, lanjut booking lain', function () {
        $booking1 = makeBookingMock(['id' => 1, 'kode' => 'BKG-1']);
        $booking2 = makeBookingMock(['id' => 2, 'kode' => 'BKG-2']);

        $this->bookingRepo->shouldReceive('findExpired')
            ->once()
            ->andReturn(new Collection([$booking1, $booking2]));

        $this->bookingRepo->shouldReceive('update')
            ->with($booking1, Mockery::any())
            ->andThrow(new RuntimeException('DB error'));

        $updated = makeBookingMock(['id' => 2]);
        $updated->setRelation('client', makeUser(['id' => 10]));
        $this->bookingRepo->shouldReceive('update')
            ->with($booking2, Mockery::any())
            ->andReturn($updated);

        Log::shouldReceive('error')->once();
        $this->notifier->shouldReceive('send')->once();

        $result = $this->service->expirePendingBookings();

        expect($result)->toBe(1);
    });
});

/*
|==========================================================================
| KATEGORI 5 — HELPER
|==========================================================================
*/

describe('Helper — mergeCatatan', function () {
    it('catatan null → new line dengan timestamp', function () {
        $result = invokeMergeCatatan($this->service, null, 'Alasan');

        expect($result)->toMatch('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}\] Alasan$/');
    });

    it('catatan ada → append new line', function () {
        $result = invokeMergeCatatan($this->service, 'Catatan lama', 'Alasan');

        expect($result)->toContain('Catatan lama')
            ->and($result)->toContain('Alasan')
            ->and($result)->toContain("\n");
    });

    it('catatan empty string → new line saja', function () {
        $result = invokeMergeCatatan($this->service, '', 'Alasan');

        expect($result)->toMatch('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}\] Alasan$/');
    });
});

describe('Helper — deletePaymentProof', function () {
    it('path null → tidak error', function () {
        invokeDeletePaymentProof($this->service, null);

        expect(true)->toBeTrue();
    });

    it('path empty string → tidak error', function () {
        invokeDeletePaymentProof($this->service, '');

        expect(true)->toBeTrue();
    });

    it('file ada di storage → dihapus', function () {
        Storage::disk('local')->put('payment_proofs/old.jpg', 'content');
        Storage::disk('local')->assertExists('payment_proofs/old.jpg');

        invokeDeletePaymentProof($this->service, 'payment_proofs/old.jpg');

        Storage::disk('local')->assertMissing('payment_proofs/old.jpg');
    });

    it('file tidak ada di storage → tidak error', function () {
        invokeDeletePaymentProof($this->service, 'payment_proofs/tidak_ada.jpg');

        expect(true)->toBeTrue();
    });
});

/*
|==========================================================================
| CATATAN: Feature test untuk cancelByMitra refund
|==========================================================================
|
|   it('cancelByMitra status confirmed → Refund + Payment tercipta', function () {
|       $booking = Booking::factory()->create(['status' => BookingStatus::CONFIRMED]);
|       $mitra = User::factory()->mitra()->create();
|       $booking->update(['mitra_id' => $mitra->id]);
|
|       $service = app(BookingCancellationService::class);
|       $service->cancelByMitra($booking, $mitra, 'Force majeure');
|
|       $this->assertDatabaseHas('refunds', ['booking_id' => $booking->id]);
|       $this->assertDatabaseHas('payments', [
|           'booking_id' => $booking->id,
|           'jumlah' => -1000000,
|       ]);
|   });
|
*/
