<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Services\Notification\Contracts\FirebaseNotificationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SendPushNotificationJob — push FCM di background
|==========================================================================
|
| `FirebaseNotificationServiceInterface` sudah di-mock global oleh
| `Tests\TestCase`; di sini kita override bila perlu.
|
*/

describe('handle', function () {
    it('user dengan fcm_token → sendToDevice dipanggil dengan data + tipe', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => 'token-abc',
            'must_change_password' => false,
        ]);

        $this->mock(FirebaseNotificationServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('sendToDevice')
                ->once()
                ->with(
                    deviceToken: 'token-abc',
                    title: 'Perjalanan Dimulai',
                    body: 'Trip sudah dimulai.',
                    data: Mockery::on(fn (array $data): bool => $data['booking_id'] === '7'
                        && $data['tipe'] === 'trip_started'
                        && $data['url'] === '/client/bookings/7'),
                )
                ->andReturnTrue();
        });

        (new SendPushNotificationJob(
            userId: $user->id,
            tipe: 'trip_started',
            judul: 'Perjalanan Dimulai',
            pesan: 'Trip sudah dimulai.',
            data: ['booking_id' => '7', 'url' => '/client/bookings/7'],
        ))->handle(app(FirebaseNotificationServiceInterface::class));
    });

    it('user tanpa fcm_token → tidak memanggil FCM', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => null,
            'must_change_password' => false,
        ]);

        $this->mock(FirebaseNotificationServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('sendToDevice')->never();
        });

        (new SendPushNotificationJob(
            userId: $user->id,
            tipe: 'info',
            judul: 'Info',
            pesan: 'Pesan',
        ))->handle(app(FirebaseNotificationServiceInterface::class));
    });

    it('user tidak ditemukan → dilewati tanpa error', function () {
        $this->mock(FirebaseNotificationServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('sendToDevice')->never();
        });

        (new SendPushNotificationJob(
            userId: 999_999,
            tipe: 'info',
            judul: 'Info',
            pesan: 'Pesan',
        ))->handle(app(FirebaseNotificationServiceInterface::class));

        expect(true)->toBeTrue();
    });

    it('FCM throw → exception dilempar ulang agar job retry', function () {
        $user = User::factory()->client()->create([
            'fcm_token' => 'token-abc',
            'must_change_password' => false,
        ]);

        $this->mock(FirebaseNotificationServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('sendToDevice')
                ->once()
                ->andThrow(new RuntimeException('FCM down'));
        });

        expect(fn () => (new SendPushNotificationJob(
            userId: $user->id,
            tipe: 'info',
            judul: 'Info',
            pesan: 'Pesan',
        ))->handle(app(FirebaseNotificationServiceInterface::class)))
            ->toThrow(RuntimeException::class, 'FCM down');
    });
});
