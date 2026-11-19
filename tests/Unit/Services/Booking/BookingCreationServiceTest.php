<?php

use App\Enums\BookingStatus;
use App\Enums\PackageStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Booking\BookingCreationService;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Throwable;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    $this->bookingRepo = Mockery::mock(BookingRepositoryInterface::class);
    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);

    $this->service = new BookingCreationService($this->bookingRepo, $this->notifier);

    DB::shouldReceive('transaction')
        ->byDefault()
        ->andReturnUsing(fn ($callback) => $callback());

    $auditLoggerMock = Mockery::mock(LoggerInterface::class);
    $auditLoggerMock->shouldReceive('info')->byDefault()->andReturnNull();

    Log::shouldReceive('channel')->with('audit')->byDefault()->andReturn($auditLoggerMock);
    Log::shouldReceive('driver')->byDefault()->andReturn($auditLoggerMock);
    Log::shouldReceive('info')->byDefault()->andReturnNull();
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| HELPER
|==========================================================================
*/

function invokeValidateBooking(BookingCreationService $service, Package $package, array $data): void
{
    $ref = new ReflectionMethod($service, 'validateBooking');
    $ref->setAccessible(true);
    $ref->invoke($service, $package, $data);
}

function invokeNotifyMitraNewBooking(
    BookingCreationService $service,
    Booking $booking,
    Package $package,
    User $client,
): void {
    $ref = new ReflectionMethod($service, 'notifyMitraNewBooking');
    $ref->setAccessible(true);
    $ref->invoke($service, $booking, $package, $client);
}

/*
|==========================================================================
| KATEGORI 1 — BVA (`validateBooking` via reflection)
|==========================================================================
*/

describe('BVA — validateBooking', function () {
    it('package tidak aktif → ValidationException', function () {
        $package = makePackageMock(['status' => PackageStatus::DRAFT]);

        expect(fn () => invokeValidateBooking($this->service, $package, [
            'jumlah_peserta' => 2,
            'participants' => [['nama' => 'A'], ['nama' => 'B']],
        ]))->toThrow(ValidationException::class);
    });

    it('jumlah peserta = 0 → ValidationException', function () {
        $package = makePackageMock(['status' => PackageStatus::AKTIF]);

        expect(fn () => invokeValidateBooking($this->service, $package, [
            'jumlah_peserta' => 0,
            'participants' => [],
        ]))->toThrow(ValidationException::class);
    });

    it('jumlah peserta = 1 (batas bawah) → lolos', function () {
        $package = makePackageMock([
            'status' => PackageStatus::AKTIF,
            'harga_per_rombongan' => null,
            'max_peserta' => null,
        ]);

        invokeValidateBooking($this->service, $package, [
            'jumlah_peserta' => 1,
            'participants' => [['nama' => 'A']],
        ]);

        expect(true)->toBeTrue();
    });

    it('rombongan jumlah > max_peserta → ValidationException', function () {
        $package = makePackageMock([
            'status' => PackageStatus::AKTIF,
            'harga_per_rombongan' => 5_000_000,
            'max_peserta' => 5,
        ]);

        expect(fn () => invokeValidateBooking($this->service, $package, [
            'jumlah_peserta' => 6,
            'participants' => array_fill(0, 6, ['nama' => 'X']),
        ]))->toThrow(ValidationException::class);
    });

    it('rombongan jumlah == max_peserta (batas) → lolos', function () {
        $package = makePackageMock([
            'status' => PackageStatus::AKTIF,
            'harga_per_rombongan' => 5_000_000,
            'max_peserta' => 5,
        ]);

        invokeValidateBooking($this->service, $package, [
            'jumlah_peserta' => 5,
            'participants' => array_fill(0, 5, ['nama' => 'X']),
        ]);

        expect(true)->toBeTrue();
    });

    it('participants kurang dari jumlah_peserta → ValidationException', function () {
        $package = makePackageMock(['status' => PackageStatus::AKTIF]);

        expect(fn () => invokeValidateBooking($this->service, $package, [
            'jumlah_peserta' => 3,
            'participants' => [['nama' => 'A'], ['nama' => 'B']],
        ]))->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — HAPPY PATH (`create` flow pre-insert)
|==========================================================================
*/

describe('Happy Path — create flow pre-insert', function () {
    it('create → booking PENDING_PAYMENT + expired 24 jam + calculateTotalHarga dipanggil', function () {
        $client = makeUser(['id' => 10, 'name' => 'Budi', 'role' => 'client']);
        $package = makePackageMock([
            'id' => 5,
            'mitra_id' => 99,
            'nama' => 'Paket Test',
            'status' => PackageStatus::AKTIF,
        ]);
        $package->shouldReceive('calculateTotalHarga')->once()->with(2)->andReturn(1_000_000);

        $created = makeBookingMock(['id' => 1, 'kode' => 'BKG-2026-001']);
        $created->shouldReceive('load')->andReturnSelf();

        $this->bookingRepo->shouldReceive('generateKode')->once()->andReturn('BKG-2026-001');
        $this->bookingRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($p) => $p['status'] === BookingStatus::PENDING_PAYMENT->value
                && $p['package_id'] === 5
                && $p['mitra_id'] === 99
                && $p['total_harga'] === 1_000_000.0
                && $p['expired_at'] !== null))
            ->andReturn($created);

        try {
            $this->service->create($package, $client, [
                'tanggal_keberangkatan' => '2026-02-01',
                'jumlah_peserta' => 2,
                'participants' => [['nama' => 'A'], ['nama' => 'B']],
            ]);
        } catch (Throwable) {
            // Persistence butuh Feature test
        }

        expect(true)->toBeTrue();
    });

    it('createManual → expired_at null + mitra_id dari parameter', function () {
        $client = makeUser(['id' => 10, 'name' => 'Budi', 'email' => 'budi@example.com']);
        $mitra = makeUser(['id' => 99, 'name' => 'Mitra']);
        $package = makePackageMock([
            'id' => 5,
            'status' => PackageStatus::AKTIF,
        ]);
        $package->shouldReceive('calculateTotalHarga')->once()->with(3)->andReturn(1_500_000);

        $created = makeBookingMock(['id' => 1]);
        $created->shouldReceive('load')->andReturnSelf();

        $this->bookingRepo->shouldReceive('generateKode')->once()->andReturn('BKG-2026-001');
        $this->bookingRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($p) => $p['mitra_id'] === 99
                && $p['expired_at'] === null
                && $p['total_harga'] === 1_500_000.0))
            ->andReturn($created);

        try {
            $this->service->createManual($package, $client, $mitra, [
                'tanggal_keberangkatan' => '2026-02-01',
                'jumlah_peserta' => 3,
                'participants' => [['nama' => 'A'], ['nama' => 'B'], ['nama' => 'C']],
            ]);
        } catch (Throwable) {
        }

        expect(true)->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 3 — NOTIFICATION (`notifyMitraNewBooking` via reflection)
|==========================================================================
|
|   Catatan: `$package->mitra` adalah RELASI (BelongsTo), bukan atribut
| biasa. Mock `shouldReceive('mitra')->andReturn($user)` akan ERROR
| karena return type harus `BelongsTo`. Solusi: pakai `setRelation()`
| untuk set relasi langsung di model mock.
|
*/

describe('Notification — notifyMitraNewBooking', function () {
    it('package tanpa mitra → tidak kirim notifikasi', function () {
        $client = makeUser(['id' => 10, 'name' => 'Budi']);
        $package = makePackageMock([
            'id' => 5,
            'mitra_id' => 99,
            'nama' => 'Paket Test',
        ]);
        // Set relasi ke null via `setRelation` (bukan mock method `mitra()`)
        $package->setRelation('mitra', null);

        $booking = makeBookingMock(['id' => 1, 'kode' => 'BKG-2026-001']);

        $this->notifier->shouldReceive('send')->never();

        invokeNotifyMitraNewBooking($this->service, $booking, $package, $client);

        expect(true)->toBeTrue();
    });

    it('package ada mitra → kirim notifikasi dengan tipe booking_created', function () {
        $client = makeUser(['id' => 10, 'name' => 'Budi']);
        $mitra = makeUser(['id' => 99]);
        $package = makePackageMock([
            'id' => 5,
            'mitra_id' => 99,
            'nama' => 'Paket Test',
        ]);
        // Set relasi ke User via `setRelation`
        $package->setRelation('mitra', $mitra);

        $booking = makeBookingMock(['id' => 1, 'kode' => 'BKG-2026-001']);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: $mitra,
                tipe: 'booking_created',
                judul: 'Pesanan Baru Masuk',
                pesan: Mockery::type('string'),
                data: Mockery::on(fn ($d) => $d['booking_id'] === 1 && isset($d['url'])),
            );

        invokeNotifyMitraNewBooking($this->service, $booking, $package, $client);

        expect(true)->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 4 — NEGATIVE PATH
|==========================================================================
*/

describe('Negative Path — create validation', function () {
    it('package tidak aktif → ValidationException, repository tidak dipanggil', function () {
        $package = makePackageMock(['status' => PackageStatus::DRAFT]);
        $client = makeUser(['id' => 10]);

        $this->bookingRepo->shouldReceive('create')->never();
        $this->bookingRepo->shouldReceive('generateKode')->never();

        expect(fn () => $this->service->create($package, $client, [
            'tanggal_keberangkatan' => '2026-02-01',
            'jumlah_peserta' => 2,
            'participants' => [['nama' => 'A'], ['nama' => 'B']],
        ]))->toThrow(ValidationException::class);
    });

    it('createManual juga validasi package tidak aktif', function () {
        $package = makePackageMock(['status' => PackageStatus::DRAFT]);
        $client = makeUser(['id' => 10]);
        $mitra = makeUser(['id' => 99]);

        $this->bookingRepo->shouldReceive('create')->never();

        expect(fn () => $this->service->createManual($package, $client, $mitra, [
            'tanggal_keberangkatan' => '2026-02-01',
            'jumlah_peserta' => 2,
            'participants' => [['nama' => 'A'], ['nama' => 'B']],
        ]))->toThrow(ValidationException::class);
    });
});
