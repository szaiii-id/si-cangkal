<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingParticipant;
use App\Models\Package;
use App\Models\Profile;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Booking\BookingCreationService;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| BookingCreationService — persistence `Booking::create` +
| `BookingParticipant::create` + notifikasi.
|
| Yang di-test di sini (yang di-skip di unit test):
| - `create` — persistence `Booking::create` + `BookingParticipant::create`
|   + notifikasi ke mitra.
| - `createManual` — persistence + `expired_at = null` + notifikasi.
| - Validasi bisnis (package tidak aktif, jumlah peserta, max_peserta).
|
|   Catatan:
| - `NotificationDispatcherInterface` di-mock — fokus persistence.
| - `AuditLogger` — pakai `Log::spy()` untuk verifikasi.
| - `Auth::login($client)` — untuk `auth()->id()` di AuditLogger.
|
*/

beforeEach(function () {
    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);
    $this->notifier->shouldReceive('send')->byDefault()->andReturnNull();

    $this->service = new BookingCreationService(
        app(BookingRepositoryInterface::class),
        $this->notifier,
    );

    $this->client = User::factory()->client()->create([
        'name' => 'Budi Client',
        'must_change_password' => false,
    ]);

    $this->mitra = User::factory()->mitra()->create([
        'name' => 'Mitra Travel',
        'must_change_password' => false,
    ]);

    Profile::factory()->create([
        'user_id' => $this->mitra->id,
        'tipe' => 'mitra',
        'nama_penyedia_usaha' => 'PT Travel Jaya',
        'telepon' => '08123456789',
    ]);
});

/*
|==========================================================================
| HELPER — payload valid
|==========================================================================
*/
function validCreationPayload(array $overrides = []): array
{
    return array_merge([
        'tanggal_keberangkatan' => now()->addDays(5)->format('Y-m-d'),
        'jumlah_peserta' => 2,
        'catatan' => 'Catatan booking',
        'participants' => [
            ['nama' => 'Peserta 1', 'umur' => 25, 'no_hp' => '0811111111'],
            ['nama' => 'Peserta 2', 'umur' => 30, 'no_hp' => '0822222222'],
        ],
    ], $overrides);
}

/*
|==========================================================================
| KATEGORI 1 — CREATE (PERSISTENCE)
|==========================================================================
*/

describe('create — persistence', function () {
    it('create → Booking tersimpan di DB dengan status PENDING_PAYMENT', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create([
            'harga_per_orang' => 500_000,
            'harga_per_rombongan' => null,
            'max_peserta' => null,
        ]);

        $booking = $this->service->create($package, $this->client, validCreationPayload());

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'package_id' => $package->id,
            'user_id' => $this->client->id,
            'mitra_id' => $this->mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT->value,
            'jumlah_peserta' => 2,
        ]);

        expect($booking->kode)->toStartWith('BKG-');
        expect($booking->expired_at)->not->toBeNull();
    });

    it('create → total_harga = calculateTotalHarga × jumlah_peserta', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create([
            'harga_per_orang' => 500_000,
            'harga_per_rombongan' => null,
            'max_peserta' => null,
        ]);

        $booking = $this->service->create($package, $this->client, validCreationPayload());

        // 2 peserta × 500.000 = 1.000.000
        expect((float) $booking->total_harga)->toBe(1_000_000.0);
    });

    it('create → BookingParticipant tersimpan sesuai jumlah peserta', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

        $booking = $this->service->create($package, $this->client, validCreationPayload());

        $participants = BookingParticipant::where('booking_id', $booking->id)->get();

        expect($participants)->toHaveCount(2)
            ->and($participants[0]->nama)->toBe('Peserta 1')
            ->and($participants[0]->umur)->toBe(25)
            ->and($participants[1]->nama)->toBe('Peserta 2')
            ->and($participants[1]->umur)->toBe(30);
    });

    it('create → notifikasi ke mitra dikirim', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create([
            'nama' => 'Paket Test',
        ]);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->mitra->id),
                tipe: 'booking_created',
                judul: 'Pesanan Baru Masuk',
                pesan: Mockery::type('string'),
                data: Mockery::on(fn ($d) => isset($d['booking_id']) && isset($d['url'])),
            );

        $this->service->create($package, $this->client, validCreationPayload());
    });

    it('create → notifikasi gagal TIDAK mengganggu booking', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

        $this->notifier->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('FCM down'));

        $booking = $this->service->create($package, $this->client, validCreationPayload());

        // Booking TETAP tersimpan
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    });

    it('create → package mitra tanpa relasi → tidak crash', function () {
        // Package tanpa mitra (mitra_id null) — edge case
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

        $this->notifier->shouldReceive('send')->never();

        // Set relasi mitra ke null
        $package->setRelation('mitra', null);

        $booking = $this->service->create($package, $this->client, validCreationPayload());

        expect($booking)->toBeInstanceOf(Booking::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — CREATE MANUAL (PERSISTENCE)
|==========================================================================
*/

describe('createManual — persistence', function () {
    it('createManual → Booking tersimpan dengan expired_at = null', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

        $booking = $this->service->createManual(
            $package,
            $this->client,
            $this->mitra,
            validCreationPayload(),
        );

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'package_id' => $package->id,
            'user_id' => $this->client->id,
            'mitra_id' => $this->mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT->value,
            'expired_at' => null,
            'sumber' => 'manual',
        ]);
    });

    it('createManual → BookingParticipant tersimpan', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

        $booking = $this->service->createManual(
            $package,
            $this->client,
            $this->mitra,
            validCreationPayload(['jumlah_peserta' => 3, 'participants' => [
                ['nama' => 'P1'],
                ['nama' => 'P2'],
                ['nama' => 'P3'],
            ]]),
        );

        $participants = BookingParticipant::where('booking_id', $booking->id)->get();

        expect($participants)->toHaveCount(3);
    });

    it('createManual → mitra_id dari parameter (bukan dari package)', function () {
        $mitraLain = User::factory()->mitra()->create(['must_change_password' => false]);
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

        $booking = $this->service->createManual(
            $package,
            $this->client,
            $mitraLain,
            validCreationPayload(),
        );

        expect($booking->mitra_id)->toBe($mitraLain->id);
    });
});

/*
|==========================================================================
| KATEGORI 3 — VALIDASI BISNIS
|==========================================================================
*/

describe('create — validasi bisnis', function () {
    it('package tidak aktif → ValidationException', function () {
        $package = Package::factory()->forMitra($this->mitra)->draft()->create();

        expect(fn () => $this->service->create($package, $this->client, validCreationPayload()))
            ->toThrow(ValidationException::class);
    });

    it('jumlah_peserta 0 → ValidationException', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

        expect(fn () => $this->service->create($package, $this->client, validCreationPayload([
            'jumlah_peserta' => 0,
            'participants' => [],
        ])))->toThrow(ValidationException::class);
    });

    it('rombongan jumlah > max_peserta → ValidationException', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create([
            'harga_per_rombongan' => 5_000_000,
            'max_peserta' => 5,
        ]);

        expect(fn () => $this->service->create($package, $this->client, validCreationPayload([
            'jumlah_peserta' => 6,
            'participants' => array_fill(0, 6, ['nama' => 'X']),
        ])))->toThrow(ValidationException::class);
    });

    it('participants kurang dari jumlah_peserta → ValidationException', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

        expect(fn () => $this->service->create($package, $this->client, validCreationPayload([
            'jumlah_peserta' => 3,
            'participants' => [['nama' => 'A'], ['nama' => 'B']],
        ])))->toThrow(ValidationException::class);
    });
});
