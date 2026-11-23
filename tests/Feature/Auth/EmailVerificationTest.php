<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP
|==========================================================================
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    RateLimiter::clear('verification-send');
});

/**
 * Buat signed URL verifikasi untuk user.
 */
function verificationUrlFor(User $user, ?DateTimeInterface $expiresAt = null): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        $expiresAt ?? now()->addMinutes(60),
        [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ],
    );
}

/*
|==========================================================================
| TC-01 — REGISTRASI
|==========================================================================
*/

describe('TC-01 — Registrasi manual', function () {
    it('client baru dibuat unverified dan menerima email verifikasi', function () {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Budi Santoso',
            'username' => 'budisantoso',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'budi@example.com')->firstOrFail();

        expect($user->email_verified_at)->toBeNull()
            ->and($user->hasVerifiedEmail())->toBeFalse();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    });

    it('mitra baru dibuat unverified dan tidak langsung ke onboarding', function () {
        Notification::fake();

        $this->post('/register/mitra', [
            'name' => 'Mitra Baru',
            'username' => 'mitrabaru',
            'email' => 'mitra@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'mitra@example.com')->firstOrFail();

        expect($user->hasVerifiedEmail())->toBeFalse();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    });

    it('halaman /verify-email menampilkan email user', function () {
        $user = User::factory()->unverified()->create([
            'email' => 'budi@example.com',
        ]);

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/VerifyEmail')
                ->where('email', 'budi@example.com'));
    });

    it('user terverifikasi yang membuka /verify-email diarahkan ke dashboard', function () {
        $user = User::factory()->client()->create();

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertRedirect(route('client.dashboard'));
    });
});

/*
|==========================================================================
| TC-02 — ACCESS CONTROL MATRIX
|==========================================================================
*/

describe('TC-02 — Blokir akses user belum terverifikasi', function () {
    it('client tidak bisa membuka dashboard', function () {
        $user = User::factory()->unverified()->client()->create();

        $this->actingAs($user)
            ->get('/client/dashboard')
            ->assertRedirect(route('verification.notice'));
    });

    it('client tidak bisa membuka halaman booking', function () {
        $user = User::factory()->unverified()->client()->create();

        $this->actingAs($user)
            ->get('/client/bookings')
            ->assertRedirect(route('verification.notice'));
    });

    it('mitra tidak bisa membuka onboarding', function () {
        $user = User::factory()->unverified()->mitra()->create();

        $this->actingAs($user)
            ->get('/mitra/onboarding')
            ->assertRedirect(route('verification.notice'));
    });

    it('mitra tidak bisa membuka dashboard', function () {
        $user = User::factory()->unverified()->mitra()->create();

        $this->actingAs($user)
            ->get('/mitra/dashboard')
            ->assertRedirect(route('verification.notice'));
    });

    it('client tetap boleh membuka profil dan katalog', function () {
        $user = User::factory()->unverified()->client()->create();

        $this->actingAs($user)->get('/client/profile')->assertOk();
        $this->actingAs($user)->get('/client/catalog')->assertOk();
    });
});

/*
|==========================================================================
| TC-03 — VERIFIKASI VALID
|==========================================================================
*/

describe('TC-03 — Tautan verifikasi valid', function () {
    it('mengisi email_verified_at dan redirect ke dashboard', function () {
        $user = User::factory()->unverified()->client()->create();

        $this->actingAs($user)
            ->get(verificationUrlFor($user))
            ->assertRedirect(route('client.dashboard'))
            ->assertSessionHas('status');

        $user->refresh();

        expect($user->hasVerifiedEmail())->toBeTrue();
    });

    it('mitra terverifikasi diarahkan ke dashboard mitra', function () {
        $user = User::factory()->unverified()->mitra()->create();

        $this->actingAs($user)
            ->get(verificationUrlFor($user))
            ->assertRedirect(route('mitra.dashboard'));

        expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    });
});

/*
|==========================================================================
| TC-04 & TC-05 — SIGNATURE / EXPIRY
|==========================================================================
*/

describe('TC-04/TC-05 — Tautan tidak valid', function () {
    it('signature dimodifikasi → 403 dan email tetap unverified', function () {
        $user = User::factory()->unverified()->client()->create();

        $url = verificationUrlFor($user);
        $tampered = preg_replace('/signature=[^&]+/', 'signature=invalid', $url);

        expect($tampered)->not->toBeNull();

        $this->actingAs($user)
            ->get((string) $tampered)
            ->assertForbidden();

        expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('tautan kedaluwarsa → 403 dan email tetap unverified', function () {
        $user = User::factory()->unverified()->client()->create();

        $this->actingAs($user)
            ->get(verificationUrlFor($user, now()->subMinute()))
            ->assertForbidden();

        expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
    });
});

/*
|==========================================================================
| TC-06 — RATE LIMITING
|==========================================================================
*/

describe('TC-06 — Kirim ulang maksimal 6/menit', function () {
    it('permintaan ke-7 ditolak 429 dan menyertakan Retry-After', function () {
        Notification::fake();

        $user = User::factory()->unverified()->client()->create();

        for ($i = 1; $i <= 6; $i++) {
            $this->actingAs($user)
                ->post(route('verification.send'))
                ->assertRedirect();
        }

        $response = $this->actingAs($user)
            ->post(route('verification.send'));

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    });

    it('user terverifikasi tidak memicu pengiriman ulang', function () {
        Notification::fake();

        $user = User::factory()->client()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('client.dashboard'));

        Notification::assertNothingSent();
    });
});

/*
|==========================================================================
| TC-07 — GOOGLE SSO
|==========================================================================
*/

describe('TC-07 — Google SSO otomatis terverifikasi', function () {
    it('user lama via email ditandai terverifikasi saat login Google', function () {
        $user = User::factory()->unverified()->client()->create([
            'email' => 'budi@example.com',
            'google_id' => null,
        ]);

        $googleUser = makeSocialiteUser(id: 'google-999', email: 'budi@example.com');

        $this->mock(Provider::class, function ($mock) use ($googleUser): void {
            $mock->shouldReceive('stateless')->andReturnSelf();
            $mock->shouldReceive('user')->andReturn($googleUser);
        });

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn(app(Provider::class));

        $this->get('/auth/google/callback')
            ->assertRedirect(route('client.dashboard'));

        expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    });
});

/*
|==========================================================================
| IDEMPOTENCY, CONSISTENCY & RECOVERY
|==========================================================================
*/

describe('Idempotency & consistency', function () {
    it('verifikasi dua kali tidak mengubah email_verified_at', function () {
        $user = User::factory()->unverified()->client()->create();
        $url = verificationUrlFor($user);

        $this->actingAs($user)->get($url)->assertRedirect(route('client.dashboard'));
        $verifiedAt = $user->refresh()->email_verified_at?->toIso8601String();

        expect($verifiedAt)->not->toBeNull();

        $this->actingAs($user)->get($url)->assertRedirect(route('client.dashboard'));

        expect($user->refresh()->email_verified_at?->toIso8601String())
            ->toBe($verifiedAt);
    });

    it('dua permintaan kirim ulang berurutan cepat mengirim dua email, bukan duplikat status', function () {
        Notification::fake();

        $user = User::factory()->unverified()->client()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();
        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();

        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 2);
        expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('kegagalan SMTP tidak membuat registrasi 500 dan user tetap unverified', function () {
        Notification::shouldReceive('send')
            ->andThrow(new RuntimeException('SMTP sedang gangguan'));

        $response = $this->post('/register', [
            'name' => 'Gagal Kirim',
            'username' => 'gagalkirim',
            'email' => 'gagal@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error');

        $user = User::where('email', 'gagal@example.com')->firstOrFail();

        expect($user->hasVerifiedEmail())->toBeFalse();
    });
});

/*
|==========================================================================
| EMAIL CONTENT
|==========================================================================
*/

describe('Konten email verifikasi', function () {
    it('memakai subjek Indonesia, CTA, dan TTL 60 menit', function () {
        Notification::fake();

        $user = User::factory()->unverified()->client()->create();

        $this->actingAs($user)->post(route('verification.send'));

        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return str_contains((string) $mail->subject, 'Verifikasi Alamat Email Anda')
                && $mail->markdown === 'mail.verify-email'
                && isset($mail->viewData['url'])
                && isset($mail->viewData['expiresIn'])
                && $mail->viewData['expiresIn'] === 60;
        });
    });
});
