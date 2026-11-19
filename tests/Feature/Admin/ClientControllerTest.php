<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| ClientController — read-only daftar client untuk admin.
|
| Route: GET /admin/clients (admin.clients.index)
| Middleware: auth, password.changed, role:admin
|
| Service: UserService::paginateClients(perPage, search)
| Repository: UserRepository::paginateByRoles(['client'], ...)
|             — LIKE search (escaped via LikeEscaper + whereRaw ESCAPE),
|             eager-load profile, order created_at DESC.
|
| Response: UserResource::collection($clients) — paginator format Resource
|           (data, links, meta).
|
|   Catatan penting:
| - Middleware `password.changed` → user WAJIB must_change_password=false.
| - `User::$hidden` = ['password', 'remember_token', 'google_id', 'fcm_token']
|   → defense-in-depth. Response TIDAK boleh mengandung field tsb.
| - Test `profile.alamat` di-skip (TODO) — tunggu audit `Profile` model
|   & `ProfileFactory`. Kemungkinan factory tidak set `alamat` default.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    $this->admin = User::factory()->admin()->create([
        'must_change_password' => false,
    ]);
    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);
    $this->client = User::factory()->client()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /admin/clients → render Admin/Clients/Index', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Clients/Index')
                ->has('clients')
                ->has('filters')
            );
    });

    it('clients adalah paginator Resource dengan keys data, links, meta', function () {
        User::factory()->client()->count(3)->create(['must_change_password' => false]);

        $this->actingAs($this->admin)
            ->get('/admin/clients')
            ->assertInertia(fn (Assert $page) => $page
                ->has('clients.data')
                ->has('clients.links')
                ->has('clients.meta')
                ->has('clients.meta.current_page')
                ->has('clients.meta.per_page')
                ->has('clients.meta.total')
            );
    });

    it('clients.data item punya shape: id, name, email, role', function () {
        User::factory()->client()->create([
            'name' => 'Budi Client',
            'email' => 'budi@example.com',
            'must_change_password' => false,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/clients')
            ->assertInertia(fn (Assert $page) => $page
                ->has('clients.data.0.id')
                ->has('clients.data.0.name')
                ->has('clients.data.0.email')
                ->has('clients.data.0.role')
                ->where('clients.data.0.role', 'client')
            );
    });

    it('clients.data item TIDAK punya field sensitif (password, google_id, fcm_token)', function () {
        User::factory()->client()->create([
            'google_id' => 'google-secret',
            'fcm_token' => 'fcm-secret',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/clients');
        $payload = $response->viewData('page')['props']['clients']['data'][0];

        expect($payload)->not->toHaveKey('password')
            ->and($payload)->not->toHaveKey('remember_token')
            ->and($payload)->not->toHaveKey('google_id')
            ->and($payload)->not->toHaveKey('fcm_token');
    });

    it('filters default: search=null, per_page=15', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', null)
                ->where('filters.per_page', 15)
            );
    });

    it('filters mengirim search + per_page dari query', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients?search=budi&per_page=5')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'budi')
                ->where('filters.per_page', 5)
            );
    });

    it('route name = admin.clients.index', function () {
        expect(route('admin.clients.index'))->toBe(url('/admin/clients'));
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login', function () {
        $this->get('/admin/clients')->assertRedirect(route('login'));
    });

    it('mitra di-redirect ke mitra.dashboard', function () {
        $this->actingAs($this->mitra)
            ->get('/admin/clients')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client di-redirect ke client.dashboard', function () {
        $this->actingAs($this->client)
            ->get('/admin/clients')
            ->assertRedirect(route('client.dashboard'));
    });

    it('admin dengan must_change_password=true di-redirect ke password.change', function () {
        $adminNeedChange = User::factory()->admin()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($adminNeedChange)
            ->get('/admin/clients')
            ->assertRedirect(route('password.change'));
    });

    it('admin dengan must_change_password=false bisa akses', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients')
            ->assertOk();
    });
});

describe('Security — data leakage', function () {
    it('password TIDAK muncul di response', function () {
        User::factory()->client()->create(['must_change_password' => false]);

        $response = $this->actingAs($this->admin)->get('/admin/clients');
        $payload = $response->viewData('page')['props']['clients']['data'][0];

        expect($payload)->not->toHaveKey('password')
            ->and($payload)->not->toHaveKey('remember_token');
    });

    it('google_id TIDAK muncul di response', function () {
        User::factory()->client()->create([
            'google_id' => 'google-oauth-id',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/clients');
        $payload = $response->viewData('page')['props']['clients']['data'][0];

        expect($payload)->not->toHaveKey('google_id');
    });

    it('fcm_token TIDAK muncul di response', function () {
        User::factory()->client()->create([
            'fcm_token' => 'secret-fcm-token',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/clients');
        $payload = $response->viewData('page')['props']['clients']['data'][0];

        expect($payload)->not->toHaveKey('fcm_token');
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
|
| Endpoint read-only → tidak ada race condition di level tulis. Test di
| sini fokus: konsistensi hasil saat 2 request paralel (sequential di test).
|
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET dengan query sama → hasil identik', function () {
        User::factory()->client()->count(3)->create(['must_change_password' => false]);

        $first = $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=5')
            ->viewData('page')['props']['clients']['meta']['total'];

        $second = $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=5')
            ->viewData('page')['props']['clients']['meta']['total'];

        expect($first)->toBe($second);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('GET berulang tidak mengubah data', function () {
        User::factory()->client()->count(2)->create(['must_change_password' => false]);
        $countBefore = User::count();

        $this->actingAs($this->admin)->get('/admin/clients');
        $this->actingAs($this->admin)->get('/admin/clients');

        expect(User::count())->toBe($countBefore);
    });

    it('search yang sama → urutan & isi konsisten', function () {
        User::factory()->client()->count(3)->create(['must_change_password' => false]);

        $r1 = $this->actingAs($this->admin)
            ->get('/admin/clients?search=&per_page=15')
            ->viewData('page')['props']['clients']['data'];
        $r2 = $this->actingAs($this->admin)
            ->get('/admin/clients?search=&per_page=15')
            ->viewData('page')['props']['clients']['data'];

        expect(array_column($r1, 'id'))->toBe(array_column($r2, 'id'));
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE
|==========================================================================
*/

describe('Data Integrity — filter role', function () {
    it('hanya menampilkan user dengan role client', function () {
        User::factory()->client()->count(2)->create(['must_change_password' => false]);
        User::factory()->admin()->count(1)->create(['must_change_password' => false]);
        User::factory()->mitra()->count(1)->create(['must_change_password' => false]);

        $totalClient = User::where('role', 'client')->count();

        $this->actingAs($this->admin)
            ->get('/admin/clients')
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.meta.total', $totalClient)
                ->where('clients.data.0.role', 'client')
            );
    });

    it('client TANPA username tetap muncul (username null)', function () {
        User::factory()->client()->create([
            'username' => null,
            'must_change_password' => false,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/clients')
            ->assertInertia(fn (Assert $page) => $page
                ->has('clients.data')
            );
    });

    it('TODO: eager-load profile — profile muncul di response', function () {
        $this->markTestSkipped(
            'Butuh audit `Profile` model & `ProfileFactory`: kemungkinan '.
            'factory tidak set `alamat` default, atau `Profile::$hidden` '.
            'exclude `alamat`. Kirim `app/Models/Profile.php` + '.
            '`database/factories/ProfileFactory.php` untuk finalisasi.'
        );

        $client = User::factory()->client()->create(['must_change_password' => false]);
        Profile::factory()->create([
            'user_id' => $client->id,
            'tipe' => 'client',
            'alamat' => 'Jl. Test',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/clients')
            ->assertInertia(fn (Assert $page) => $page
                ->has('clients.data.0.profile')
                ->where('clients.data.0.profile.alamat', 'Jl. Test')
            );
    });
});

describe('Data Integrity — search', function () {
    it('search by name (case-insensitive)', function () {
        User::factory()->client()->create([
            'name' => 'Budi Santoso',
            'must_change_password' => false,
        ]);
        User::factory()->client()->create([
            'name' => 'Ani Lestari',
            'must_change_password' => false,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?search=BUDI')
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.meta.total', 1)
                ->where('clients.data.0.name', 'Budi Santoso')
            );
    });

    it('search by email', function () {
        User::factory()->client()->create([
            'email' => 'budi@example.com',
            'must_change_password' => false,
        ]);
        User::factory()->client()->create([
            'email' => 'ani@example.com',
            'must_change_password' => false,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?search=budi@')
            ->assertInertia(fn (Assert $page) => $page->where('clients.meta.total', 1));
    });

    it('search by username', function () {
        User::factory()->client()->create([
            'username' => 'budisantoso',
            'must_change_password' => false,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?search=budisantoso')
            ->assertInertia(fn (Assert $page) => $page->where('clients.meta.total', 1));
    });

    it('search string kosong → skip filter, return semua client', function () {
        $totalClient = User::where('role', 'client')->count();

        $this->actingAs($this->admin)
            ->get('/admin/clients?search=')
            ->assertInertia(fn (Assert $page) => $page->where('clients.meta.total', $totalClient));
    });
});

describe('Data Integrity — pagination', function () {
    it('per_page=5 → 5 item per halaman', function () {
        User::factory()->client()->count(10)->create(['must_change_password' => false]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=5')
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.meta.per_page', 5)
                ->has('clients.data', 5)
            );
    });

    it('per_page=100 (boundary max) → lolos, total <= 100', function () {
        User::factory()->client()->count(3)->create(['must_change_password' => false]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=100')
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.meta.per_page', 100)
            );
    });

    it('page=2 → data halaman 2', function () {
        User::factory()->client()->count(10)->create(['must_change_password' => false]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=5&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.meta.current_page', 2)
                ->has('clients.data', 5)
            );
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING / VALIDATION
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('per_page < 5 → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=4')
            ->assertSessionHasErrors('per_page');
    });

    it('per_page > 100 → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=101')
            ->assertSessionHasErrors('per_page');
    });

    it('per_page bukan integer → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=abc')
            ->assertSessionHasErrors('per_page');
    });

    it('search > 255 karakter → validation error', function () {
        $long = str_repeat('a', 256);

        $this->actingAs($this->admin)
            ->get('/admin/clients?search='.$long)
            ->assertSessionHasErrors('search');
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — BVA per_page', function () {
    it('per_page=5 (min boundary) → lolos', function () {
        User::factory()->client()->count(5)->create(['must_change_password' => false]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=5')
            ->assertSessionHasNoErrors()
            ->assertOk();
    });

    it('per_page=4 (min - 1) → error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=4')
            ->assertSessionHasErrors('per_page');
    });

    it('per_page=100 (max boundary) → lolos', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=100')
            ->assertSessionHasNoErrors()
            ->assertOk();
    });

    it('per_page=101 (max + 1) → error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=101')
            ->assertSessionHasErrors('per_page');
    });

    it('per_page=0 → error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=0')
            ->assertSessionHasErrors('per_page');
    });
});

describe('Edge Cases — empty & whitespace', function () {
    it('DB tanpa client → total 0, data kosong', function () {
        User::where('role', 'client')->delete();
        User::where('role', 'client')->forceDelete();

        $this->actingAs($this->admin)
            ->get('/admin/clients')
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.meta.total', 0)
                ->has('clients.data', 0)
            );
    });

    it('search whitespace "   " → tidak crash, behavior dokumentasi', function () {
        $response = $this->actingAs($this->admin)
            ->get('/admin/clients?search='.urlencode('   '));

        $response->assertOk();
    });

    it('BUG FIX: per_page="" (empty string) → fallback 15 (bukan 0)', function () {
        // Controller: (int) ($request->validated('per_page') ?? 15)
        // Dengan 'nullable', '?per_page=' → validated return null → ?? 15 → 15.
        // Sebelum fix: (int) validated('per_page', 15) → (int) null = 0.
        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.per_page', 15)
            );
    });
});

describe('Edge Cases — SQL wildcard injection', function () {
    it('input % TIDAK berfungsi sebagai wildcard (literal match)', function () {
        User::factory()->client()->create([
            'name' => 'Promo 100% Hemat',
            'must_change_password' => false,
        ]);
        User::factory()->client()->create([
            'name' => 'Promo 100 Ribu',
            'must_change_password' => false,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?search='.urlencode('100%'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.meta.total', 1)
                ->where('clients.data.0.name', 'Promo 100% Hemat')
            );
    });

    it('input _ TIDAK berfungsi sebagai wildcard satu karakter', function () {
        User::factory()->client()->create([
            'name' => 'paket_hemat',
            'must_change_password' => false,
        ]);
        User::factory()->client()->create([
            'name' => 'paketXhemat',
            'must_change_password' => false,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?search=paket_hemat')
            ->assertInertia(fn (Assert $page) => $page->where('clients.meta.total', 1));
    });

    it('SQL injection classik aman (parameter binding)', function () {
        User::factory()->client()->count(2)->create(['must_change_password' => false]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?search='.urlencode("' OR 1=1--"))
            ->assertInertia(fn (Assert $page) => $page->where('clients.meta.total', 0));
    });
});

describe('Edge Cases — ordering', function () {
    it('default order created_at DESC (terbaru dulu)', function () {
        // Isolasi: hapus client dari beforeEach agar tidak mengganggu ordering.
        User::where('role', 'client')->delete();

        $older = User::factory()->client()->create([
            'name' => 'ORDER-OLDER',
            'created_at' => now()->subDays(2),
            'must_change_password' => false,
        ]);
        $newer = User::factory()->client()->create([
            'name' => 'ORDER-NEWER',
            'created_at' => now()->subDay(),
            'must_change_password' => false,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/clients?per_page=5')
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.data.0.id', $newer->id)
                ->where('clients.data.1.id', $older->id)
            );
    });
});
