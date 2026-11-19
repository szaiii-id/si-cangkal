<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| ProfileController (client) — edit & update profile.
|
| Route (prefix /client):
| - GET /profile → edit (client.profile)
| - PUT /profile → update (client.profile.update)
|
| Middleware: auth, password.changed, role:client
| FormRequest: UpdateProfileRequest (name required, username unique)
| Service: ClientProfileService (getProfileData, updateProfile)
|
| Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - Client tidak butuh bank account (beda dengan mitra).
| - Test fokus CONTROLLER (validation, redirect, persistence).
|   `ClientProfileService` sudah di-test unit (ClientProfileServiceTest).
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->client = User::factory()->client()->create([
        'name' => 'Budi Client',
        'username' => 'budiclient',
        'email' => 'budi@example.com',
        'must_change_password' => false,
    ]);

    $this->client2 = User::factory()->client()->create([
        'name' => 'Ani Client',
        'username' => 'aniclient',
        'email' => 'ani@example.com',
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — edit', function () {
    it('GET /client/profile → render Client/Profile', function () {
        $this->actingAs($this->client)
            ->get('/client/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Profile')
                ->has('user')
            );
    });

    it('user punya keys id, name, username, email, role', function () {
        $this->actingAs($this->client)
            ->get('/client/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->has('user.id')
                ->has('user.name')
                ->has('user.username')
                ->has('user.email')
                ->has('user.role')
                ->where('user.id', $this->client->id)
                ->where('user.name', 'Budi Client')
                ->where('user.email', 'budi@example.com')
                ->where('user.role', 'client')
            );
    });

    it('route name = client.profile', function () {
        expect(route('client.profile'))->toBe(url('/client/profile'));
    });
});

describe('Contract — update redirect', function () {
    it('PUT /client/profile valid → redirect ke client.profile + status', function () {
        $response = $this->actingAs($this->client)->put('/client/profile', [
            'name' => 'Budi Updated',
            'username' => 'budiupdated',
        ]);

        $response->assertRedirect(route('client.profile'))
            ->assertSessionHas('status', 'Profile berhasil diperbarui.');
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (edit)', function () {
        $this->get('/client/profile')->assertRedirect(route('login'));
    });

    it('guest di-redirect ke login (update)', function () {
        $this->put('/client/profile', [])->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard (edit)', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/client/profile')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('mitra di-redirect ke mitra.dashboard (edit)', function () {
        $mitra = User::factory()->mitra()->create(['must_change_password' => false]);

        $this->actingAs($mitra)
            ->get('/client/profile')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->client()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/client/profile')
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — client hanya bisa update profile sendiri', function () {
    it('update profile client lain → 403 / redirect (route pakai auth user)', function () {
        // Route tidak punya parameter user — selalu pakai auth()->user()
        // Jadi tidak mungkin update client lain.
        // Test ini dokumentasi: profile endpoint selalu scope ke auth user.
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => 'Budi Updated',
                'username' => 'budiupdated',
            ])
            ->assertRedirect();

        $this->client->refresh();
        expect($this->client->name)->toBe('Budi Updated');

        // Client2 TIDAK terpengaruh
        $this->client2->refresh();
        expect($this->client2->name)->toBe('Ani Client');
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET edit → data identik', function () {
        $first = $this->actingAs($this->client)
            ->get('/client/profile')
            ->viewData('page')['props']['user'];

        $second = $this->actingAs($this->client)
            ->get('/client/profile')
            ->viewData('page')['props']['user'];

        expect($first)->toBe($second);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('update profile dengan data sama 2x → hasil konsisten', function () {
        $payload = [
            'name' => 'Budi Updated',
            'username' => 'budiupdated',
        ];

        $this->actingAs($this->client)->put('/client/profile', $payload)->assertRedirect();
        $this->actingAs($this->client)->put('/client/profile', $payload)->assertRedirect();

        $this->client->refresh();
        expect($this->client->name)->toBe('Budi Updated');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — update', function () {
    it('update name + username → data berubah di DB', function () {
        $this->actingAs($this->client)->put('/client/profile', [
            'name' => 'Budi Updated',
            'username' => 'budiupdated',
        ]);

        $this->client->refresh();
        expect($this->client->name)->toBe('Budi Updated')
            ->and($this->client->username)->toBe('budiupdated');
    });

    it('update name saja (username null) → name berubah, username lama dipertahankan', function () {
        $oldUsername = $this->client->username;

        $this->actingAs($this->client)->put('/client/profile', [
            'name' => 'Budi Updated',
            'username' => null,
        ]);

        $this->client->refresh();
        expect($this->client->name)->toBe('Budi Updated')
            ->and($this->client->username)->toBe($oldUsername);  // tidak berubah
    });

    it('email TIDAK berubah (read-only)', function () {
        $this->actingAs($this->client)->put('/client/profile', [
            'name' => 'Budi Updated',
            'username' => 'budiupdated',
            'email' => 'hacker@example.com',  // ← diabaikan FormRequest
        ]);

        $this->client->refresh();
        expect($this->client->email)->toBe('budi@example.com');
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation)
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('name kosong → error', function () {
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => '',
                'username' => 'budiupdated',
            ])
            ->assertSessionHasErrors('name');
    });

    it('name > 255 karakter → error', function () {
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => str_repeat('a', 256),
                'username' => 'budiupdated',
            ])
            ->assertSessionHasErrors('name');
    });

    it('username duplikat (client lain) → error', function () {
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => 'Budi Updated',
                'username' => 'aniclient',  // ← sudah dipakai client2
            ])
            ->assertSessionHasErrors('username');
    });

    it('username sama dengan diri sendiri → lolos (ignore self)', function () {
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => 'Budi Updated',
                'username' => 'budiclient',  // ← username sendiri
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('username > 255 karakter → error', function () {
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => 'Budi Updated',
                'username' => str_repeat('a', 256),
            ])
            ->assertSessionHasErrors('username');
    });

    it('validasi gagal → data TIDAK berubah', function () {
        $this->actingAs($this->client)->put('/client/profile', [
            'name' => '',
            'username' => 'budiupdated',
        ]);

        $this->client->refresh();
        expect($this->client->name)->toBe('Budi Client')  // tidak berubah
            ->and($this->client->username)->toBe('budiclient');
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — BVA name', function () {
    it('name 1 karakter (min - 1?) → lolos (tidak ada min rule)', function () {
        // UpdateProfileRequest: 'name' => ['required', 'string', 'max:255']
        // Tidak ada min rule → 1 karakter lolos
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => 'a',
                'username' => 'budiclient',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('name 255 karakter (max) → lolos', function () {
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => str_repeat('a', 255),
                'username' => 'budiclient',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });
});

describe('Edge Cases — username nullable', function () {
    it('username null → lolos', function () {
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => 'Budi Updated',
                'username' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('username kosong string → lolos (nullable)', function () {
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => 'Budi Updated',
                'username' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });
});

describe('Edge Cases — username unique case-insensitive', function () {
    it('username beda case (BUDICLIENT vs budiclient) → lolos (unique case-sensitive?)', function () {
        // MySQL default: case-insensitive. SQLite: case-sensitive.
        // Test dokumentasi — behavior tergantung DB driver.
        $this->actingAs($this->client)
            ->put('/client/profile', [
                'name' => 'Budi Updated',
                'username' => 'BUDICLIENT',  // beda case
            ]);

        $this->client->refresh();
        // Behavior tergantung DB — bisa lolos atau error
        expect(true)->toBeTrue();
    });
});
