<?php

use App\Enums\PackageFacilityTipe;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\PackageFacility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| PackageFacilityController (mitra) — CRUD fasilitas include/exclude.
|
| Route (prefix /mitra):
| - POST   /packages/{package}/facilities          → store
| - PUT    /package-facilities/{facility}          → update
| - DELETE /package-facilities/{facility}          → destroy
|
| Middleware: auth, password.changed, role:mitra, mitra.complete
| Policy: PackagePolicy@manageFacilities (owner check)
| FormRequest: StorePackageFacilityRequest, UpdatePackageFacilityRequest
|
| Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - Mitra WAJIB punya bank account (middleware `mitra.complete`).
| - `PackageFacility` model cast `tipe` ke enum `PackageFacilityTipe`.
|   Assert pakai enum instance (bukan string).
| - Test fokus CONTROLLER (policy, validation, redirect, persistence).
|   `PackageFacilityService` sudah di-test unit (PackageFacilityServiceTest).
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

    $this->package = Package::factory()->forMitra($this->mitra)->create();
    $this->package2 = Package::factory()->forMitra($this->mitra2)->create();
});

/*
|==========================================================================
| HELPER — payload valid
|==========================================================================
*/
function validFacilityPayload(array $overrides = []): array
{
    return array_merge([
        'tipe' => PackageFacilityTipe::INCLUDE->value,
        'nama' => 'Transportasi',
    ], $overrides);
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — store redirect', function () {
    it('POST /mitra/packages/{id}/facilities valid → redirect ke packages.show + status', function () {
        $response = $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload());

        $response->assertRedirect(route('mitra.packages.show', $this->package->id))
            ->assertSessionHas('status', 'Fasilitas berhasil ditambahkan.');
    });
});

describe('Contract — update redirect', function () {
    it('PUT /mitra/package-facilities/{id} valid → redirect ke packages.show + status', function () {
        $facility = PackageFacility::factory()->create([
            'package_id' => $this->package->id,
            'tipe' => 'include',
            'nama' => 'Lama',
        ]);

        $response = $this->actingAs($this->mitra)
            ->put("/mitra/package-facilities/{$facility->id}", validFacilityPayload(['nama' => 'Baru']));

        $response->assertRedirect(route('mitra.packages.show', $this->package->id))
            ->assertSessionHas('status', 'Fasilitas berhasil diperbarui.');
    });
});

describe('Contract — destroy redirect', function () {
    it('DELETE /mitra/package-facilities/{id} valid → redirect ke packages.show + status', function () {
        $facility = PackageFacility::factory()->create([
            'package_id' => $this->package->id,
            'tipe' => 'include',
        ]);

        $response = $this->actingAs($this->mitra)
            ->delete("/mitra/package-facilities/{$facility->id}");

        $response->assertRedirect(route('mitra.packages.show', $this->package->id))
            ->assertSessionHas('status', 'Fasilitas berhasil dihapus.');
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (store)', function () {
        $this->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload())
            ->assertRedirect(route('login'));
    });

    it('guest di-redirect ke login (update)', function () {
        $facility = PackageFacility::factory()->create(['package_id' => $this->package->id]);

        $this->put("/mitra/package-facilities/{$facility->id}", validFacilityPayload())
            ->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload())
            ->assertRedirect(route('admin.dashboard'));
    });

    it('client di-redirect ke client.dashboard', function () {
        $client = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($client)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload())
            ->assertRedirect(route('client.dashboard'));
    });

    it('mitra must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->mitra()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload())
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — ownership (Policy manageFacilities)', function () {
    it('mitra TIDAK bisa store facility di paket mitra lain → 403', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package2->id}/facilities", validFacilityPayload())
            ->assertForbidden();

        expect(PackageFacility::where('package_id', $this->package2->id)->count())->toBe(0);
    });

    it('mitra TIDAK bisa update facility milik paket mitra lain → 403', function () {
        $facility = PackageFacility::factory()->create([
            'package_id' => $this->package2->id,
            'nama' => 'Lama',
        ]);

        $this->actingAs($this->mitra)
            ->put("/mitra/package-facilities/{$facility->id}", validFacilityPayload(['nama' => 'Hacked']))
            ->assertForbidden();

        $facility->refresh();
        expect($facility->nama)->not->toBe('Hacked');
    });

    it('mitra TIDAK bisa delete facility milik paket mitra lain → 403', function () {
        $facility = PackageFacility::factory()->create([
            'package_id' => $this->package2->id,
        ]);

        $this->actingAs($this->mitra)
            ->delete("/mitra/package-facilities/{$facility->id}")
            ->assertForbidden();

        expect(PackageFacility::where('id', $facility->id)->exists())->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — store 2x', function () {
    it('store 2x → 2 facility berbeda (urutan auto-increment)', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload([
                'nama' => 'Transportasi',
            ]));

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload([
                'nama' => 'Makan',
            ]));

        $facilities = PackageFacility::where('package_id', $this->package->id)
            ->orderBy('id')
            ->get();

        expect($facilities)->toHaveCount(2)
            ->and($facilities[0]->nama)->toBe('Transportasi')
            ->and($facilities[1]->nama)->toBe('Makan');
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('update facility dengan data sama 2x → hasil konsisten', function () {
        $facility = PackageFacility::factory()->create([
            'package_id' => $this->package->id,
            'nama' => 'Lama',
        ]);

        $payload = validFacilityPayload(['nama' => 'Sama']);

        $this->actingAs($this->mitra)->put("/mitra/package-facilities/{$facility->id}", $payload);
        $this->actingAs($this->mitra)->put("/mitra/package-facilities/{$facility->id}", $payload);

        $facility->refresh();
        expect($facility->nama)->toBe('Sama');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — store', function () {
    it('store → facility tersimpan di DB', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload([
                'tipe' => PackageFacilityTipe::INCLUDE->value,
                'nama' => 'Transportasi',
            ]));

        $facility = PackageFacility::where('package_id', $this->package->id)->first();
        expect($facility)->not->toBeNull()
            ->and($facility->tipe)->toBe(PackageFacilityTipe::INCLUDE)
            ->and($facility->nama)->toBe('Transportasi');
    });

    it('store tipe exclude → tersimpan dengan tipe exclude', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload([
                'tipe' => PackageFacilityTipe::EXCLUDE->value,
                'nama' => 'Tiket Pesawat',
            ]));

        $facility = PackageFacility::where('package_id', $this->package->id)->first();
        expect($facility->tipe)->toBe(PackageFacilityTipe::EXCLUDE)
            ->and($facility->nama)->toBe('Tiket Pesawat');
    });

    it('store include + exclude → 2 facility berbeda tipe', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload([
                'tipe' => PackageFacilityTipe::INCLUDE->value,
                'nama' => 'Transportasi',
            ]));

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload([
                'tipe' => PackageFacilityTipe::EXCLUDE->value,
                'nama' => 'Tiket Pesawat',
            ]));

        $facilities = PackageFacility::where('package_id', $this->package->id)
            ->orderBy('id')
            ->get();

        expect($facilities)->toHaveCount(2)
            ->and($facilities[0]->tipe)->toBe(PackageFacilityTipe::INCLUDE)
            ->and($facilities[1]->tipe)->toBe(PackageFacilityTipe::EXCLUDE);
    });
});

describe('Data Integrity — update', function () {
    it('update → data berubah di DB', function () {
        $facility = PackageFacility::factory()->create([
            'package_id' => $this->package->id,
            'tipe' => 'include',
            'nama' => 'Lama',
        ]);

        $this->actingAs($this->mitra)
            ->put("/mitra/package-facilities/{$facility->id}", validFacilityPayload([
                'tipe' => PackageFacilityTipe::EXCLUDE->value,
                'nama' => 'Baru',
            ]));

        $facility->refresh();
        expect($facility->tipe)->toBe(PackageFacilityTipe::EXCLUDE)
            ->and($facility->nama)->toBe('Baru');
    });
});

describe('Data Integrity — destroy', function () {
    it('delete → facility hilang dari DB', function () {
        $facility = PackageFacility::factory()->create([
            'package_id' => $this->package->id,
        ]);
        $facilityId = $facility->id;

        $this->actingAs($this->mitra)
            ->delete("/mitra/package-facilities/{$facilityId}");

        $this->assertDatabaseMissing('package_facilities', ['id' => $facilityId]);
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation)
|==========================================================================
*/

describe('Error Handling — store validation', function () {
    it('tipe kosong → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload(['tipe' => '']))
            ->assertSessionHasErrors('tipe');
    });

    it('tipe invalid → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload(['tipe' => 'ngawur']))
            ->assertSessionHasErrors('tipe');
    });

    it('nama kosong → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload(['nama' => '']))
            ->assertSessionHasErrors('nama');
    });

    it('nama < 2 karakter → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload(['nama' => 'a']))
            ->assertSessionHasErrors('nama');
    });

    it('validasi gagal → tidak ada data tersimpan', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", [
                'tipe' => 'ngawur',
                'nama' => '',
            ]);

        expect(PackageFacility::where('package_id', $this->package->id)->count())->toBe(0);
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — BVA nama', function () {
    it('nama 2 karakter (min) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload(['nama' => 'ab']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('nama 255 karakter (max) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload([
                'nama' => str_repeat('a', 255),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('nama 256 karakter (max + 1) → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload([
                'nama' => str_repeat('a', 256),
            ]))
            ->assertSessionHasErrors('nama');
    });

    it('nama 1 karakter (min - 1) → error', function () {
        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$this->package->id}/facilities", validFacilityPayload(['nama' => 'a']))
            ->assertSessionHasErrors('nama');
    });
});
