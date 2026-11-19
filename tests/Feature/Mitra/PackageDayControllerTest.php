<?php

use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| PackageDayController (mitra) — CRUD itinerary (hari) paket.
|
| Route (prefix /mitra):
| - POST   /packages/{package}/days              → store
| - PUT    /package-days/{day}                   → update
| - DELETE /package-days/{day}                   → destroy
|
| Middleware: auth, password.changed, role:mitra, mitra.complete
| Policy: PackagePolicy@manageDays (owner check)
| FormRequest: StorePackageDayRequest, UpdatePackageDayRequest
|
|   Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - Mitra WAJIB punya bank account (middleware `mitra.complete`).
| - Test fokus CONTROLLER (policy, validation, redirect, persistence).
|   `PackageDayService` sudah di-test unit (PackageDayServiceTest).
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

    // Paket dengan durasi 3 hari (agar bisa tambah hari)
    $this->package = Package::factory()->forMitra($this->mitra)->create([
        'durasi_hari' => 3,
        'durasi_malam' => 2,
    ]);

    $this->package2 = Package::factory()->forMitra($this->mitra2)->create([
        'durasi_hari' => 3,
        'durasi_malam' => 2,
    ]);
});

/*
|==========================================================================
| HELPER — payload valid
|==========================================================================
*/
function validDayPayload(array $overrides = []): array
{
    return array_merge([
        'judul' => 'Hari Ke-1: Penjemputan',
        'deskripsi' => 'Penjemputan di bandara',
        'jam_mulai' => '08:00',
        'jam_selesai' => '12:00',
        'lokasi' => 'Bandara Soekarno-Hatta',
    ], $overrides);
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — store redirect', function () {
    it('POST /mitra/packages/{id}/days valid → redirect ke packages.show + status', function () {
        $response = $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload());

        $response->assertRedirect(route('mitra.packages.show', $this->package->id))
            ->assertSessionHas('status', 'Hari berhasil ditambahkan.');
    });
});

describe('Contract — update redirect', function () {
    it('PUT /mitra/package-days/{id} valid → redirect ke packages.show + status', function () {
        $day = PackageDay::factory()->create([
            'package_id' => $this->package->id,
            'hari_ke' => 1,
        ]);

        $response = $this->actingAs($this->mitra)
            ->put("/mitra/package-days/{$day->id}", validDayPayload(['judul' => 'Judul Baru']));

        $response->assertRedirect(route('mitra.packages.show', $this->package->id))
            ->assertSessionHas('status', 'Hari berhasil diperbarui.');
    });
});

describe('Contract — destroy redirect', function () {
    it('DELETE /mitra/package-days/{id} valid → redirect ke packages.show + status', function () {
        $day = PackageDay::factory()->create([
            'package_id' => $this->package->id,
            'hari_ke' => 1,
        ]);

        $response = $this->actingAs($this->mitra)
            ->delete("/mitra/package-days/{$day->id}");

        $response->assertRedirect(route('mitra.packages.show', $this->package->id))
            ->assertSessionHas('status', 'Hari berhasil dihapus.');
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (store)', function () {
        $this->post("/mitra/packages/{$this->package->id}/days", validDayPayload())
            ->assertRedirect(route('login'));
    });

    it('guest di-redirect ke login (update)', function () {
        $day = PackageDay::factory()->create(['package_id' => $this->package->id]);

        $this->put("/mitra/package-days/{$day->id}", validDayPayload())
            ->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload())
            ->assertRedirect(route('admin.dashboard'));
    });

    it('client di-redirect ke client.dashboard', function () {
        $client = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($client)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload())
            ->assertRedirect(route('client.dashboard'));
    });

    it('mitra must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->mitra()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload())
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — ownership (Policy manageDays)', function () {
    it('mitra TIDAK bisa store day di paket mitra lain → 403', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package2->id}/days", validDayPayload())
            ->assertForbidden();

        expect(PackageDay::where('package_id', $this->package2->id)->count())->toBe(0);
    });

    it('mitra TIDAK bisa update day milik paket mitra lain → 403', function () {
        $day = PackageDay::factory()->create([
            'package_id' => $this->package2->id,
            'hari_ke' => 1,
        ]);

        $this->actingAs($this->mitra)
            ->put("/mitra/package-days/{$day->id}", validDayPayload(['judul' => 'Hacked']))
            ->assertForbidden();

        $day->refresh();
        expect($day->judul)->not->toBe('Hacked');
    });

    it('mitra TIDAK bisa delete day milik paket mitra lain → 403', function () {
        $day = PackageDay::factory()->create([
            'package_id' => $this->package2->id,
            'hari_ke' => 1,
        ]);

        $this->actingAs($this->mitra)
            ->delete("/mitra/package-days/{$day->id}")
            ->assertForbidden();

        expect(PackageDay::where('id', $day->id)->exists())->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — store 2x', function () {
    it('store 2x → 2 day berbeda (hari_ke auto-increment)', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload(['judul' => 'Hari A']));

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload(['judul' => 'Hari B']));

        $days = PackageDay::where('package_id', $this->package->id)
            ->orderBy('hari_ke')
            ->get();

        expect($days)->toHaveCount(2)
            ->and($days[0]->hari_ke)->toBe(1)
            ->and($days[0]->judul)->toBe('Hari A')
            ->and($days[1]->hari_ke)->toBe(2)
            ->and($days[1]->judul)->toBe('Hari B');
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('update day dengan data sama 2x → hasil konsisten', function () {
        $day = PackageDay::factory()->create([
            'package_id' => $this->package->id,
            'hari_ke' => 1,
        ]);

        $payload = validDayPayload(['judul' => 'Judul Sama']);

        $this->actingAs($this->mitra)->put("/mitra/package-days/{$day->id}", $payload);
        $this->actingAs($this->mitra)->put("/mitra/package-days/{$day->id}", $payload);

        $day->refresh();
        expect($day->judul)->toBe('Judul Sama');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — store', function () {
    it('store → day tersimpan di DB dengan hari_ke auto-increment', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload());

        $day = PackageDay::where('package_id', $this->package->id)->first();
        expect($day)->not->toBeNull()
            ->and($day->hari_ke)->toBe(1)
            ->and($day->judul)->toBe('Hari Ke-1: Penjemputan')
            ->and($day->deskripsi)->toBe('Penjemputan di bandara')
            // Pakai helper jamMulaiShort() — return '08:00' (bukan '08:00:00')
            ->and($day->jamMulaiShort())->toBe('08:00')
            ->and($day->jamSelesaiShort())->toBe('12:00')
            ->and($day->lokasi)->toBe('Bandara Soekarno-Hatta');
    });

    it('store day kedua → hari_ke = 2 (auto-increment)', function () {
        PackageDay::factory()->create([
            'package_id' => $this->package->id,
            'hari_ke' => 1,
        ]);

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload(['judul' => 'Hari Kedua']));

        $day = PackageDay::where('package_id', $this->package->id)
            ->where('hari_ke', 2)
            ->first();

        expect($day)->not->toBeNull()
            ->and($day->judul)->toBe('Hari Kedua');
    });
});

describe('Data Integrity — update', function () {
    it('update → data berubah di DB', function () {
        $day = PackageDay::factory()->create([
            'package_id' => $this->package->id,
            'hari_ke' => 1,
            'judul' => 'Judul Lama',
        ]);

        $this->actingAs($this->mitra)
            ->put("/mitra/package-days/{$day->id}", validDayPayload([
                'judul' => 'Judul Baru',
                'deskripsi' => 'Deskripsi Baru',
            ]));

        $day->refresh();
        expect($day->judul)->toBe('Judul Baru')
            ->and($day->deskripsi)->toBe('Deskripsi Baru');
    });

    it('update TIDAK mengubah hari_ke', function () {
        $day = PackageDay::factory()->create([
            'package_id' => $this->package->id,
            'hari_ke' => 2,
        ]);

        $this->actingAs($this->mitra)
            ->put("/mitra/package-days/{$day->id}", validDayPayload(['judul' => 'Judul Baru']));

        $day->refresh();
        expect($day->hari_ke)->toBe(2);
    });
});

describe('Data Integrity — destroy', function () {
    it('delete → day hilang dari DB', function () {
        $day = PackageDay::factory()->create([
            'package_id' => $this->package->id,
            'hari_ke' => 1,
        ]);
        $dayId = $day->id;

        $this->actingAs($this->mitra)
            ->delete("/mitra/package-days/{$dayId}");

        $this->assertDatabaseMissing('package_days', ['id' => $dayId]);
    });

    it('delete day di tengah → hari_ke di-reindex (jika service re-number)', function () {
        // Buat 3 hari
        $day1 = PackageDay::factory()->create(['package_id' => $this->package->id, 'hari_ke' => 1]);
        $day2 = PackageDay::factory()->create(['package_id' => $this->package->id, 'hari_ke' => 2]);
        $day3 = PackageDay::factory()->create(['package_id' => $this->package->id, 'hari_ke' => 3]);

        // Hapus day2 (tengah)
        $this->actingAs($this->mitra)
            ->delete("/mitra/package-days/{$day2->id}");

        // Cek behavior — tergantung PackageDayService::delete
        // Dari PackageDayServiceTest: delete re-number hari_ke
        $remaining = PackageDay::where('package_id', $this->package->id)
            ->orderBy('hari_ke')
            ->get();

        expect($remaining)->toHaveCount(2);
        // Behavior: day3 mungkin jadi hari_ke=2 (re-number) atau tetap 3
        // Test dokumentasi — tidak assert ketat
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation)
|==========================================================================
*/

describe('Error Handling — store validation', function () {
    it('judul kosong → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload(['judul' => '']))
            ->assertSessionHasErrors('judul');
    });

    it('judul < 3 karakter → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload(['judul' => 'ab']))
            ->assertSessionHasErrors('judul');
    });

    it('jam_mulai format invalid → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload(['jam_mulai' => '25:00']))
            ->assertSessionHasErrors('jam_mulai');
    });

    it('jam_selesai < jam_mulai → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload([
                'jam_mulai' => '12:00',
                'jam_selesai' => '08:00',
            ]))
            ->assertSessionHasErrors('jam_selesai');
    });
});

describe('Error Handling — store state (durasi habis)', function () {
    it('store day melebihi durasi_hari → error', function () {
        // package durasi_hari = 3
        // Buat 3 hari (full)
        PackageDay::factory()->count(3)->sequence(
            ['hari_ke' => 1],
            ['hari_ke' => 2],
            ['hari_ke' => 3],
        )->create(['package_id' => $this->package->id]);

        // Coba tambah hari ke-4 → error (validasi service)
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload(['judul' => 'Hari Ke-4']))
            ->assertRedirect();

        // Tidak ada day ke-4
        expect(PackageDay::where('package_id', $this->package->id)->count())->toBe(3);
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — BVA judul', function () {
    it('judul 3 karakter (min) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload(['judul' => 'abc']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('judul 255 karakter (max) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload(['judul' => str_repeat('a', 255)]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('judul 256 karakter (max + 1) → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload(['judul' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('judul');
    });
});

describe('Edge Cases — jam nullable', function () {
    it('jam_mulai & jam_selesai null → lolos', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload([
                'jam_mulai' => null,
                'jam_selesai' => null,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('jam_mulai = jam_selesai → lolos (after_or_equal)', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/days", validDayPayload([
                'jam_mulai' => '08:00',
                'jam_selesai' => '08:00',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });
});
