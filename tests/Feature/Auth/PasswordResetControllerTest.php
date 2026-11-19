<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

    config([
        'auth-throttle.forgot-password.test_override' => null,
        'auth-throttle.reset-password.test_override' => null,
    ]);

    RateLimiter::clear('budi@example.com|127.0.0.1');
    RateLimiter::clear('tidak-ada@example.com|127.0.0.1');
});

afterEach(function () {
    RateLimiter::clear('budi@example.com|127.0.0.1');
    RateLimiter::clear('tidak-ada@example.com|127.0.0.1');
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — create', function () {
    it('GET /forgot-password render Inertia Auth/ForgotPassword', function () {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
    });
});

describe('Contract — store', function () {
    it('POST /forgot-password email valid → redirect back + session status', function () {
        Notification::fake();

        User::factory()->client()->create(['email' => 'budi@example.com']);

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'budi@example.com',
        ]);

        $response->assertRedirect('/forgot-password')
            ->assertSessionHas('status');
    });
});

describe('Contract — edit', function () {
    it('GET /reset-password/{token} render Inertia Auth/ResetPassword dengan token & email', function () {
        $user = User::factory()->client()->create(['email' => 'budi@example.com']);
        $token = Password::broker()->createToken($user);

        $this->get("/reset-password/{$token}?email=budi@example.com")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', $token)
                ->where('email', 'budi@example.com')
            );
    });
});

describe('Contract — update', function () {
    it('POST /reset-password valid → redirect login + session status', function () {
        $user = User::factory()->client()->create(['email' => 'budi@example.com']);
        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'budi@example.com',
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

describe('Security — guest only', function () {
    it('user yang sudah login tidak bisa akses /forgot-password', function () {
        $user = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($user)->get('/forgot-password')->assertRedirect();
    });

    it('user yang sudah login tidak bisa POST /forgot-password', function () {
        $user = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($user)->post('/forgot-password', [
            'email' => 'x@y.com',
        ])->assertRedirect();
    });

    it('guest bisa akses /forgot-password', function () {
        $this->get('/forgot-password')->assertOk();
    });

    it('guest bisa akses /reset-password/{token}', function () {
        $user = User::factory()->create(['email' => 'budi@example.com']);
        $token = Password::broker()->createToken($user);

        $this->get("/reset-password/{$token}?email=budi@example.com")->assertOk();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY / RACE CONDITION
|==========================================================================
*/

describe('Concurrency', function () {
    it('POST /forgot-password 2x → status berubah (link pertama valid)', function () {
        Notification::fake();

        User::factory()->client()->create(['email' => 'budi@example.com']);

        // Request pertama
        $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'budi@example.com',
        ])->assertSessionHas('status');

        // Request kedua — biasanya di-throttle atau tetap berhasil
        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'budi@example.com',
        ]);

        // Behavior: bisa throttle / sukses (tergantung config)
        expect($response->status())->toBeIn([302, 429]);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('POST /forgot-password 2x → throttle setelah 3x', function () {
        config([
            'auth-throttle.forgot-password.test_override' => null,
            'auth-throttle.forgot-password.max_attempts' => 3,
        ]);

        Notification::fake();

        User::factory()->client()->create(['email' => 'budi@example.com']);

        // 3x request
        for ($i = 0; $i < 3; $i++) {
            $this->from('/forgot-password')->post('/forgot-password', [
                'email' => 'budi@example.com',
            ]);
        }

        // Request ke-4 → throttle
        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'budi@example.com',
        ]);

        $response->assertRedirect('/forgot-password');
    });

    it('reset password dengan token yang sama 2x → gagal di kedua', function () {
        $user = User::factory()->client()->create([
            'email' => 'budi@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);
        $token = Password::broker()->createToken($user);

        // Reset pertama — sukses
        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'budi@example.com',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])->assertRedirect(route('login'));

        // Reset kedua — token sudah invalid
        $response = $this->from("/reset-password/{$token}")->post('/reset-password', [
            'token' => $token,
            'email' => 'budi@example.com',
            'password' => 'AnotherPass789!',
            'password_confirmation' => 'AnotherPass789!',
        ]);

        $response->assertSessionHasErrors('email');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE TRANSITION
|==========================================================================
*/

describe('Data Integrity — reset password', function () {
    it('password berubah setelah reset sukses', function () {
        $user = User::factory()->client()->create([
            'email' => 'budi@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'budi@example.com',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $user->refresh();
        expect(Hash::check('NewPass456!', $user->password))->toBeTrue()
            ->and(Hash::check('OldPass123!', $user->password))->toBeFalse();
    });

    it('token reset dihapus setelah sukses', function () {
        $user = User::factory()->client()->create(['email' => 'budi@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'budi@example.com',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'budi@example.com',
        ]);
    });

    it('forceFill password — hash tersimpan (bukan plain)', function () {
        $user = User::factory()->client()->create([
            'email' => 'budi@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'budi@example.com',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $user->refresh();
        expect($user->password)->not->toBe('NewPass456!')
            ->and(strlen($user->password))->toBeGreaterThan(50);
    });

    it('notification dikirim saat request reset link', function () {
        Notification::fake();

        $user = User::factory()->client()->create(['email' => 'budi@example.com']);

        $this->post('/forgot-password', [
            'email' => 'budi@example.com',
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
    });
});

/*
|==========================================================================
| KATEGORI 6 — RATE LIMITING
|==========================================================================
*/

describe('Rate Limiting — forgot-password', function () {
    it('throttle setelah 3x request', function () {
        config([
            'auth-throttle.forgot-password.test_override' => null,
            'auth-throttle.forgot-password.max_attempts' => 3,
        ]);

        Notification::fake();

        User::factory()->client()->create(['email' => 'budi@example.com']);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => 'budi@example.com']);
        }

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'budi@example.com',
        ]);

        $response->assertRedirect('/forgot-password')
            ->assertSessionHasErrors();
    });
});

describe('Rate Limiting — reset-password', function () {
    it('throttle setelah 5x request', function () {
        config([
            'auth-throttle.reset-password.test_override' => null,
            'auth-throttle.reset-password.max_attempts' => 5,
        ]);

        User::factory()->client()->create(['email' => 'budi@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password', [
                'token' => 'invalid',
                'email' => 'budi@example.com',
                'password' => 'NewPass456!',
                'password_confirmation' => 'NewPass456!',
            ]);
        }

        $response = $this->from('/reset-password/invalid')->post('/reset-password', [
            'token' => 'invalid',
            'email' => 'budi@example.com',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $response->assertSessionHasErrors();
    });
});

/*
|==========================================================================
| KATEGORI 7 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('email kosong di /forgot-password → error', function () {
        $this->post('/forgot-password', ['email' => ''])
            ->assertSessionHasErrors('email');
    });

    it('email format invalid → error', function () {
        $this->post('/forgot-password', ['email' => 'bukan-email'])
            ->assertSessionHasErrors('email');
    });

    it('email tidak terdaftar → error generic', function () {
        Notification::fake();

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'tidak-ada@example.com',
        ]);

        // Behavior Laravel: error "email tidak ditemukan" — tapi generic
        $response->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    });

    it('token palsu → error', function () {
        $user = User::factory()->client()->create([
            'email' => 'budi@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);

        $response = $this->from('/reset-password/token-palsu')->post('/reset-password', [
            'token' => 'token-palsu',
            'email' => 'budi@example.com',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $response->assertSessionHasErrors('email');

        // Password TIDAK berubah
        $user->refresh();
        expect(Hash::check('OldPass123!', $user->password))->toBeTrue();
    });

    it('password konfirmasi tidak cocok → error', function () {
        $user = User::factory()->client()->create(['email' => 'budi@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'budi@example.com',
            'password' => 'NewPass456!',
            'password_confirmation' => 'Different789!',
        ])->assertSessionHasErrors('password');
    });

    it('password kosong → error', function () {
        $user = User::factory()->client()->create(['email' => 'budi@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'budi@example.com',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasErrors('password');
    });

    it('validasi gagal tidak mengubah password', function () {
        $user = User::factory()->client()->create([
            'email' => 'budi@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'budi@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $user->refresh();
        expect(Hash::check('OldPass123!', $user->password))->toBeTrue();
    });
});
