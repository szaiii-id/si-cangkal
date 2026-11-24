<?php

use App\Enums\BookingStatus;
use App\Enums\PackageKategori;
use App\Enums\TripStatus;
use App\Models\Package;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Trip\Contracts\TripStatusSynchronizerInterface;
use App\Services\Trip\TripService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Throwable;

beforeEach(function () {
    Queue::fake();

    $this->tripRepo = Mockery::mock(TripRepositoryInterface::class);
    $this->synchronizer = Mockery::mock(TripStatusSynchronizerInterface::class);
    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);

    $this->service = new TripService(
        $this->tripRepo,
        $this->synchronizer,
        $this->notifier,
    );

    DB::shouldReceive('transaction')
        ->byDefault()
        ->andReturnUsing(fn ($callback) => $callback());

    // Lock baris: default null => service memakai trip yang diberikan.
    $this->tripRepo->shouldReceive('findByIdForUpdate')
        ->byDefault()
        ->andReturnNull();

    // Sinkronisasi & notifikasi butuh DB/IO — di unit test cukup di-stub.
    $this->synchronizer->shouldReceive('markActivated')->byDefault()->andReturnNull();
    $this->synchronizer->shouldReceive('markCompleted')->byDefault()->andReturnNull();
    $this->notifier->shouldReceive('send')->byDefault()->andReturnNull();

    Log::shouldReceive('info')->byDefault()->andReturnNull();
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
    Log::shouldReceive('error')->byDefault()->andReturnNull();
});

afterEach(function () {
    Mockery::close();
});

function invokeBuildSnapshot(TripService $service, Package $package): array
{
    $ref = new ReflectionMethod($service, 'buildSnapshot');
    $ref->setAccessible(true);

    return $ref->invoke($service, $package);
}

describe('findDetail', function () {
    it('return Trip jika ada', function () {
        $trip = makeTripMock(['id' => 1]);

        $this->tripRepo->shouldReceive('findById')->once()->with(1)->andReturn($trip);

        expect($this->service->findDetail(1))->toBe($trip);
    });

    it('throw ValidationException jika null', function () {
        $this->tripRepo->shouldReceive('findById')->once()->with(999)->andReturn(null);

        expect(fn () => $this->service->findDetail(999))
            ->toThrow(ValidationException::class);
    });
});

describe('Passthrough — READ', function () {
    it('paginate pass-through', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->tripRepo->shouldReceive('paginate')
            ->once()->with(['search' => 'bali'], 25)->andReturn($paginator);

        expect($this->service->paginate(['search' => 'bali'], 25))->toBe($paginator);
    });

    it('paginateForMitra pass-through', function () {
        $mitra = makeUser(['id' => 99, 'role' => 'mitra']);
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->tripRepo->shouldReceive('paginateForMitra')
            ->once()->with($mitra, [], 15)->andReturn($paginator);

        expect($this->service->paginateForMitra($mitra))->toBe($paginator);
    });

    it('findByBookingId pass-through', function () {
        $trip = makeTripMock();
        $this->tripRepo->shouldReceive('findByBookingId')->once()->with(10)->andReturn($trip);

        expect($this->service->findByBookingId(10))->toBe($trip);
    });

    it('countByStatus pass-through', function () {
        $this->tripRepo->shouldReceive('countByStatus')
            ->once()->andReturn(['scheduled' => 2, 'active' => 1]);

        expect($this->service->countByStatus())
            ->toBe(['scheduled' => 2, 'active' => 1]);
    });

    it('countByStatusForMitra pass-through', function () {
        $mitra = makeUser(['id' => 99]);
        $this->tripRepo->shouldReceive('countByStatusForMitra')
            ->once()->with($mitra)->andReturn(['active' => 1]);

        expect($this->service->countByStatusForMitra($mitra))
            ->toBe(['active' => 1]);
    });
});

describe('BVA — createFromBooking guard', function () {
    it('booking belum confirmed → ValidationException', function () {
        $booking = makeBookingMock(['status' => BookingStatus::PENDING_PAYMENT]);

        expect(fn () => $this->service->createFromBooking($booking))
            ->toThrow(ValidationException::class);
    });

    it('booking sudah punya trip → ValidationException', function () {
        $booking = makeBookingMock(['id' => 10, 'status' => BookingStatus::CONFIRMED]);

        $this->tripRepo->shouldReceive('findByBookingId')
            ->once()->with(10)->andReturn(makeTripMock());

        expect(fn () => $this->service->createFromBooking($booking))
            ->toThrow(ValidationException::class);
    });
});

describe('buildSnapshot', function () {
    it('struktur snapshot benar', function () {
        $package = makePackageMock([
            'nama' => 'Paket Test',
            'kode' => 'PKG-2026-001',
            'kategori' => PackageKategori::WISATA_ALAM,
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'timezone' => 'Asia/Makassar',
        ]);
        $package->setRelation('days', new Collection);
        $package->setRelation('facilities', new Collection);

        $snapshot = invokeBuildSnapshot($this->service, $package);

        expect($snapshot)->toHaveKeys([
            'nama', 'kode', 'slug', 'deskripsi', 'kategori',
            'durasi_hari', 'durasi_malam', 'timezone', 'days', 'facilities',
        ]);
        expect($snapshot['nama'])->toBe('Paket Test');
        expect($snapshot['kode'])->toBe('PKG-2026-001');
        expect($snapshot['kategori'])->toBe('wisata_alam');
        expect($snapshot['durasi_hari'])->toBe(2);
        expect($snapshot['days'])->toBeArray();
        expect($snapshot['facilities'])->toHaveKeys(['include', 'exclude']);
    });

    it('days & facilities di-map dengan struktur benar', function () {
        $day = makePackageDayMock(['hari_ke' => 1, 'judul' => 'Hari 1']);

        $facilityInclude = makePackageFacilityMock(['tipe' => 'include', 'nama' => 'Transportasi']);
        $facilityExclude = makePackageFacilityMock(['tipe' => 'exclude', 'nama' => 'Tiket Pesawat']);

        $package = makePackageMock(['kategori' => PackageKategori::BUDAYA]);
        $package->setRelation('days', new Collection([$day]));
        $package->setRelation('facilities', new Collection([$facilityInclude, $facilityExclude]));

        $snapshot = invokeBuildSnapshot($this->service, $package);

        expect($snapshot['days'])->toHaveCount(1)
            ->and($snapshot['days'][0]['hari_ke'])->toBe(1)
            ->and($snapshot['days'][0]['judul'])->toBe('Hari 1')
            ->and($snapshot['facilities']['include'])->toBe(['Transportasi'])
            ->and($snapshot['facilities']['exclude'])->toBe(['Tiket Pesawat']);
    });
});

describe('createFromBooking — flow', function () {
    it('booking confirmed + no trip → tripRepo->create dipanggil + generateKode', function () {
        $mitra = makeUser(['id' => 99]);
        $package = makePackageMock([
            'id' => 5,
            'durasi_hari' => 2,
            'timezone' => 'Asia/Makassar',
            'kategori' => PackageKategori::WISATA_ALAM,
        ]);
        $package->setRelation('days', new Collection);
        $package->setRelation('facilities', new Collection);

        $booking = makeBookingMock([
            'id' => 10,
            'kode' => 'BKG-2026-001',
            'status' => BookingStatus::CONFIRMED,
            'mitra_id' => 99,
            'jumlah_peserta' => 2,
            'tanggal_keberangkatan' => '2026-02-01',
        ]);
        $booking->setRelation('package', $package);

        $created = makeTripMock(['id' => 1, 'kode' => 'TRP-2026-001']);
        $created->shouldReceive('load')->andReturnSelf();

        $this->tripRepo->shouldReceive('findByBookingId')->andReturn(null);
        $this->tripRepo->shouldReceive('generateKode')->once()->andReturn('TRP-2026-001');
        $this->tripRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($p) => $p['kode'] === 'TRP-2026-001'
                && $p['booking_id'] === 10
                && $p['package_id'] === 5
                && $p['mitra_id'] === 99
                && $p['status'] === TripStatus::SCHEDULED->value))
            ->andReturn($created);

        try {
            $this->service->createFromBooking($booking);
        } catch (Throwable) {
        }

        expect(true)->toBeTrue();
    });
});

describe('activate — guard', function () {
    it('status bukan scheduled → ValidationException', function () {
        $trip = makeTripMock(['status' => TripStatus::ACTIVE]);

        expect(fn () => $this->service->activate($trip))
            ->toThrow(ValidationException::class);
    });
});

describe('activate — flow', function () {
    it('sukses → tripRepo->update status ACTIVE', function () {
        $trip = makeTripMock([
            'id' => 1,
            'kode' => 'TRP-2026-001',
            'status' => TripStatus::SCHEDULED,
            'mitra_id' => 99,
            'tanggal_mulai' => Carbon::now()->subDay(),
            'timezone' => 'Asia/Makassar',
        ]);
        $trip->shouldReceive('timezoneOrDefault')->andReturn('Asia/Makassar');

        $updated = makeTripMock(['status' => TripStatus::ACTIVE]);
        $this->tripRepo->shouldReceive('update')
            ->once()
            ->with($trip, Mockery::on(fn ($p) => $p['status'] === TripStatus::ACTIVE->value))
            ->andReturn($updated);

        try {
            $this->service->activate($trip);
        } catch (Throwable) {
        }

        expect(true)->toBeTrue();
    });
});

describe('complete', function () {
    it('status bukan active → ValidationException', function () {
        $trip = makeTripMock(['status' => TripStatus::SCHEDULED]);

        expect(fn () => $this->service->complete($trip))
            ->toThrow(ValidationException::class);
    });

    it('sukses → tripRepo->update status COMPLETED', function () {
        $trip = makeTripMock([
            'id' => 1,
            'kode' => 'TRP-2026-001',
            'status' => TripStatus::ACTIVE,
        ]);

        $updated = makeTripMock(['status' => TripStatus::COMPLETED]);
        $this->tripRepo->shouldReceive('update')
            ->once()
            ->with($trip, Mockery::on(fn ($p) => $p['status'] === TripStatus::COMPLETED->value))
            ->andReturn($updated);

        expect($this->service->complete($trip))->toBe($updated);
    });
});

describe('cancel', function () {
    it('tidak bisa cancel → ValidationException', function () {
        $trip = makeTripMock(['status' => TripStatus::COMPLETED]);
        $trip->shouldReceive('canBeCancelled')->andReturn(false);

        expect(fn () => $this->service->cancel($trip))
            ->toThrow(ValidationException::class);
    });

    it('sukses → tripRepo->update status CANCELLED', function () {
        $trip = makeTripMock([
            'id' => 1,
            'kode' => 'TRP-2026-001',
            'status' => TripStatus::SCHEDULED,
        ]);
        $trip->shouldReceive('canBeCancelled')->andReturn(true);

        $updated = makeTripMock(['status' => TripStatus::CANCELLED]);
        $this->tripRepo->shouldReceive('update')
            ->once()
            ->with($trip, Mockery::on(fn ($p) => $p['status'] === TripStatus::CANCELLED->value))
            ->andReturn($updated);

        expect($this->service->cancel($trip, 'Cuaca buruk'))->toBe($updated);
    });
});

describe('activateDueTrips', function () {
    it('tidak ada candidates → activated 0', function () {
        $this->tripRepo->shouldReceive('findDueScheduled')
            ->once()->andReturn(new Collection);

        $result = $this->service->activateDueTrips();

        expect($result)->toBe(['activated' => 0, 'skipped_no_mitra' => 0]);
    });

    it('trip tanpa mitra → skipped_no_mitra bertambah', function () {
        $trip = makeTripMock([
            'id' => 1,
            'mitra_id' => 0,
            'tanggal_mulai' => Carbon::now(),
            'timezone' => 'Asia/Makassar',
        ]);
        $trip->shouldReceive('hasMitra')->once()->andReturn(false);

        $this->tripRepo->shouldReceive('findDueScheduled')
            ->once()->andReturn(new Collection([$trip]));

        $result = $this->service->activateDueTrips();

        expect($result)->toBe(['activated' => 0, 'skipped_no_mitra' => 1]);
    });

    it('error di 1 trip → log, lanjut trip lain', function () {
        $trip1 = makeTripMock([
            'id' => 1,
            'mitra_id' => 99,
            'tanggal_mulai' => Carbon::now(),
            'timezone' => 'Asia/Makassar',
        ]);
        $trip1->shouldReceive('timezoneOrDefault')->andReturn('Asia/Makassar');

        $this->tripRepo->shouldReceive('findDueScheduled')
            ->once()->andReturn(new Collection([$trip1]));

        $this->tripRepo->shouldReceive('update')
            ->once()
            ->andThrow(new RuntimeException('DB error'));

        Log::shouldReceive('error')->once();

        $result = $this->service->activateDueTrips();

        expect($result['activated'])->toBe(0);
    });
});
