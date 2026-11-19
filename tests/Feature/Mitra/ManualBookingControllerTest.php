<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\User;
use App\Services\Booking\BookingCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| ManualBookingController (mitra) — buat booking manual + auto-create client.
|
| Route (prefix /mitra):
| - GET  /packages/{package}/manual-booking → create
| - POST /packages/{package}/manual-booking → store
|
| Middleware: auth, password.changed, role:mitra, mitra.complete
| Policy: PackagePolicy@createManualBooking (owner check)
| FormRequest: StoreManualBookingRequest
|
|   Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - `BookingCreationService` di-mock — fokus test CONTROLLER,
|   bukan service (sudah di-test unit di BookingCreationServiceTest).
| - Mitra WAJIB punya bank account (middleware `mitra.complete`).
| - Auto-create client: `User::firstOrCreate` — test verifikasi ini.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);

    MitraBankAccount::create([
        'user_id' => $this->mitra->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BCA',
        'nomor_rekening' => '1234567890',
        'nama_pemilik' => 'Budi',
        'is_primary' => true,
    ]);

    $this->mitra2 = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);

    MitraBankAccount::create([
        'user_id' => $this->mitra2->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BRI',
        'nomor_rekening' => '9999999999',
        'nama_pemilik' => 'Ani',
        'is_primary' => true,
    ]);

    $this->package = Package::factory()->forMitra($this->mitra)->aktif()->create([
        'durasi_hari' => 2,
        'max_peserta' => null,
        'harga_per_rombongan' => null,
    ]);

    $this->package2 = Package::factory()->forMitra($this->mitra2)->aktif()->create();
});

/*
|==========================================================================
| HELPER — payload valid
|==========================================================================
*/
function validManualBookingPayload(array $overrides = []): array
{
    $tanggal = now()->addDays(5)->format('Y-m-d');

    return array_merge([
        'nama_pelanggan' => 'Budi Pelanggan',
        'email_pelanggan' => 'budi.pelanggan@example.com',
        'no_hp_pelanggan' => '08123456789',
        'tanggal_keberangkatan' => $tanggal,
        'jumlah_peserta' => 2,
        'catatan' => 'Catatan manual',
        'participants' => [
            ['nama' => 'Peserta 1', 'umur' => 25, 'no_hp' => '0811111111'],
            ['nama' => 'Peserta 2', 'umur' => 30, 'no_hp' => '0822222222'],
        ],
    ], $overrides);
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — create', function () {
    it('GET /mitra/packages/{id}/manual-booking → render Mitra/Bookings/CreateManual', function () {
        $this->actingAs($this->mitra)
            ->get("/mitra/packages/{$this->package->id}/manual-booking")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Bookings/CreateManual')
                ->has('package')
                ->where('package.id', $this->package->id)
            );
    });
});

describe('Contract — store redirect', function () {
    it('POST /mitra/packages/{id}/manual-booking valid → redirect ke bookings.show + status', function () {
        $booking = Booking::factory()
            ->forPackage($this->package)
            ->forClient($this->mitra)
            ->create([
                'mitra_id' => $this->mitra->id,
                'status' => BookingStatus::PENDING_PAYMENT,
            ]);

        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('createManual')
                ->once()
                ->andReturn($booking);
        });

        $response = $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload());

        $response->assertRedirect(route('mitra.bookings.show', $booking->id))
            ->assertSessionHas('status');
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (create)', function () {
        $this->get("/mitra/packages/{$this->package->id}/manual-booking")
            ->assertRedirect(route('login'));
    });

    it('guest di-redirect ke login (store)', function () {
        $this->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload())
            ->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get("/mitra/packages/{$this->package->id}/manual-booking")
            ->assertRedirect(route('admin.dashboard'));
    });

    it('client di-redirect ke client.dashboard', function () {
        $client = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($client)
            ->get("/mitra/packages/{$this->package->id}/manual-booking")
            ->assertRedirect(route('client.dashboard'));
    });
});

describe('Security — ownership (Policy createManualBooking)', function () {
    it('mitra TIDAK bisa create manual untuk paket mitra lain → 403', function () {
        $this->actingAs($this->mitra)
            ->get("/mitra/packages/{$this->package2->id}/manual-booking")
            ->assertForbidden();
    });

    it('mitra TIDAK bisa store manual untuk paket mitra lain → 403', function () {
        $this->mock(BookingCreationService::class, function (MockInterface $m) {
            $m->shouldNotReceive('createManual');
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package2->id}/manual-booking", validManualBookingPayload())
            ->assertForbidden();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — auto-create client', function () {
    it('store 2x dengan email pelanggan sama → client TIDAK duplikat', function () {
        $booking1 = Booking::factory()
            ->forPackage($this->package)
            ->forClient($this->mitra)
            ->create(['mitra_id' => $this->mitra->id]);
        $booking2 = Booking::factory()
            ->forPackage($this->package)
            ->forClient($this->mitra)
            ->create(['mitra_id' => $this->mitra->id]);

        $callCount = 0;
        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking1, $booking2, &$callCount) {
            $m->shouldReceive('createManual')
                ->twice()
                ->andReturnUsing(function () use ($booking1, $booking2, &$callCount) {
                    $callCount++;

                    return $callCount === 1 ? $booking1 : $booking2;
                });
        });

        $payload = validManualBookingPayload();

        $this->actingAs($this->mitra)->post("/mitra/packages/{$this->package->id}/manual-booking", $payload);
        $this->actingAs($this->mitra)->post("/mitra/packages/{$this->package->id}/manual-booking", $payload);

        // Client hanya 1 (firstOrCreate)
        expect(User::where('email', 'budi.pelanggan@example.com')->count())->toBe(1);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('store 2x dengan data sama → auto-create client idempoten (firstOrCreate)', function () {
        $booking = Booking::factory()
            ->forPackage($this->package)
            ->forClient($this->mitra)
            ->create(['mitra_id' => $this->mitra->id]);

        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('createManual')->twice()->andReturn($booking);
        });

        $payload = validManualBookingPayload();

        $this->actingAs($this->mitra)->post("/mitra/packages/{$this->package->id}/manual-booking", $payload);
        $this->actingAs($this->mitra)->post("/mitra/packages/{$this->package->id}/manual-booking", $payload);

        expect(User::where('email', 'budi.pelanggan@example.com')->count())->toBe(1);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — auto-create client', function () {
    it('client baru → dibuat dengan role client + timezone paket', function () {
        $booking = Booking::factory()
            ->forPackage($this->package)
            ->forClient($this->mitra)
            ->create(['mitra_id' => $this->mitra->id]);

        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('createManual')->once()->andReturn($booking);
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload());

        $client = User::where('email', 'budi.pelanggan@example.com')->first();
        expect($client)->not->toBeNull()
            ->and($client->name)->toBe('Budi Pelanggan')
            ->and($client->role)->toBe('client')
            ->and($client->timezone)->toBe($this->package->timezone)
            ->and(Hash::check('anything', $client->password))->toBeFalse();  // random password
    });

    it('client existing → TIDAK dibuat ulang, data lama dipertahankan', function () {
        $existingClient = User::factory()->client()->create([
            'email' => 'budi.pelanggan@example.com',
            'name' => 'Nama Lama',
            'must_change_password' => false,
        ]);

        $booking = Booking::factory()
            ->forPackage($this->package)
            ->forClient($existingClient)
            ->create(['mitra_id' => $this->mitra->id]);

        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('createManual')->once()->andReturn($booking);
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload());

        $existingClient->refresh();
        expect($existingClient->name)->toBe('Nama Lama');  // tidak berubah
    });
});

describe('Data Integrity — store', function () {
    it('store → BookingCreationService::createManual dipanggil dengan data valid', function () {
        $booking = Booking::factory()
            ->forPackage($this->package)
            ->forClient($this->mitra)
            ->create(['mitra_id' => $this->mitra->id]);

        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('createManual')
                ->once()
                ->with(
                    Mockery::on(fn ($p) => $p->id === $this->package->id),
                    Mockery::on(fn ($c) => $c->email === 'budi.pelanggan@example.com'),
                    Mockery::on(fn ($u) => $u->id === $this->mitra->id),
                    Mockery::type('array'),
                )
                ->andReturn($booking);
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload());
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation)
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('nama_pelanggan kosong → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'nama_pelanggan' => '',
            ]))
            ->assertSessionHasErrors('nama_pelanggan');
    });

    it('email_pelanggan format invalid → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'email_pelanggan' => 'bukan-email',
            ]))
            ->assertSessionHasErrors('email_pelanggan');
    });

    it('tanggal_keberangkatan < H-0 → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'tanggal_keberangkatan' => now($this->package->timezone)->subDay()->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors('tanggal_keberangkatan');
    });

    it('jumlah_peserta 0 → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'jumlah_peserta' => 0,
                'participants' => [],
            ]))
            ->assertSessionHasErrors('jumlah_peserta');
    });

    it('participants kosong → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'participants' => [],
            ]))
            ->assertSessionHasErrors('participants');
    });

    it('participants count != jumlah_peserta → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'jumlah_peserta' => 3,
                'participants' => [
                    ['nama' => 'P1'],
                    ['nama' => 'P2'],
                ],
            ]))
            ->assertSessionHasErrors('participants');
    });

    it('participants.*.nama kosong → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'participants' => [
                    ['nama' => '', 'umur' => 25],
                    ['nama' => 'P2', 'umur' => 30],
                ],
            ]))
            ->assertSessionHasErrors('participants.0.nama');
    });

    it('participants.*.umur > 120 → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'participants' => [
                    ['nama' => 'P1', 'umur' => 121],
                    ['nama' => 'P2', 'umur' => 30],
                ],
            ]))
            ->assertSessionHasErrors('participants.0.umur');
    });
});

describe('Error Handling — ValidationException from service', function () {
    it('service throw ValidationException → redirect back + errors', function () {
        $this->mock(BookingCreationService::class, function (MockInterface $m) {
            $m->shouldReceive('createManual')
                ->once()
                ->andThrow(ValidationException::withMessages([
                    'jumlah_peserta' => 'Melebihi max peserta.',
                ]));
        });

        $this->actingAs($this->mitra)
            ->from("/mitra/packages/{$this->package->id}/manual-booking")
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload())
            ->assertRedirect()
            ->assertSessionHasErrors('jumlah_peserta');
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — BVA nama', function () {
    it('nama_pelanggan 2 karakter (min) → lolos', function () {
        $booking = Booking::factory()
            ->forPackage($this->package)
            ->forClient($this->mitra)
            ->create(['mitra_id' => $this->mitra->id]);

        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('createManual')->once()->andReturn($booking);
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'nama_pelanggan' => 'ab',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('nama_pelanggan 255 karakter (max) → lolos', function () {
        $booking = Booking::factory()
            ->forPackage($this->package)
            ->forClient($this->mitra)
            ->create(['mitra_id' => $this->mitra->id]);

        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('createManual')->once()->andReturn($booking);
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'nama_pelanggan' => str_repeat('a', 255),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('nama_pelanggan 256 karakter (max + 1) → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'nama_pelanggan' => str_repeat('a', 256),
            ]))
            ->assertSessionHasErrors('nama_pelanggan');
    });
});

describe('Edge Cases — tanggal boundary', function () {
    it('tanggal H-0 (min) → lolos', function () {
        $booking = Booking::factory()
            ->forPackage($this->package)
            ->forClient($this->mitra)
            ->create(['mitra_id' => $this->mitra->id]);

        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('createManual')->once()->andReturn($booking);
        });

        $minDate = now($this->package->timezone)->format('Y-m-d');

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'tanggal_keberangkatan' => $minDate,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('tanggal H-1 (min - 1) → error', function () {
        $belowMinDate = now($this->package->timezone)->subDay()->format('Y-m-d');

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/manual-booking", validManualBookingPayload([
                'tanggal_keberangkatan' => $belowMinDate,
            ]))
            ->assertSessionHasErrors('tanggal_keberangkatan');
    });
});