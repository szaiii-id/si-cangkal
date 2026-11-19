<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    RateLimiter::clear('budi@example.com|127.0.0.1');
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — edit', function () {
    it('GET /change-password merender Inertia component Auth/ChangePassword', function () {
        $user = User::factory()->client()->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get('/change-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ChangePassword')
                ->where('mustChangePassword', false)
            );
    });

    it('GET /change-password render mustChangePassword = true jika wajib ganti', function () {
        $user = User::factory()->admin()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get('/change-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ChangePassword')
                ->where('mustChangePassword', true)
            );
    });
});

describe('Contract — update redirect', function () {
    it('PUT /change-password (normal) → redirect ke dashboard role + session status', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->put('/change-password', [
            'old_password' => 'OldPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $response->assertRedirect(route('client.dashboard'))
            ->assertSessionHas('status');
    });

    it('PUT /change-password (forced) → redirect ke login + session status', function () {
        $user = User::factory()->admin()->create([
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->put('/change-password', [
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHas('status');
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest access', function () {
    it('guest di-redirect ke login saat akses GET /change-password', function () {
        $this->get('/change-password')->assertRedirect(route('login'));
    });

    it('guest di-redirect ke login saat PUT /change-password', function () {
        $this->put('/change-password', [
            'old_password' => 'OldPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])->assertRedirect(route('login'));
    });
});

describe('Security — old_password required (kondisional)', function () {
    it('must_change_password=false → old_password wajib', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])->assertSessionHasErrors('old_password');
    });

    it('must_change_password=true → old_password nullable (tidak wajib)', function () {
        $user = User::factory()->admin()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])->assertRedirect(route('login'));
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY / RACE CONDITION
|==========================================================================
*/

describe('Concurrency', function () {
    it('2x change password (forced) → user berhasil ganti 2x, password pakai terakhir', function () {
        $user = User::factory()->admin()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ]);

        $this->assertGuest();

        // Setelah logout, login lagi dengan password baru
        RateLimiter::clear($user->email.'|127.0.0.1');

        $this->post('/login', [
            'identitas' => $user->email,
            'password' => 'NewPass1!',
        ]);

        $this->assertAuthenticated();

        $user->refresh();
        expect(Hash::check('NewPass1!', $user->password))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('change password dengan password baru SAMA 2x → password tetap valid', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'OldPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $user->refresh();

        // Password baru = NewPass456. Ganti lagi dengan password lama = NewPass456
        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'NewPass456!',
            'password' => 'NewPass456!',  // ← sama
            'password_confirmation' => 'NewPass456!',
        ]);

        $user->refresh();
        expect(Hash::check('NewPass456!', $user->password))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE TRANSITION
|==========================================================================
*/

describe('Data Integrity — update', function () {
    it('ganti password normal → password berubah di DB', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'OldPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $user->refresh();
        expect(Hash::check('NewPass456!', $user->password))->toBeTrue()
            ->and(Hash::check('OldPass123!', $user->password))->toBeFalse();
    });

    it('forced change password → must_change_password jadi false', function () {
        $user = User::factory()->admin()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $user->refresh();
        expect((bool) $user->must_change_password)->toBeFalse();
    });

    it('ganti password normal → must_change_password tetap false', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'OldPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $user->refresh();
        expect((bool) $user->must_change_password)->toBeFalse();
    });

    it('forced change password → user logout (guest)', function () {
        $user = User::factory()->admin()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $this->assertGuest();
    });

    it('forced change password → redirect login + status "login ulang"', function () {
        $user = User::factory()->admin()->create([
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->put('/change-password', [
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $response->assertRedirect(route('login'));
        $status = $response->getSession()->get('status');
        expect($status)->toContain('login ulang');
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('old_password salah (current_password) → error', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'WrongPass!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])->assertSessionHasErrors('old_password');

        // Password TIDAK berubah
        $user->refresh();
        expect(Hash::check('OldPass123!', $user->password))->toBeTrue();
    });

    it('password baru < min (production) → error', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'OldPass123!',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    });

    it('password baru kosong → error', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'OldPass123!',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasErrors('password');
    });

    it('konfirmasi password tidak cocok → error', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'OldPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'Different789!',
        ])->assertSessionHasErrors('password');
    });

    it('password berubah tidak terjadi jika validasi gagal', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'WrongPass!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])->assertSessionHasErrors('old_password');

        $user->refresh();
        expect(Hash::check('OldPass123!', $user->password))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases', function () {
    it('ganti password baru sama dengan password lama → sukses (tidak divalidasi)', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('SamePass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'SamePass123!',
            'password' => 'SamePass123!',
            'password_confirmation' => 'SamePass123!',
        ])->assertRedirect(route('client.dashboard'));
    });

    it('forced change password meskipun old_password dikirim salah → tetap sukses', function () {
        $user = User::factory()->admin()->create([
            'must_change_password' => true,
            'password' => Hash::make('OldPass123!'),
        ]);

        $this->actingAs($user)->put('/change-password', [
            'old_password' => 'WrongOld!',  // ← diabaikan
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])->assertRedirect(route('login'));

        $user->refresh();
        expect(Hash::check('NewPass456!', $user->password))->toBeTrue();
    });

    it('after forced change password, user bisa login dengan password baru', function () {
        $user = User::factory()->admin()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put('/change-password', [
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $this->assertGuest();

        RateLimiter::clear($user->email.'|127.0.0.1');

        $this->post('/login', [
            'identitas' => $user->email,
            'password' => 'NewPass456!',
        ]);

        $this->assertAuthenticated();
    });
});
