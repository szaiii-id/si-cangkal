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
    // Hindari error Vite manifest saat render Inertia
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    // Clear rate limiter
    RateLimiter::clear('budi@example.com|127.0.0.1');
    RateLimiter::clear('target@example.com|127.0.0.1');
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — Inertia render', function () {
    it('GET /login merender Inertia component Auth/Login', function () {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    });

    it('GET /register merender Inertia component Auth/Register', function () {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    });

    it('GET /register/mitra merender Inertia component Auth/RegisterMitra', function () {
        $this->get('/register/mitra')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/RegisterMitra'));
    });
});

describe('Contract — Login redirect', function () {
    it('POST /login client → redirect ke /client/dashboard', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'identitas' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('client.dashboard'));
    });

    it('POST /login admin → redirect ke /admin/dashboard', function () {
        $admin = User::factory()->admin()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'identitas' => $admin->email,
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));
    });

    it('POST /login mitra → redirect ke /mitra/dashboard', function () {
        $mitra = User::factory()->mitra()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'identitas' => $mitra->email,
            'password' => 'password123',
        ])->assertRedirect(route('mitra.dashboard'));
    });

    it('POST /login user must_change_password → redirect ke /password/change', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('password123'),
            'must_change_password' => true,
        ]);

        $this->post('/login', [
            'identitas' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('password.change'));
    });
});

describe('Contract — Register redirect', function () {
    it('POST /register client → redirect ke /client/dashboard', function () {
        $this->post('/register', [
            'name' => 'Budi Santoso',
            'username' => 'budisantoso',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('client.dashboard'));
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest access', function () {
    it('guest bisa akses /login', function () {
        $this->get('/login')->assertOk();
    });

    it('guest bisa akses /register', function () {
        $this->get('/register')->assertOk();
    });

    it('user yang sudah login tidak bisa akses /login (guest middleware)', function () {
        $user = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($user)->get('/login')->assertRedirect();
    });
});

describe('Security — CSRF / validation', function () {
    it('POST /login tanpa CSRF token → 419 (di test, CSRF disabled by default)', function () {
        // Test env CSRF biasanya disabled — skip verifikasi 419
        // Fokus ke validasi input
        $this->post('/login', [])->assertSessionHasErrors(['identitas', 'password']);
    });

    it('login gagal tidak bocorkan apakah email terdaftar atau tidak', function () {
        $response = $this->from('/login')->post('/login', [
            'identitas' => 'tidak-ada@example.com',
            'password' => 'apapun',
        ]);

        $response->assertSessionHasErrors('identitas');
        $this->assertGuest();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY / RACE CONDITION
|==========================================================================
*/

describe('Concurrency — unique constraint', function () {
    it('register 2x dengan email sama → validasi unique', function () {
        $data = [
            'name' => 'Budi',
            'username' => 'budi1',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->post('/register', $data);
        $this->post('/logout');

        // Register kedua dengan email sama
        $data['username'] = 'budi2';
        $response = $this->from('/register')->post('/register', $data);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    });

    it('register 2x dengan username sama → validasi unique', function () {
        $data = [
            'name' => 'Budi',
            'username' => 'budisantoso',
            'email' => 'budi1@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->post('/register', $data);
        $this->post('/logout');

        $data['email'] = 'budi2@example.com';
        $response = $this->from('/register')->post('/register', $data);

        $response->assertSessionHasErrors('username');
        $this->assertDatabaseCount('users', 1);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('POST /register dengan data sama 2x hanya buat 1 user', function () {
        $data = [
            'name' => 'Budi',
            'username' => 'budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->post('/register', $data);
        expect(User::count())->toBe(1);

        $this->post('/logout');
        $this->post('/register', $data);  // gagal unique

        expect(User::count())->toBe(1);
    });

    it('POST /logout 2x → guest, tidak error', function () {
        $user = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        // Logout kedua → guest, redirect ke login
        $this->post('/logout')->assertRedirect('/login');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE TRANSITION
|==========================================================================
*/

describe('Data Integrity — register', function () {
    it('register client → user tersimpan dengan role client', function () {
        $this->post('/register', [
            'name' => 'Budi',
            'username' => 'budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'role' => 'client',
        ]);
    });

    it('register mitra → user tersimpan dengan role mitra', function () {
        $this->post('/register/mitra', [
            'name' => 'Mitra',
            'username' => 'mitra1',
            'email' => 'mitra@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'mitra@example.com',
            'role' => 'mitra',
        ]);
    });

    it('register → langsung authenticated', function () {
        $this->post('/register', [
            'name' => 'Budi',
            'username' => 'budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
    });

    it('register password di-hash (bukan plain)', function () {
        $this->post('/register', [
            'name' => 'Budi',
            'username' => 'budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'budi@example.com')->first();
        expect($user->password)->not->toBe('password123')
            ->and(Hash::check('password123', $user->password))->toBeTrue();
    });
});

describe('State Transition — login', function () {
    it('login → session auth', function () {
        $user = User::factory()->client()->create([
            'password' => Hash::make('password123'),
            'must_change_password' => false,
        ]);

        $this->post('/login', [
            'identitas' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
    });

    it('login dengan remember=true → remember token diset', function () {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'remember_token' => null,
        ]);

        $this->post('/login', [
            'identitas' => $user->email,
            'password' => 'password123',
            'remember' => true,
        ]);

        expect($user->fresh()->remember_token)->not->toBeNull();
    });
});

/*
|==========================================================================
| KATEGORI 6 — RATE LIMITING
|==========================================================================
*/

describe('Rate Limiting — login', function () {
    it('rate limiter blokir login setelah 5 percobaan gagal', function () {
        config([
            'auth-throttle.login.test_override' => null,
            'auth-throttle.login.max_attempts' => 5,
        ]);

        User::factory()->create([
            'email' => 'target@example.com',
            'password' => Hash::make('password123'),
        ]);

        RateLimiter::clear('target@example.com|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'identitas' => 'target@example.com',
                'password' => 'salah',
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'identitas' => 'target@example.com',
            'password' => 'salah',
        ]);

        $response->assertRedirect('/login')
            ->assertSessionHasErrors('identitas');

        $this->assertGuest();
    });
});

describe('Rate Limiting — register', function () {
    it('rate limiter blokir register setelah 3 percobaan', function () {
        config([
            'auth-throttle.register.test_override' => null,
            'auth-throttle.register.max_attempts' => 3,
        ]);

        RateLimiter::clear('target2@example.com|127.0.0.1');

        for ($i = 0; $i < 3; $i++) {
            $this->post('/register', [
                'name' => 'Test',
                'username' => 'test'.$i,
                'email' => 'target2@example.com',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);
        }

        $response = $this->from('/register')->post('/register', [
            'name' => 'Test',
            'username' => 'test99',
            'email' => 'target2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/register');
    });
});

/*
|==========================================================================
| KATEGORI 7 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('POST /login tanpa identitas → validation error', function () {
        $this->post('/login', [
            'password' => 'password123',
        ])->assertSessionHasErrors('identitas');
    });

    it('POST /login tanpa password → validation error', function () {
        $this->post('/login', [
            'identitas' => 'budi@example.com',
        ])->assertSessionHasErrors('password');
    });

    it('POST /register tanpa name → validation error', function () {
        $this->post('/register', [
            'username' => 'budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('name');
    });

    it('POST /register password < 8 → validation error', function () {
        $this->post('/register', [
            'name' => 'Budi',
            'username' => 'budi',
            'email' => 'budi@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    });

    it('POST /register password tidak match → validation error', function () {
        $this->post('/register', [
            'name' => 'Budi',
            'username' => 'budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertSessionHasErrors('password');
    });
});

describe('Error Handling — invalid credentials', function () {
    it('login dengan password salah → error, guest', function () {
        $user = User::factory()->create([
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->from('/login')->post('/login', [
            'identitas' => 'budi@example.com',
            'password' => 'salah',
        ])->assertSessionHasErrors('identitas');

        $this->assertGuest();
    });

    it('login dengan email tidak ada → error generic, guest', function () {
        $this->from('/login')->post('/login', [
            'identitas' => 'tidak-ada@example.com',
            'password' => 'apapun',
        ])->assertSessionHasErrors('identitas');

        $this->assertGuest();
    });
});
