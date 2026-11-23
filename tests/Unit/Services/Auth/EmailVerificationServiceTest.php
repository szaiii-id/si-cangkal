<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/*
|==========================================================================
| UNIT TEST — EmailVerificationService (tanpa database)
|==========================================================================
*/

beforeEach(function () {
    $this->users = Mockery::mock(UserRepositoryInterface::class);
    $this->service = new EmailVerificationService($this->users);
});

afterEach(function () {
    Mockery::close();
});

describe('send() — kirim ulang notifikasi', function () {
    it('mengirim notifikasi untuk user yang belum terverifikasi', function () {
        Notification::fake();

        $user = makeUser([
            'id' => 1,
            'email' => 'budi@example.com',
            'email_verified_at' => null,
        ]);

        expect($this->service->send($user))->toBeTrue();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    });

    it('tidak mengirim notifikasi untuk user yang sudah terverifikasi (idempotent)', function () {
        Notification::fake();

        $user = makeUser(['id' => 2, 'email' => 'sudah@example.com']);

        expect($user->hasVerifiedEmail())->toBeTrue()
            ->and($this->service->send($user))->toBeFalse();

        Notification::assertNothingSent();
    });

    it('menangkap exception SMTP dan mengembalikan false tanpa melempar error', function () {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill([
            'id' => 3,
            'email' => 'gagal@example.com',
            'email_verified_at' => null,
        ]);
        $user->shouldReceive('sendEmailVerificationNotification')
            ->once()
            ->andThrow(new RuntimeException('SMTP down'));

        expect($this->service->send($user))->toBeFalse();
    });
});

describe('verify() — validasi hash & penandaan', function () {
    it('menandai terverifikasi via repository dan memicu event Verified', function () {
        Event::fake([Verified::class]);

        $user = makeUser([
            'id' => 4,
            'email' => 'budi@example.com',
            'email_verified_at' => null,
        ]);

        $this->users->shouldReceive('markEmailAsVerified')
            ->once()
            ->with($user)
            ->andReturn($user);

        $this->service->verify($user, sha1($user->getEmailForVerification()));

        Event::assertDispatched(Verified::class);
    });

    it('hash tidak cocok → AuthorizationException dan repository tidak dipanggil', function () {
        Event::fake([Verified::class]);

        $user = makeUser([
            'id' => 5,
            'email' => 'budi@example.com',
            'email_verified_at' => null,
        ]);

        $this->users->shouldNotReceive('markEmailAsVerified');

        expect(fn () => $this->service->verify($user, sha1('email-lain@example.com')))
            ->toThrow(AuthorizationException::class);

        Event::assertNotDispatched(Verified::class);
    });

    it('user sudah terverifikasi → idempotent, tidak memanggil repository', function () {
        Event::fake([Verified::class]);

        $user = makeUser(['id' => 6, 'email' => 'sudah@example.com']);

        $this->users->shouldNotReceive('markEmailAsVerified');

        $this->service->verify($user, sha1($user->getEmailForVerification()));

        Event::assertNotDispatched(Verified::class);
    });
});
