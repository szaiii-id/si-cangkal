<?php

use App\Enums\BookingStatus;
use App\Events\BookingConfirmed;
use App\Models\Payment;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Booking\BookingPaymentService;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

    $this->service = new BookingPaymentService($this->bookingRepo, $this->notifier);

    DB::shouldReceive('transaction')
        ->byDefault()
        ->andReturnUsing(fn ($callback) => $callback());

    // Lock baris: default null => service memakai booking yang diberikan.
    // Test verifyPayment meng-override dengan booking yang sama.
    $this->bookingRepo->shouldReceive('findByIdForUpdate')
        ->byDefault()
        ->andReturnNull();

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
| KATEGORI 1 — BVA
|==========================================================================
*/

describe('BVA — uploadProof status guard', function () {
    it('status bukan pending_payment → ValidationException', function () {
        $booking = makeBookingMock(['status' => BookingStatus::CONFIRMED]);

        expect(fn () => $this->service->uploadProof($booking, UploadedFile::fake()->image('proof.jpg')))
            ->toThrow(ValidationException::class);
    });
});

describe('BVA — verifyPayment status guard', function () {
    it('status bukan pending_payment → ValidationException', function () {
        $mitra = makeUser(['id' => 99, 'role' => 'mitra']);
        $booking = makeBookingMock(['status' => BookingStatus::CONFIRMED, 'mitra_id' => 99]);

        $this->bookingRepo->shouldReceive('findByIdForUpdate')
            ->once()->with($booking->id)->andReturn($booking);

        expect(fn () => $this->service->verifyPayment($booking, $mitra))
            ->toThrow(ValidationException::class);
    });
});

describe('BVA — rejectPayment status guard', function () {
    it('status bukan pending_payment → ValidationException', function () {
        $mitra = makeUser(['id' => 99, 'role' => 'mitra']);
        $booking = makeBookingMock(['status' => BookingStatus::CONFIRMED, 'mitra_id' => 99]);

        expect(fn () => $this->service->rejectPayment($booking, $mitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — EQUIVALENCE
|==========================================================================
*/

describe('Equivalence — ownership', function () {
    it('verifyPayment: mitra bukan pemilik → ValidationException', function () {
        $otherMitra = makeUser(['id' => 88]);
        $booking = makeBookingMock(['mitra_id' => 99, 'status' => BookingStatus::PENDING_PAYMENT]);

        $this->bookingRepo->shouldReceive('findByIdForUpdate')
            ->once()->with($booking->id)->andReturn($booking);

        expect(fn () => $this->service->verifyPayment($booking, $otherMitra))
            ->toThrow(ValidationException::class);
    });

    it('rejectPayment: mitra bukan pemilik → ValidationException', function () {
        $otherMitra = makeUser(['id' => 88]);
        $booking = makeBookingMock(['mitra_id' => 99, 'status' => BookingStatus::PENDING_PAYMENT]);

        expect(fn () => $this->service->rejectPayment($booking, $otherMitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 3 — HAPPY PATH
|==========================================================================
*/

describe('Happy Path — uploadProof', function () {
    it('client upload → update booking + notif ke mitra', function () {
        $client = makeUser(['id' => 10, 'role' => 'client']);
        $mitra = makeUser(['id' => 99, 'role' => 'mitra']);

        $booking = makeBookingMock([
            'id' => 1,
            'kode' => 'BKG-2026-001',
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => null,
        ]);
        $booking->setRelation('mitra', $mitra);
        $booking->setRelation('client', $client);

        Auth::shouldReceive('user')->andReturn($client);

        // `$updated` juga harus punya relasi (dipakai di `notifyProofUploaded`)
        $updated = makeBookingMock(['id' => 1, 'kode' => 'BKG-2026-001']);
        $updated->setRelation('mitra', $mitra);
        $updated->setRelation('client', $client);

        $this->bookingRepo->shouldReceive('update')
            ->once()
            ->with($booking, Mockery::on(fn ($p) => $p['payment_proof_path'] !== null
                && $p['payment_reject_reason'] === null))
            ->andReturn($updated);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: $mitra,
                tipe: 'payment_proof_uploaded',
                judul: Mockery::any(),
                pesan: Mockery::any(),
                data: Mockery::any(),
            );

        $result = $this->service->uploadProof($booking, UploadedFile::fake()->image('proof.jpg'));

        expect($result)->toBe($updated);
    });

    it('mitra upload (manual) → notif ke client', function () {
        $client = makeUser(['id' => 10, 'role' => 'client']);
        $mitra = makeUser(['id' => 99, 'role' => 'mitra']);

        $booking = makeBookingMock([
            'id' => 1,
            'kode' => 'BKG-2026-001',
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => null,
        ]);
        $booking->setRelation('mitra', $mitra);
        $booking->setRelation('client', $client);

        Auth::shouldReceive('user')->andReturn($mitra);

        $updated = makeBookingMock(['id' => 1]);
        $updated->setRelation('mitra', $mitra);
        $updated->setRelation('client', $client);

        $this->bookingRepo->shouldReceive('update')->andReturn($updated);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: $client,
                tipe: 'payment_proof_uploaded',
                judul: 'Bukti Transfer Diunggah',
                pesan: Mockery::any(),
                data: Mockery::any(),
            );

        $this->service->uploadProof($booking, UploadedFile::fake()->image('proof.jpg'));
    });
});

describe('Happy Path — verifyPayment', function () {
    it('mitra owner → update CONFIRMED + notif ke client', function () {
        $this->markTestSkipped(
            'Payment::create() static membutuhkan DB. '.
            'Test full verifyPayment di Feature test (RefreshDatabase).'
        );
    });
});

describe('Happy Path — rejectPayment', function () {
    it('mitra owner → update reject_reason + notif ke client', function () {
        $mitra = makeUser(['id' => 99]);
        $client = makeUser(['id' => 10]);

        $booking = makeBookingMock([
            'id' => 1,
            'kode' => 'BKG-2026-001',
            'mitra_id' => 99,
            'status' => BookingStatus::PENDING_PAYMENT,
        ]);
        $booking->setRelation('client', $client);

        $updated = makeBookingMock(['id' => 1, 'kode' => 'BKG-2026-001']);
        $updated->setRelation('client', $client);

        $this->bookingRepo->shouldReceive('update')
            ->once()
            ->with($booking, ['payment_reject_reason' => 'Foto buram'])
            ->andReturn($updated);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: $client,
                tipe: 'payment_rejected',
                judul: 'Bukti Transfer Ditolak',
                pesan: Mockery::on(fn ($p) => str_contains($p, 'Foto buram')),  // ← FIX: bukan contains
                data: Mockery::any(),
            );

        $result = $this->service->rejectPayment($booking, $mitra, 'Foto buram');

        expect($result)->toBe($updated);
    });
});

/*
|==========================================================================
| KATEGORI 4 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — uploadProof storeProofFile fail', function () {
    it('storeProofFile return false → ValidationException', function () {
        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn(Mockery::mock(Filesystem::class, function ($m) {
                $m->shouldReceive('exists')->andReturn(false);
                $m->shouldReceive('putFileAs')->andReturn(false);
            }));

        $client = makeUser(['id' => 10]);
        $booking = makeBookingMock([
            'id' => 1,
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => null,
        ]);

        Auth::shouldReceive('user')->andReturn($client);

        expect(fn () => $this->service->uploadProof($booking, UploadedFile::fake()->image('proof.jpg')))
            ->toThrow(ValidationException::class);
    });
});

describe('Edge Cases — uploadProof old proof deletion', function () {
    it('old proof ada di storage → dihapus', function () {
        Storage::disk('local')->put('payment_proofs/old.jpg', 'content');

        $client = makeUser(['id' => 10]);
        $mitra = makeUser(['id' => 99]);

        $booking = makeBookingMock([
            'id' => 1,
            'kode' => 'BKG-2026-001',
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => 'payment_proofs/old.jpg',
        ]);
        $booking->setRelation('mitra', $mitra);
        $booking->setRelation('client', $client);

        Auth::shouldReceive('user')->andReturn($client);

        $updated = makeBookingMock(['id' => 1]);
        $updated->setRelation('mitra', $mitra);
        $updated->setRelation('client', $client);

        $this->bookingRepo->shouldReceive('update')->andReturn($updated);
        $this->notifier->shouldReceive('send');

        $this->service->uploadProof($booking, UploadedFile::fake()->image('new.jpg'));

        Storage::disk('local')->assertMissing('payment_proofs/old.jpg');
    });
});

/*
|==========================================================================
| CATATAN: Test yang butuh Feature (dengan DB)
|==========================================================================
|
| - `verifyPayment` dispatch event `BookingConfirmed` — karena
|   `Payment::create` static butuh DB, event tidak tercapai di unit.
|   Test di Feature:
|
|       Event::fake([BookingConfirmed::class]);
|       $service->verifyPayment($booking, $mitra);
|       Event::assertDispatched(BookingConfirmed::class);
|
| - `dispatch event gagal → log error` — butuh DB untuk mencapai dispatch.
|
*/
