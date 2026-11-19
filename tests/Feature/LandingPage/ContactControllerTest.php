<?php

use App\Models\User;
use App\Services\LandingPage\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
*/

beforeEach(function () {
    config([
        'mail.contact_recipient' => 'admin@sicangkal.test',
        'auth-throttle.contact.test_override' => null,
        'auth-throttle.contact.max_attempts' => 3,
    ]);

    RateLimiter::clear('127.0.0.1');
});

afterEach(function () {
    RateLimiter::clear('127.0.0.1');
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT
|==========================================================================
*/

describe('Contract — store', function () {
    it('POST /contact valid → redirect back + session status', function () {
        $this->mock(ContactService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once();
        });

        $response = $this->from('/')->post('/contact', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan minimal 10 karakter',
        ]);

        $response->assertRedirect('/')
            ->assertSessionHas('status', 'Pesan Anda berhasil terkirim. Tim kami akan segera merespons.')
            ->assertSessionHasNoErrors();
    });

    it('status message sesuai (tidak berubah format)', function () {
        $this->mock(ContactService::class, fn (MockInterface $m) => $m->shouldReceive('send')->once());

        $response = $this->from('/')->post('/contact', [
            'nama' => 'X',
            'email' => 'x@y.com',
            'pesan' => 'Pesan valid 10+',
        ]);

        $response->assertSessionHas('status', function ($status) {
            return is_string($status)
                && str_contains($status, 'berhasil terkirim');
        });
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY
|==========================================================================
*/

describe('Security — guest access', function () {
    it('guest bisa kirim kontak tanpa auth', function () {
        $this->mock(ContactService::class, fn (MockInterface $m) => $m->shouldReceive('send')->once());

        $this->from('/')->post('/contact', [
            'nama' => 'Guest',
            'email' => 'guest@example.com',
            'pesan' => 'Pesan dari guest',
        ])->assertSessionHas('status');
    });

    it('user yang sudah login bisa kirim kontak', function () {
        $user = User::factory()->client()->create(['must_change_password' => false]);

        $this->mock(ContactService::class, fn (MockInterface $m) => $m->shouldReceive('send')->once());

        $this->actingAs($user)
            ->from('/')
            ->post('/contact', [
                'nama' => 'Budi',
                'email' => 'budi@example.com',
                'pesan' => 'Pesan valid dari user login',
            ])->assertSessionHas('status');
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY (2 request paralel, tanpa dedup)
|==========================================================================
*/

describe('Concurrency', function () {
    it('2x submit → service dipanggil 2x (tidak ada dedup)', function () {
        $this->mock(ContactService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->twice();
        });

        $data = [
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan valid 10+ karakter',
        ];

        $this->from('/')->post('/contact', $data);
        $this->from('/')->post('/contact', $data);

        // Assertion via mock `send()->twice()` sudah terpenuhi
        expect(true)->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('submit 2x dengan data sama → 2 email (bukan idempotent)', function () {
        // Form kontak TIDAK idempotent — setiap submit kirim email.
        // Test ini dokumentasi behavior as-is.
        $this->mock(ContactService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->twice();
        });

        $data = [
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan sama 10+',
        ];

        $this->from('/')->post('/contact', $data);
        $this->from('/')->post('/contact', $data);

        expect(true)->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity', function () {
    it('service dipanggil dengan data valid (nama, email, pesan)', function () {
        $this->mock(ContactService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')
                ->once()
                ->with(Mockery::on(fn ($data) => $data['nama'] === 'Budi Santoso'
                    && $data['email'] === 'budi@example.com'
                    && $data['pesan'] === 'Pesan minimal 10 karakter'));
        });

        $this->from('/')->post('/contact', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan minimal 10 karakter',
        ]);
    });

    it('data valid dengan unicode / karakter khusus', function () {
        $this->mock(ContactService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')
                ->once()
                ->with(Mockery::on(fn ($d) => $d['nama'] === 'Bûdi Sántosø'
                    && str_contains($d['pesan'], '@#$%^&*()')));
        });

        $this->from('/')->post('/contact', [
            'nama' => 'Bûdi Sántosø',
            'email' => 'budi+tag@example.co.id',
            'pesan' => 'Pesan dengan karakter khusus: @#$%^&*()',
        ]);
    });
});

/*
|==========================================================================
| KATEGORI 6 — RATE LIMITING
|==========================================================================
*/

describe('Rate Limiting', function () {
    it('throttle blokir setelah 3x request (batas config)', function () {
        config([
            'auth-throttle.contact.test_override' => null,
            'auth-throttle.contact.max_attempts' => 3,
        ]);

        RateLimiter::clear('127.0.0.1');

        $this->mock(ContactService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->times(3);
        });

        $data = [
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan valid 10+ karakter',
        ];

        for ($i = 0; $i < 3; $i++) {
            $this->from('/')->post('/contact', $data);
        }

        $response = $this->from('/')->post('/contact', $data);

        $response->assertStatus(429);
    });

    it('request ke-2 masih boleh (boundary bawah throttle)', function () {
        config([
            'auth-throttle.contact.test_override' => null,
            'auth-throttle.contact.max_attempts' => 3,
        ]);

        $this->mock(ContactService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->twice();
        });

        $data = [
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan valid 10+ karakter',
        ];

        $this->from('/')->post('/contact', $data)->assertRedirect();
        $this->from('/')->post('/contact', $data)->assertRedirect();
    });
});

/*
|==========================================================================
| KATEGORI 7 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('nama kosong → error', function () {
        $this->mock(ContactService::class, fn (MockInterface $m) => $m->shouldReceive('send')->never());

        $this->from('/')->post('/contact', [
            'nama' => '',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan valid 10+',
        ])->assertSessionHasErrors('nama');
    });

    it('email kosong → error', function () {
        $this->mock(ContactService::class, fn (MockInterface $m) => $m->shouldReceive('send')->never());

        $this->from('/')->post('/contact', [
            'nama' => 'Budi',
            'email' => '',
            'pesan' => 'Pesan valid 10+',
        ])->assertSessionHasErrors('email');
    });

    it('email format invalid → error', function () {
        $this->mock(ContactService::class, fn (MockInterface $m) => $m->shouldReceive('send')->never());

        $this->from('/')->post('/contact', [
            'nama' => 'Budi',
            'email' => 'bukan-email',
            'pesan' => 'Pesan valid 10+',
        ])->assertSessionHasErrors('email');
    });

    it('pesan < 10 karakter → error', function () {
        $this->mock(ContactService::class, fn (MockInterface $m) => $m->shouldReceive('send')->never());

        $this->from('/')->post('/contact', [
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'pesan' => 'Halo',
        ])->assertSessionHasErrors('pesan');
    });

    it('pesan > 5000 karakter → error', function () {
        $this->mock(ContactService::class, fn (MockInterface $m) => $m->shouldReceive('send')->never());

        $this->from('/')->post('/contact', [
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'pesan' => str_repeat('a', 5001),
        ])->assertSessionHasErrors('pesan');
    });

    it('nama > 255 karakter → error', function () {
        $this->mock(ContactService::class, fn (MockInterface $m) => $m->shouldReceive('send')->never());

        $this->from('/')->post('/contact', [
            'nama' => str_repeat('a', 256),
            'email' => 'budi@example.com',
            'pesan' => 'Pesan valid 10+',
        ])->assertSessionHasErrors('nama');
    });
});

describe('Error Handling — service failure', function () {
    it('ContactService throw → error ramah + Log::error + withInput', function () {
        $this->mock(ContactService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andThrow(new Exception('Brevo API down'));
        });

        Log::spy();

        $response = $this->from('/')->post('/contact', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan valid 10+ karakter',
        ]);

        $response->assertRedirect('/')
            ->assertSessionHasErrors('pesan')
            ->assertSessionMissing('status')
            ->assertSessionHasInput('nama', 'Budi Santoso')
            ->assertSessionHasInput('email', 'budi@example.com');

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Contact form failed', Mockery::on(fn ($ctx) => $ctx['email'] === 'budi@example.com'
                && $ctx['error'] === 'Brevo API down'
            ));
    });

    it('service throw tidak set status sukses', function () {
        $this->mock(ContactService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andThrow(new Exception('SMTP timeout'));
        });

        Log::spy();

        $response = $this->from('/')->post('/contact', [
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan valid 10+ karakter',
        ]);

        $response->assertSessionMissing('status');
    });

    it('guest input (nama, email) tetap ada saat error (withInput)', function () {
        $this->mock(ContactService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')
                ->andThrow(new Exception('Fail'));
        });

        Log::shouldReceive('error')->once();

        $response = $this->from('/')->post('/contact', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan valid 10+ karakter',
        ]);

        $response->assertSessionHasInput('nama', 'Budi Santoso');
        $response->assertSessionHasInput('email', 'budi@example.com');
    });
});
