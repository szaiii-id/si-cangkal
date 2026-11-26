<?php

use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

it('createAdmin mengirim tautan set password dan tidak mengembalikan password plaintext', function () {
    $result = app(UserService::class)->createAdmin([
        'name' => 'Admin Baru',
        'email' => 'admin-baru@example.com',
        'username' => 'adminbaru',
    ]);

    expect($result)->toHaveKey('user')
        ->not->toHaveKey('plain_password');

    Notification::assertSentTo($result['user'], ResetPassword::class);
});

it('resetPassword mengirim tautan tanpa mengubah hash password di database', function () {
    $user = User::factory()->mitra()->create([
        'must_change_password' => false,
        'email' => 'mitra-reset@example.com',
    ]);
    $oldHash = $user->password;

    app(UserService::class)->resetPassword($user);

    expect($user->fresh()->password)->toBe($oldHash);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('reset lewat tautan menghapus flag must_change_password', function () {
    $user = User::factory()->mitra()->create([
        'must_change_password' => true,
        'email' => 'mitra-token@example.com',
    ]);

    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'PasswordBaru123!',
        'password_confirmation' => 'PasswordBaru123!',
    ])->assertRedirect(route('login'));

    expect((bool) $user->fresh()->must_change_password)->toBeFalse();
});
