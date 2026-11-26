<?php

use App\Enums\PackageStatus;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\PackageFacility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| PackageController (mitra) — CRUD paket + publish/archive/restore.
|
| Route (prefix /mitra):
| - GET    /packages                          → index
| - GET    /packages/create                   → create
| - POST   /packages                          → store
| - GET    /packages/{package}                → show
| - GET    /packages/{package}/edit           → edit
| - PUT    /packages/{package}                → update
| - DELETE /packages/{package}                → destroy
| - GET    /packages/{package}/complete-phase2→ completePhase2
| - POST   /packages/{package}/publish        → publish
| - POST   /packages/{package}/archive        → archive
| - POST   /packages/{package}/restore        → restore
|
| Middleware: auth, password.changed, role:mitra, mitra.complete
| Policy: PackagePolicy (viewAny, view, update, delete, publish, archive, restore)
|
|   Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - `Storage::fake('public')` — untuk cover_image upload.
| - `PackageService` sudah di-test unit (PackageServiceTest) — test ini
|   fokus CONTROLLER (policy, validation, redirect, persistence).
| - Mitra WAJIB punya bank account (middleware `mitra.complete`).
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    Storage::fake('public');
    Storage::fake('r2_public');

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);

    // Bank account agar lolos middleware `mitra.complete`
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
});

function makePackageFor(User $mitra, array $overrides = []): Package
{
    return Package::factory()->forMitra($mitra)->create(array_merge([
        'status' => PackageStatus::DRAFT->value,
    ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /mitra/packages → render Mitra/Packages/Index', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/packages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Packages/Index')
                ->has('packages')
                ->has('filters')
                ->has('statusCounts')
            );
    });

    it('filters default: search=null, status=null, mitra_id=current user', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/packages')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', null)
                ->where('filters.status', null)
                ->where('filters.mitra_id', $this->mitra->id)
            );
    });

    it('hanya menampilkan paket milik mitra sendiri', function () {
        makePackageFor($this->mitra, ['nama' => 'Paket Sendiri']);
        makePackageFor($this->mitra2, ['nama' => 'Paket Mitra Lain']);

        $this->actingAs($this->mitra)
            ->get('/mitra/packages')
            ->assertInertia(fn (Assert $page) => $page
                ->where('packages.total', 1)
                ->where('packages.data.0.nama', 'Paket Sendiri')
            );
    });

    it('route name = mitra.packages.index', function () {
        expect(route('mitra.packages.index'))->toBe(url('/mitra/packages'));
    });
});

describe('Contract — create', function () {
    it('GET /mitra/packages/create → render Mitra/Packages/Create', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/packages/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Packages/Create')
            );
    });
});

describe('Contract — show', function () {
    it('GET /mitra/packages/{id} → render Mitra/Packages/Show', function () {
        $package = makePackageFor($this->mitra);

        $this->actingAs($this->mitra)
            ->get("/mitra/packages/{$package->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Packages/Show')
                ->has('package')
                ->where('package.id', $package->id)
            );
    });
});

describe('Contract — edit', function () {
    it('GET /mitra/packages/{id}/edit → render Mitra/Packages/Edit', function () {
        $package = makePackageFor($this->mitra);

        $this->actingAs($this->mitra)
            ->get("/mitra/packages/{$package->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Packages/Edit')
                ->has('package')
            );
    });
});

describe('Contract — completePhase2', function () {
    it('GET /mitra/packages/{id}/complete-phase2 → render Mitra/Packages/CompletePhase2', function () {
        $package = makePackageFor($this->mitra);

        $this->actingAs($this->mitra)
            ->get("/mitra/packages/{$package->id}/complete-phase2")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Packages/CompletePhase2')
                ->has('package')
            );
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (index)', function () {
        $this->get('/mitra/packages')->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard (index)', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/mitra/packages')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('client di-redirect ke client.dashboard (index)', function () {
        $client = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($client)
            ->get('/mitra/packages')
            ->assertRedirect(route('client.dashboard'));
    });

    it('mitra must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->mitra()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/mitra/packages')
            ->assertRedirect(route('password.change'));
    });

    it('mitra tanpa bank account di-redirect ke onboarding', function () {
        $noAccount = User::factory()->mitra()->create(['must_change_password' => false]);

        $this->actingAs($noAccount)
            ->get('/mitra/packages')
            ->assertRedirect(route('mitra.onboarding.index'));
    });
});

describe('Security — ownership (Policy)', function () {
    it('mitra TIDAK bisa akses paket milik mitra lain (show)', function () {
        $package = makePackageFor($this->mitra2);

        $this->actingAs($this->mitra)
            ->get("/mitra/packages/{$package->id}")
            ->assertForbidden();
    });

    it('mitra TIDAK bisa edit paket milik mitra lain', function () {
        $package = makePackageFor($this->mitra2);

        $this->actingAs($this->mitra)
            ->get("/mitra/packages/{$package->id}/edit")
            ->assertForbidden();
    });

    it('mitra TIDAK bisa update paket milik mitra lain', function () {
        $package = makePackageFor($this->mitra2);

        $this->actingAs($this->mitra)
            ->put("/mitra/packages/{$package->id}", validPackagePayload())
            ->assertForbidden();
    });

    it('mitra TIDAK bisa delete paket milik mitra lain', function () {
        $package = makePackageFor($this->mitra2);

        $this->actingAs($this->mitra)
            ->delete("/mitra/packages/{$package->id}")
            ->assertForbidden();
    });

    it('mitra TIDAK bisa publish paket milik mitra lain', function () {
        $package = makePackageFor($this->mitra2, [
            'status' => PackageStatus::DRAFT->value,
        ]);

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$package->id}/publish")
            ->assertForbidden();
    });

    it('mitra TIDAK bisa archive paket milik mitra lain', function () {
        $package = makePackageFor($this->mitra2, [
            'status' => PackageStatus::AKTIF->value,
        ]);

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$package->id}/archive")
            ->assertForbidden();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET index → data identik', function () {
        makePackageFor($this->mitra, ['nama' => 'Paket A']);
        makePackageFor($this->mitra, ['nama' => 'Paket B']);

        $first = $this->actingAs($this->mitra)
            ->get('/mitra/packages')
            ->viewData('page')['props']['packages']['total'];

        $second = $this->actingAs($this->mitra)
            ->get('/mitra/packages')
            ->viewData('page')['props']['packages']['total'];

        expect($first)->toBe($second);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('update paket dengan data sama 2x → hasil konsisten', function () {
        $package = makePackageFor($this->mitra);
        $payload = validPackagePayload(['nama' => 'Paket Updated']);

        $this->actingAs($this->mitra)->put("/mitra/packages/{$package->id}", $payload);
        $this->actingAs($this->mitra)->put("/mitra/packages/{$package->id}", $payload);

        $package->refresh();
        expect($package->nama)->toBe('Paket Updated');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — store', function () {
    it('store → paket tersimpan dengan mitra_id = current user', function () {
        $this->actingAs($this->mitra)->post('/mitra/packages', validPackagePayload());

        $package = Package::where('mitra_id', $this->mitra->id)->first();
        expect($package)->not->toBeNull()
            ->and($package->nama)->toBe('Paket Wisata Test')
            ->and($package->mitra_id)->toBe($this->mitra->id)
            ->and($package->status)->toBe(PackageStatus::DRAFT);
    });

    it('store → redirect ke complete-phase2 + status', function () {
        $response = $this->actingAs($this->mitra)->post('/mitra/packages', validPackagePayload());

        $package = Package::where('mitra_id', $this->mitra->id)->first();

        $response->assertRedirect(route('mitra.packages.complete-phase2', $package->id))
            ->assertSessionHas('status');
    });

    it('store dengan cover_image → file tersimpan', function () {
        $file = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $this->actingAs($this->mitra)->post('/mitra/packages', validPackagePayload([
            'cover_image' => $file,
        ]));

        $package = Package::where('mitra_id', $this->mitra->id)->first();
        expect($package->cover_image)->toStartWith('packages/')
            ->and($package->cover_image)->toEndWith('.jpg');

        Storage::disk('r2_public')->assertExists($package->cover_image);
    });
});

describe('Data Integrity — update', function () {
    it('update → data berubah di DB + redirect ke show', function () {
        $package = makePackageFor($this->mitra);

        $response = $this->actingAs($this->mitra)->put(
            "/mitra/packages/{$package->id}",
            validPackagePayload(['nama' => 'Paket Updated'])
        );

        $response->assertRedirect(route('mitra.packages.show', $package->id))
            ->assertSessionHas('status');

        $package->refresh();
        expect($package->nama)->toBe('Paket Updated');
    });
});

describe('Data Integrity — destroy', function () {
    it('delete → paket hilang dari DB (soft delete) + redirect', function () {
        $package = makePackageFor($this->mitra);
        $packageId = $package->id;

        $response = $this->actingAs($this->mitra)
            ->delete("/mitra/packages/{$packageId}");

        $response->assertRedirect(route('mitra.packages.index'))
            ->assertSessionHas('status', 'Paket berhasil dihapus.');

        $this->assertSoftDeleted('packages', ['id' => $packageId]);
    });
});

describe('Data Integrity — publish', function () {
    it('publish → status AKTIF + redirect show', function () {
        $package = makePackageFor($this->mitra, [
            'status' => PackageStatus::DRAFT->value,
            'durasi_hari' => 2,   // ← eksplisit
            'durasi_malam' => 1,
        ]);

        // Setup days (2 hari) + facilities agar publish valid
        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);
        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 2]);
        PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'include']);
        PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'exclude']);

        $response = $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$package->id}/publish");

        $response->assertRedirect(route('mitra.packages.show', $package->id))
            ->assertSessionHas('status', 'Paket berhasil dipublish.');

        $package->refresh();
        expect($package->status)->toBe(PackageStatus::AKTIF);
    });
});

describe('Data Integrity — archive', function () {
    it('archive dari AKTIF → ARSIP + redirect show', function () {
        $package = makePackageFor($this->mitra, ['status' => PackageStatus::AKTIF->value]);

        $response = $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$package->id}/archive");

        $response->assertRedirect(route('mitra.packages.show', $package->id))
            ->assertSessionHas('status', 'Paket berhasil diarsipkan.');

        $package->refresh();
        expect($package->status)->toBe(PackageStatus::ARSIP);
    });
});

describe('Data Integrity — restore', function () {
    it('restore dari ARSIP → DRAFT + redirect show', function () {
        $package = makePackageFor($this->mitra, ['status' => PackageStatus::ARSIP->value]);

        $response = $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$package->id}/restore");

        $response->assertRedirect(route('mitra.packages.show', $package->id))
            ->assertSessionHas('status', 'Paket berhasil direstore.');

        $package->refresh();
        expect($package->status)->toBe(PackageStatus::DRAFT);
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation & State)
|==========================================================================
*/

describe('Error Handling — store validation', function () {
    it('nama kosong → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/packages', validPackagePayload(['nama' => '']))
            ->assertSessionHasErrors('nama');
    });

    it('nama < 3 karakter → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/packages', validPackagePayload(['nama' => 'ab']))
            ->assertSessionHasErrors('nama');
    });

    it('kategori invalid → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/packages', validPackagePayload(['kategori' => 'ngawur']))
            ->assertSessionHasErrors('kategori');
    });

    it('durasi_hari < 1 → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/packages', validPackagePayload(['durasi_hari' => 0]))
            ->assertSessionHasErrors('durasi_hari');
    });

    it('timezone invalid → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/packages', validPackagePayload(['timezone' => 'Invalid/Zone']))
            ->assertSessionHasErrors('timezone');
    });

    it('harga_per_rombongan > 0 tanpa max_peserta → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/packages', validPackagePayload([
                'harga_per_rombongan' => 5_000_000,
                'max_peserta' => null,
            ]))
            ->assertSessionHasErrors('max_peserta');
    });

    it('cover_image bukan gambar → error', function () {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->actingAs($this->mitra)
            ->post('/mitra/packages', validPackagePayload(['cover_image' => $file]))
            ->assertSessionHasErrors('cover_image');
    });
});

describe('Error Handling — publish state', function () {
    it('publish paket yang sudah AKTIF → error', function () {
        $package = makePackageFor($this->mitra, ['status' => PackageStatus::AKTIF->value]);

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$package->id}/publish")
            ->assertRedirect();  // redirect back dengan error

        $package->refresh();
        expect($package->status)->toBe(PackageStatus::AKTIF);  // tidak berubah
    });
});

describe('Error Handling — archive state', function () {
    it('archive paket yang sudah ARSIP → error', function () {
        $package = makePackageFor($this->mitra, ['status' => PackageStatus::ARSIP->value]);

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$package->id}/archive")
            ->assertRedirect();

        $package->refresh();
        expect($package->status)->toBe(PackageStatus::ARSIP);
    });
});

describe('Error Handling — restore state', function () {
    it('restore paket yang bukan ARSIP → error', function () {
        $package = makePackageFor($this->mitra, ['status' => PackageStatus::DRAFT->value]);

        $this->actingAs($this->mitra)
            ->post("/mitra/packages/{$package->id}/restore")
            ->assertRedirect();

        $package->refresh();
        expect($package->status)->toBe(PackageStatus::DRAFT);
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — index filters', function () {
    it('filter status=draft → hanya draft', function () {
        makePackageFor($this->mitra, ['status' => PackageStatus::DRAFT->value]);
        makePackageFor($this->mitra, ['status' => PackageStatus::AKTIF->value]);

        $this->actingAs($this->mitra)
            ->get('/mitra/packages?status=draft')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'draft')
            );
    });

    it('search by nama → return match', function () {
        makePackageFor($this->mitra, ['nama' => 'Paket Bali Spesial']);
        makePackageFor($this->mitra, ['nama' => 'Paket Lombok']);

        $this->actingAs($this->mitra)
            ->get('/mitra/packages?search=Bali')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Bali')
            );
    });
});

describe('Edge Cases — BVA durasi', function () {
    it('durasi_hari=1 (min) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/packages', validPackagePayload([
                'durasi_hari' => 1,
                'durasi_malam' => 0,
            ]))
            ->assertSessionHasNoErrors();
    });

    it('durasi_hari=30 (max) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/packages', validPackagePayload([
                'durasi_hari' => 30,
                'durasi_malam' => 29,
            ]))
            ->assertSessionHasNoErrors();
    });

    it('durasi_hari=31 (max + 1) → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/packages', validPackagePayload([
                'durasi_hari' => 31,
                'durasi_malam' => 30,
            ]))
            ->assertSessionHasErrors('durasi_hari');
    });
});
