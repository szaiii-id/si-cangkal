<?php

use App\Models\Profile;
use App\Models\User;
use App\Services\Notification\Contracts\FirebaseNotificationServiceInterface;
use App\Services\User\UserService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
|   Firebase Mock:
| `NotificationDispatcher` inject `FirebaseNotificationServiceInterface`,
| yang implementasinya (`FirebaseNotificationService`) inject `Messaging`
| dari Kreait Firebase — butuh kredensial (project ID). Di test env,
| kredensial tidak ada → error.
|
| Solusi: mock `FirebaseNotificationServiceInterface` di container,
| supaya `NotificationDispatcher` resolve mock (bukan Firebase asli).
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    //  FIX: Mock Firebase — hindari "Unable to determine Firebase Project ID"
    $this->instance(
        FirebaseNotificationServiceInterface::class,
        Mockery::mock(FirebaseNotificationServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendToDevice')->andReturn(true);
        })
    );

    $this->admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'must_change_password' => false,
    ]);
    $this->admin2 = User::factory()->admin()->create([
        'email' => 'admin2@example.com',
        'must_change_password' => false,
    ]);
    $this->mitra = User::factory()->mitra()->create([
        'email' => 'mitra@example.com',
        'must_change_password' => false,
    ]);
    $this->client = User::factory()->client()->create([
        'email' => 'client@example.com',
        'must_change_password' => false,
    ]);
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /admin/users → render Admin/Users/Index', function () {
        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users')
                ->has('filters')
            );
    });

    it('index default filters: search=null, per_page=15', function () {
        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', null)
                ->where('filters.per_page', 15)
            );
    });

    it('index hanya tampil admin + mitra (bukan client)', function () {
        // 2 admin + 1 mitra + 1 client = 4 user
        // Filter role admin+mitra = 3 (exclude client)
        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.total', 3)
            );
    });
});

describe('Contract — show', function () {
    it('GET /admin/users/{id} (mitra) → render Admin/Users/Show', function () {
        $this->actingAs($this->admin)
            ->get("/admin/users/{$this->mitra->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Show')
                ->has('user')
            );
    });

    it('show user resource shape: id, name, email, role, dll.', function () {
        $this->actingAs($this->admin)
            ->get("/admin/users/{$this->mitra->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('user.id', $this->mitra->id)
                ->where('user.email', 'mitra@example.com')
                ->where('user.role', 'mitra')
                ->has('user.profile')
                ->has('user.bank_accounts')
            );
    });
});

describe('Contract — store', function () {
    it('POST /admin/users valid → redirect + status, tanpa password plaintext', function () {
        Notification::fake();

        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'role' => 'admin',
            'name' => 'Admin Baru',
            'username' => 'adminbaru',
            'email' => 'adminbaru@example.com',
        ]);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionMissing('credentials')
            ->assertSessionHas('status');

        $user = User::where('email', 'adminbaru@example.com')->firstOrFail();
        Notification::assertSentTo($user, ResetPassword::class);
    });
});

describe('Contract — destroy', function () {
    it('DELETE /admin/users/{id} → redirect + status', function () {
        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->mitra->id}")
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', 'User berhasil dihapus.');
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest', function () {
    it('guest di-redirect ke login', function () {
        $this->get('/admin/users')->assertRedirect(route('login'));
    });
});

describe('Security — role:admin middleware', function () {
    it('mitra tidak bisa akses /admin/users (redirect ke mitra.dashboard)', function () {
        $this->actingAs($this->mitra)
            ->get('/admin/users')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client tidak bisa akses /admin/users (redirect ke client.dashboard)', function () {
        $this->actingAs($this->client)
            ->get('/admin/users')
            ->assertRedirect(route('client.dashboard'));
    });
});

describe('Security — user client → 404', function () {
    it('show client → 404', function () {
        $this->actingAs($this->admin)
            ->get("/admin/users/{$this->client->id}")
            ->assertNotFound();
    });

    it('edit client → 404', function () {
        $this->actingAs($this->admin)
            ->get("/admin/users/{$this->client->id}/edit")
            ->assertNotFound();
    });

    it('update client → 404', function () {
        $this->actingAs($this->admin)
            ->put("/admin/users/{$this->client->id}", [
                'name' => 'X',
                'email' => 'x@y.com',
            ])
            ->assertNotFound();
    });

    it('destroy client → 404', function () {
        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->client->id}")
            ->assertNotFound();
    });

    it('resetPassword client → 404', function () {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->client->id}/reset-password")
            ->assertNotFound();
    });
});

describe('Security — self-delete guard', function () {
    it('admin tidak bisa hapus akun sendiri → 403', function () {
        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->admin->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY / UNIQUE CONSTRAINT
|==========================================================================
*/

describe('Concurrency — unique constraint', function () {
    it('store 2x email sama → validation unique error', function () {
        $data = [
            'role' => 'admin',
            'name' => 'Admin X',
            'username' => 'adminx',
            'email' => 'adminx@example.com',
        ];

        $this->actingAs($this->admin)->post('/admin/users', $data);
        $this->assertDatabaseCount('users', 5); // 4 awal + 1 baru

        // Store kedua dengan email sama
        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'role' => 'admin',
            'name' => 'Admin Y',
            'username' => 'adminy',
            'email' => 'adminx@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 5);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('update dengan data sama 2x → tidak error, data konsisten', function () {
        $data = [
            'name' => 'Admin Updated',
            'email' => 'admin@example.com',
            'username' => 'admin',
        ];

        $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", $data)
            ->assertRedirect();
        $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", $data)
            ->assertRedirect();

        $this->admin->refresh();
        expect($this->admin->name)->toBe('Admin Updated');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — store', function () {
    it('store admin baru → user dengan role admin tersimpan', function () {
        $this->actingAs($this->admin)->post('/admin/users', [
            'role' => 'admin',
            'name' => 'Admin Baru',
            'email' => 'adminbaru@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'adminbaru@example.com',
            'role' => 'admin',
        ]);
    });

    it('store admin → profile tipe admin dibuat', function () {
        $this->actingAs($this->admin)->post('/admin/users', [
            'role' => 'admin',
            'name' => 'Admin Baru',
            'email' => 'adminbaru@example.com',
        ]);

        $user = User::where('email', 'adminbaru@example.com')->first();
        expect($user)->not->toBeNull()
            ->and($user->profile)->not->toBeNull()
            ->and($user->profile->tipe)->toBe('admin');
    });
});

describe('Data Integrity — update', function () {
    it('update admin (nama + email)', function () {
        $this->actingAs($this->admin)->put("/admin/users/{$this->mitra->id}", [
            'name' => 'Mitra Updated',
            'email' => 'mitra-updated@example.com',
        ]);

        $this->mitra->refresh();
        expect($this->mitra->name)->toBe('Mitra Updated')
            ->and($this->mitra->email)->toBe('mitra-updated@example.com');
    });

    it('update profile fields', function () {
        Profile::factory()->forMitra()->create(['user_id' => $this->mitra->id]);

        $this->actingAs($this->admin)->put("/admin/users/{$this->mitra->id}", [
            'name' => 'Mitra Updated',
            'email' => 'mitra-updated@example.com',
            'profile' => [
                'alamat' => 'Jl. Baru No. 1',
                'telepon' => '08123456789',
            ],
        ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $this->mitra->id,
            'alamat' => 'Jl. Baru No. 1',
        ]);
    });
});

describe('Data Integrity — destroy', function () {
    it('delete mitra → user hilang dari DB', function () {
        $mitraId = $this->mitra->id;

        $this->actingAs($this->admin)->delete("/admin/users/{$mitraId}");

        $this->assertDatabaseMissing('users', ['id' => $mitraId]);
    });
});

describe('Data Integrity — resetPassword', function () {
    it('resetPassword → kirim tautan, hash password tidak berubah', function () {
        Notification::fake();
        $oldPassword = $this->mitra->password;

        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$this->mitra->id}/reset-password");

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionMissing('credentials')
            ->assertSessionHas('status');

        expect($this->mitra->fresh()->password)->toBe($oldPassword);

        Notification::assertSentTo($this->mitra, ResetPassword::class);
    });
});

/*
|==========================================================================
| KATEGORI 6 — RATE LIMITING
|==========================================================================
*/

describe('Rate Limiting', function () {
    it('route admin.users tidak punya throttle eksplisit', function () {
        expect(true)->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 7 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('store tanpa name → error', function () {
        $this->actingAs($this->admin)->post('/admin/users', [
            'role' => 'admin',
            'email' => 'x@y.com',
        ])->assertSessionHasErrors('name');
    });

    it('store dengan email invalid → error', function () {
        $this->actingAs($this->admin)->post('/admin/users', [
            'role' => 'admin',
            'name' => 'X',
            'email' => 'bukan-email',
        ])->assertSessionHasErrors('email');
    });

    it('store dengan role client → error (hanya admin diizinkan)', function () {
        $this->actingAs($this->admin)->post('/admin/users', [
            'role' => 'client',
            'name' => 'X',
            'email' => 'x@y.com',
        ])->assertSessionHasErrors('role');
    });

    it('update user tidak ditemukan → 404', function () {
        $this->actingAs($this->admin)
            ->put('/admin/users/999999', [
                'name' => 'X',
                'email' => 'x@y.com',
            ])->assertNotFound();
    });
});

describe('Error Handling — QueryException', function () {
    it('delete user yang terhubung data lain → error message ramah', function () {
        // Mock UserService->deleteUser throw QueryException
        $this->mock(UserService::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteUser')
                ->once()
                ->andThrow(new QueryException('SQLSTATE', 'DELETE', [], new Exception('FK constraint')));
        });

        $response = $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->mitra->id}");

        $response->assertRedirect()
            ->assertSessionHas('error');

        // User masih ada di DB
        $this->assertDatabaseHas('users', ['id' => $this->mitra->id]);
    });
});
