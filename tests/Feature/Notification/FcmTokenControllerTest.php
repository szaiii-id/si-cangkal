<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — update', function () {
    it('POST /notifications/fcm valid → redirect back + session status', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => null,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', [
                'token' => 'device-token-abc',
            ]);

        $response->assertRedirect('/client/dashboard')
            ->assertSessionHas('status', 'Notifikasi berhasil diaktifkan!')
            ->assertSessionHasNoErrors();
    });

    it('status message sesuai format', function () {
        $user = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => 'token-x'])
            ->assertSessionHas('status', function ($status) {
                return is_string($status) && str_contains($status, 'berhasil diaktifkan');
            });
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security', function () {
    it('guest di-redirect ke login', function () {
        $this->from('/client/dashboard')->post('/notifications/fcm', [
            'token' => 'token-x',
        ])->assertRedirect(route('login'));
    });

    it('user must_change_password=true bisa akses (tidak di-block)', function () {
        $user = User::factory()->client()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->from('/change-password')
            ->post('/notifications/fcm', ['token' => 'token-y'])
            ->assertRedirect();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency', function () {
    it('update 2x token sama → hanya 1 update (idempotent)', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => 'token-same',
            'must_change_password' => false,
        ]);

        // Token sama → tidak ada update di DB
        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => 'token-same'])
            ->assertRedirect();

        // Token sama lagi — masih tidak update
        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => 'token-same'])
            ->assertRedirect();

        $user->refresh();
        expect($user->fcm_token)->toBe('token-same');
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('token SAMA dengan existing → tidak ubah updated_at (skip query)', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => 'token-same',
            'must_change_password' => false,
        ]);

        $updatedAtBefore = $user->updated_at;

        // Delay 1 detik untuk deteksi update
        Carbon::setTestNow(now()->addSecond());

        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => 'token-same'])
            ->assertRedirect();

        Carbon::setTestNow();

        $user->refresh();
        // updated_at TIDAK berubah karena skip update
        expect($user->updated_at->equalTo($updatedAtBefore))->toBeTrue()
            ->and($user->fcm_token)->toBe('token-same');
    });

    it('token BEDA dari existing → update DB', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => 'old-token',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => 'new-token'])
            ->assertRedirect();

        $user->refresh();
        expect($user->fcm_token)->toBe('new-token');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity', function () {
    it('token baru tersimpan di DB', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => null,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => 'token-baruuu'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'fcm_token' => 'token-baruuu',
        ]);
    });

    it('Log::info dipanggil saat token berubah', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => 'old-token',
            'must_change_password' => false,
        ]);

        Log::spy();

        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => 'new-token-abc'])
            ->assertRedirect();

        Log::shouldHaveReceived('info')
            ->once()
            ->with('FCM token diperbarui', Mockery::on(
                fn ($ctx) => $ctx['user_id'] === $user->id
                    && str_starts_with($ctx['token_prefix'], 'new-token-')
            ));
    });

    it('Log::info TIDAK dipanggil jika token sama', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => 'same-token',
            'must_change_password' => false,
        ]);

        Log::spy();

        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => 'same-token']);

        Log::shouldNotHaveReceived('info');
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('token kosong → error', function () {
        $user = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => ''])
            ->assertSessionHasErrors('token');
    });

    it('token tidak dikirim → error', function () {
        $user = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', [])
            ->assertSessionHasErrors('token');
    });

    it('token > 255 karakter → error', function () {
        $user = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => str_repeat('a', 256)])
            ->assertSessionHasErrors('token');
    });

    it('token tepat 255 karakter (boundary) → sukses', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => null,
            'must_change_password' => false,
        ]);

        $longToken = str_repeat('a', 255);

        $this->actingAs($user)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => $longToken])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        expect($user->fcm_token)->toBe($longToken);
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases', function () {
    it('update error → Log::error + error message generic', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => null,
            'must_change_password' => false,
        ]);

        // Mock user->update throw
        $userMock = Mockery::mock($user)->makePartial();
        $userMock->shouldReceive('update')->andThrow(new RuntimeException('DB error'));
        $userMock->shouldReceive('getAttribute')->andReturnUsing(fn ($key) => $user->getAttribute($key));

        Log::spy();

        $response = $this->actingAs($userMock)
            ->from('/client/dashboard')
            ->post('/notifications/fcm', ['token' => 'token-error']);

        $response->assertRedirect('/client/dashboard')
            ->assertSessionHasErrors('error');

        Log::shouldHaveReceived('error')->once();
    });
});
