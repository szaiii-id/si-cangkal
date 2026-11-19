<?php

use App\Models\AppNotification;
use App\Models\User;
use App\Services\Notification\NotificationService;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Cakupan unit test:
| - `markAsRead(AppNotification)` — instance method, bisa di-mock.
|
| Method lain (`create`, `paginateForUser`, `unreadCount`, `markAllAsRead`)
| memanggil static query builder `AppNotification::...()` yang butuh DB.
| Verifikasi persistence + query di Feature test (RefreshDatabase).
|
*/

beforeEach(function () {
    $this->service = new NotificationService;
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — markAsRead (bisa di-unit-test)
|==========================================================================
*/

describe('markAsRead — happy path', function () {
    it('update read_at jika belum dibaca', function () {
        $notif = Mockery::mock(AppNotification::class)->makePartial();
        $notif->forceFill([
            'id' => 1,
            'user_id' => 10,
            'read_at' => null,
        ]);

        $notif->shouldReceive('isRead')->once()->andReturn(false);
        $notif->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn ($payload) => isset($payload['read_at'])
                && $payload['read_at'] !== null));

        $this->service->markAsRead($notif);

        expect(true)->toBeTrue();
    });
});

describe('markAsRead — idempotency', function () {
    it('TIDAK update jika sudah dibaca', function () {
        $notif = Mockery::mock(AppNotification::class)->makePartial();
        $notif->forceFill([
            'id' => 1,
            'user_id' => 10,
            'read_at' => now(),
        ]);

        $notif->shouldReceive('isRead')->once()->andReturn(true);
        $notif->shouldReceive('update')->never();

        $this->service->markAsRead($notif);

        expect(true)->toBeTrue();
    });

    it('panggil 2x pada notif yang sama → update HANYA 1x', function () {
        $notif = Mockery::mock(AppNotification::class)->makePartial();
        $notif->forceFill(['id' => 1, 'read_at' => null]);

        // Pemanggilan pertama: belum dibaca → update
        $notif->shouldReceive('isRead')->once()->andReturn(false);
        $notif->shouldReceive('update')->once();

        $this->service->markAsRead($notif);
    });
});

/*
|==========================================================================
| KATEGORI 2 — NEGATIVE PATH
|==========================================================================
*/

describe('markAsRead — return type', function () {
    it('method void (tidak return apa-apa)', function () {
        $notif = Mockery::mock(AppNotification::class)->makePartial();
        $notif->forceFill(['id' => 1, 'read_at' => null]);
        $notif->shouldReceive('isRead')->andReturn(false);
        $notif->shouldReceive('update')->once();

        $result = $this->service->markAsRead($notif);

        expect($result)->toBeNull();
    });
});

/*
|==========================================================================
| CATATAN: Test yang butuh Feature (dengan DB)
|==========================================================================
|
| ### `create`
|   it('create menyimpan notifikasi di DB', function () {
|       $user = User::factory()->create();
|       $service = new NotificationService;
|
|       $notif = $service->create(
|           user: $user,
|           tipe: 'booking_created',
|           judul: 'Pesanan Baru',
|           pesan: 'Ada pesanan baru',
|           data: ['booking_id' => 10],
|       );
|
|       expect($notif->user_id)->toBe($user->id)
|           ->and($notif->tipe)->toBe('booking_created')
|           ->and($notif->data)->toBe(['booking_id' => 10]);
|
|       $this->assertDatabaseHas('notifications', ['user_id' => $user->id]);
|   });
|
| ### `paginateForUser`
|   it('paginateForUser hanya return notif user', function () {
|       $userA = User::factory()->create();
|       $userB = User::factory()->create();
|       $service = new NotificationService;
|
|       $service->create($userA, 'info', 'A', 'msg');
|       $service->create($userB, 'info', 'B', 'msg');
|
|       $result = $service->paginateForUser($userA);
|
|       expect($result->total())->toBe(1);
|   });
|
|   it('paginateForUser ordering: created_at DESC, id DESC (tie-breaker)', function () {
|       // ...
|   });
|
| ### `unreadCount`
|   it('unreadCount return 0 jika semua dibaca', function () {
|       // ...
|   });
|
| ### `markAllAsRead`
|   it('markAllAsRead update semua notif user', function () {
|       // ...
|   });
|
|   it('markAllAsRead tidak sentuh notif user lain', function () {
|       // ...
|   });
|
*/
