<?php

use App\Enums\BookingStatus;
use App\Enums\PackageFacilityTipe;
use App\Enums\PackageKategori;
use App\Enums\PackageStatus;
use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\PackageFacility;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\User;
use App\Services\Trip\TripService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| TripService — persistence `Trip::create` + `TripDay::create` +
| `TripDay::query()->update` + status transition.
|
| Yang di-test di sini (yang di-skip di unit test):
| - `createFromBooking` — persistence `Trip::create` + `TripDay::create`.
| - `activate` — persistence `Trip::update` + `TripDay::query()->update`.
| - `activateDueTrips` — sukses → `TripDay::query()->update`.
| - `complete` / `cancel` — persistence status.
|
|   Catatan:
| - `Log::channel('audit')` — tidak perlu (TripService pakai Log::info
|   biasa, bukan AuditLogger).
| - `Trip::factory()`, `Package::factory()`, `Booking::factory()` —
|   real factory + real DB.
|
*/

beforeEach(function () {
    Log::spy();

    $this->mitra = User::factory()->mitra()->create([
        'name' => 'Mitra Travel',
        'must_change_password' => false,
    ]);

    $this->client = User::factory()->client()->create([
        'name' => 'Budi Client',
        'must_change_password' => false,
    ]);

    $this->service = app(TripService::class);
});

/*
|==========================================================================
| HELPER — buat paket + booking confirmed
|==========================================================================
*/
function makePackageWithDays(User $mitra, int $durasiHari = 2, array $overrides = []): Package
{
    $package = Package::factory()->forMitra($mitra)->create(array_merge([
        'durasi_hari' => $durasiHari,
        'durasi_malam' => max(0, $durasiHari - 1),
        'status' => PackageStatus::AKTIF->value,
        'timezone' => 'Asia/Makassar',
        'kategori' => PackageKategori::WISATA_ALAM->value,
    ], $overrides));

    for ($i = 1; $i <= $durasiHari; $i++) {
        PackageDay::factory()->create([
            'package_id' => $package->id,
            'hari_ke' => $i,
        ]);
    }

    PackageFacility::factory()->create([
        'package_id' => $package->id,
        'tipe' => PackageFacilityTipe::INCLUDE->value,
        'nama' => 'Transportasi',
    ]);
    PackageFacility::factory()->create([
        'package_id' => $package->id,
        'tipe' => PackageFacilityTipe::EXCLUDE->value,
        'nama' => 'Tiket Pesawat',
    ]);

    return $package;
}

function makeConfirmedBooking(User $client, User $mitra, Package $package, array $overrides = []): Booking
{
    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED,
            'tanggal_keberangkatan' => now()->addDays(7)->format('Y-m-d'),
            'jumlah_peserta' => 2,
        ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — CREATE FROM BOOKING (PERSISTENCE)
|==========================================================================
*/

describe('createFromBooking — persistence', function () {
    it('createFromBooking → Trip tersimpan + TripDay di-generate', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package);

        $trip = $this->service->createFromBooking($booking);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'booking_id' => $booking->id,
            'package_id' => $package->id,
            'mitra_id' => $this->mitra->id,
            'status' => TripStatus::SCHEDULED->value,
        ]);

        // TripDay di-generate sesuai durasi_hari
        $tripDays = TripDay::where('trip_id', $trip->id)->get();
        expect($tripDays)->toHaveCount(2)
            ->and($tripDays[0]->hari_ke)->toBe(1)
            ->and($tripDays[1]->hari_ke)->toBe(2);
    });

    it('createFromBooking → snapshot_package tersimpan', function () {
        $package = makePackageWithDays($this->mitra, 2, [
            'nama' => 'Paket Bali',
            'kode' => 'PKG-2026-001',
        ]);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package);

        $trip = $this->service->createFromBooking($booking);
        $trip->refresh();

        expect($trip->snapshot_package)->toBeArray()
            ->and($trip->snapshot_package['nama'])->toBe('Paket Bali')
            ->and($trip->snapshot_package['kode'])->toBe('PKG-2026-001')
            ->and($trip->snapshot_package['days'])->toHaveCount(2)
            ->and($trip->snapshot_package['facilities']['include'])->toBe(['Transportasi'])
            ->and($trip->snapshot_package['facilities']['exclude'])->toBe(['Tiket Pesawat']);
    });

    it('createFromBooking → tanggal_mulai & tanggal_selesai sesuai booking', function () {
        $package = makePackageWithDays($this->mitra, 3);
        $tanggal = now()->addDays(7)->format('Y-m-d');
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package, [
            'tanggal_keberangkatan' => $tanggal,
        ]);

        $trip = $this->service->createFromBooking($booking);

        expect($trip->tanggal_mulai->format('Y-m-d'))->toBe($tanggal);
        // durasi_hari 3 → tanggal_selesai = tanggal_mulai + 2
        $expectedSelesai = Carbon::parse($tanggal)->addDays(2)->format('Y-m-d');
        expect($trip->tanggal_selesai->format('Y-m-d'))->toBe($expectedSelesai);
    });

    it('createFromBooking → TripDay tanggal sesuai hari_ke', function () {
        $package = makePackageWithDays($this->mitra, 3);
        $tanggal = '2026-02-01';
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package, [
            'tanggal_keberangkatan' => $tanggal,
        ]);

        $trip = $this->service->createFromBooking($booking);

        $tripDays = TripDay::where('trip_id', $trip->id)->orderBy('hari_ke')->get();

        expect($tripDays[0]->tanggal->format('Y-m-d'))->toBe('2026-02-01')
            ->and($tripDays[1]->tanggal->format('Y-m-d'))->toBe('2026-02-02')
            ->and($tripDays[2]->tanggal->format('Y-m-d'))->toBe('2026-02-03');
    });

    it('createFromBooking → TripDay status PENDING', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package);

        $trip = $this->service->createFromBooking($booking);

        $tripDays = TripDay::where('trip_id', $trip->id)->get();
        foreach ($tripDays as $day) {
            expect($day->status)->toBe(TripDay::STATUS_PENDING);
        }
    });

    it('createFromBooking → booking belum confirmed → ValidationException', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package, [
            'status' => BookingStatus::PENDING_PAYMENT,
        ]);

        expect(fn () => $this->service->createFromBooking($booking))
            ->toThrow(ValidationException::class);
    });

    it('createFromBooking → booking sudah punya trip → ValidationException', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package);

        // Trip pertama
        $this->service->createFromBooking($booking);

        // Trip kedua — harus error
        expect(fn () => $this->service->createFromBooking($booking->fresh()))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — ACTIVATE (PERSISTENCE)
|==========================================================================
*/

describe('activate — persistence', function () {
    it('activate → Trip status ACTIVE + TripDay status IN_PROGRESS', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package, [
            'tanggal_keberangkatan' => now()->subDay()->format('Y-m-d'),
        ]);
        $trip = $this->service->createFromBooking($booking);

        $result = $this->service->activate($trip);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => TripStatus::ACTIVE->value,
        ]);

        // TripDay PENDING → IN_PROGRESS
        $tripDays = TripDay::where('trip_id', $trip->id)->get();
        foreach ($tripDays as $day) {
            expect($day->status)->toBe(TripDay::STATUS_IN_PROGRESS);
        }

        expect($result->status)->toBe(TripStatus::ACTIVE);
    });

    it('activate → status bukan SCHEDULED → ValidationException', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package);
        $trip = $this->service->createFromBooking($booking);

        // Ubah status jadi ACTIVE langsung
        $trip->update(['status' => TripStatus::ACTIVE->value]);

        expect(fn () => $this->service->activate($trip->fresh()))
            ->toThrow(ValidationException::class);
    });

});

/*
|==========================================================================
| KATEGORI 3 — COMPLETE (PERSISTENCE)
|==========================================================================
*/

describe('complete — persistence', function () {
    it('complete → Trip status COMPLETED', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package);
        $trip = $this->service->createFromBooking($booking);
        $trip->update(['status' => TripStatus::ACTIVE->value]);

        $result = $this->service->complete($trip->fresh());

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => TripStatus::COMPLETED->value,
        ]);

        expect($result->status)->toBe(TripStatus::COMPLETED);
    });

    it('complete → status bukan ACTIVE → ValidationException', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package);
        $trip = $this->service->createFromBooking($booking);

        expect(fn () => $this->service->complete($trip))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — CANCEL (PERSISTENCE)
|==========================================================================
*/

describe('cancel — persistence', function () {
    it('cancel → Trip status CANCELLED', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package);
        $trip = $this->service->createFromBooking($booking);

        $result = $this->service->cancel($trip, 'Cuaca buruk');

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => TripStatus::CANCELLED->value,
        ]);

        expect($result->status)->toBe(TripStatus::CANCELLED);
    });

    it('cancel → tidak bisa cancel (COMPLETED) → ValidationException', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package);
        $trip = $this->service->createFromBooking($booking);
        $trip->update(['status' => TripStatus::COMPLETED->value]);

        expect(fn () => $this->service->cancel($trip->fresh()))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 5 — ACTIVATE DUE TRIPS (PERSISTENCE)
|==========================================================================
*/

describe('activateDueTrips — persistence', function () {
    it('activateDueTrips → 1 trip due diaktifkan', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package, [
            'tanggal_keberangkatan' => now()->format('Y-m-d'),
        ]);
        $trip = $this->service->createFromBooking($booking);

        $result = $this->service->activateDueTrips();

        expect($result['activated'])->toBe(1)
            ->and($result['skipped_no_mitra'])->toBe(0);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => TripStatus::ACTIVE->value,
        ]);
    });

    it('activateDueTrips → mengaktifkan trip yang tanggalnya hari ini di timezone trip (UTC hari ini kemarin)', function () {
        // Simulasi kasus khusus: UTC 22:30 tanggal 22 → timezone trip (Makassar)
        // sudah tanggal 23. Trip dibuat dengan tanggal hari ini di timezone trip
        // — harus aktif (sebelumnya gagal karena cutoff UTC harian tertinggal).
        Carbon::setTestNow('2026-11-22 23:30 UTC');

        $package = makePackageWithDays($this->mitra, 1);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package, [
            'tanggal_keberangkatan' => '2026-11-23',
        ]);
        $trip = $this->service->createFromBooking($booking);

        $result = $this->service->activateDueTrips();

        expect($result['activated'])->toBe(1);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => TripStatus::ACTIVE->value,
        ]);

        Carbon::setTestNow();
    });

    it('activateDueTrips → TripDay status IN_PROGRESS', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package, [
            'tanggal_keberangkatan' => now()->format('Y-m-d'),
        ]);
        $trip = $this->service->createFromBooking($booking);

        $this->service->activateDueTrips();

        $tripDays = TripDay::where('trip_id', $trip->id)->get();
        foreach ($tripDays as $day) {
            expect($day->status)->toBe(TripDay::STATUS_IN_PROGRESS);
        }
    });

    it('activateDueTrips → tidak ada trip due → 0', function () {
        $package = makePackageWithDays($this->mitra, 2);
        $booking = makeConfirmedBooking($this->client, $this->mitra, $package, [
            'tanggal_keberangkatan' => now()->addDays(7)->format('Y-m-d'),
        ]);
        $this->service->createFromBooking($booking);

        $result = $this->service->activateDueTrips();

        expect($result['activated'])->toBe(0)
            ->and($result['skipped_no_mitra'])->toBe(0);
    });
});
