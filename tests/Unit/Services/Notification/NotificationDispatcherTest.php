<?php

use App\Models\User;
use App\Services\Notification\Contracts\FirebaseNotificationServiceInterface;
use App\Services\Notification\Contracts\NotificationServiceInterface;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Facades\Log;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Cakupan:
| - `send` — panggil `notificationService->create` (in-app) +
|   `firebaseNotificationService->sendToDevice` (push FCM).
| - `resolveUrl` — private, via reflection (pure function).
|
| Tidak butuh DB. Dua dependency adalah interface (mockable).
|
*/

beforeEach(function () {
    $this->notificationService = Mockery::mock(NotificationServiceInterface::class);
    $this->firebaseService = Mockery::mock(FirebaseNotificationServiceInterface::class);

    $this->service = new NotificationDispatcher(
        $this->notificationService,
        $this->firebaseService,
    );

    Log::shouldReceive('info')->byDefault()->andReturnNull();
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
    Log::shouldReceive('error')->byDefault()->andReturnNull();
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| HELPER — invoke `resolveUrl` via reflection
|==========================================================================
*/

function invokeResolveUrl(NotificationDispatcher $service, string $tipe, User $user, array $data = []): string
{
    $ref = new ReflectionMethod($service, 'resolveUrl');
    $ref->setAccessible(true);

    return $ref->invoke($service, $tipe, $user, $data);
}

/*
|==========================================================================
| KATEGORI 1 — send: IN-APP only (no fcm_token)
|==========================================================================
*/

describe('send — in-app only (tanpa fcm_token)', function () {
    it('user tanpa fcm_token → create notif in-app saja, skip push', function () {
        $user = makeUser(['id' => 10, 'role' => 'client', 'fcm_token' => null]);

        $this->notificationService->shouldReceive('create')
            ->once()
            ->with(
                user: $user,
                tipe: 'booking_created',
                judul: 'Pesanan Baru',
                pesan: 'Pesan',
                data: Mockery::on(fn ($d) => isset($d['url'])
                    && $d['url'] === '/client/bookings/1'),
            );

        // `sendToDevice` TIDAK dipanggil (fcm_token null)
        $this->firebaseService->shouldReceive('sendToDevice')->never();

        $this->service->send(
            user: $user,
            tipe: 'booking_created',
            judul: 'Pesanan Baru',
            pesan: 'Pesan',
            data: ['booking_id' => 1],
        );
    });

    it('user dengan fcm_token "" (empty) → skip push', function () {
        $user = makeUser(['id' => 10, 'role' => 'mitra', 'fcm_token' => '']);

        $this->notificationService->shouldReceive('create')->once();
        $this->firebaseService->shouldReceive('sendToDevice')->never();

        $this->service->send(
            user: $user,
            tipe: 'info',
            judul: 'Info',
            pesan: 'Pesan',
        );
    });
});

/*
|==========================================================================
| KATEGORI 2 — send: IN-APP + PUSH (with fcm_token)
|==========================================================================
*/

describe('send — in-app + push FCM', function () {
    it('user dengan fcm_token → create in-app + sendToDevice', function () {
        $user = makeUser(['id' => 10, 'role' => 'client', 'fcm_token' => 'device-token-abc']);

        $this->notificationService->shouldReceive('create')->once();

        $this->firebaseService->shouldReceive('sendToDevice')
            ->once()
            ->with(
                deviceToken: 'device-token-abc',
                title: 'Pesanan Baru',
                body: 'Pesan',
                data: Mockery::on(fn ($d) => $d['tipe'] === 'booking_created'
                    && $d['url'] === '/client/bookings/1'
                    && $d['booking_id'] === '1'),  // ← stringify
            );

        $this->service->send(
            user: $user,
            tipe: 'booking_created',
            judul: 'Pesanan Baru',
            pesan: 'Pesan',
            data: ['booking_id' => 1],
        );
    });

    it('fcm data: semua value jadi string (array_map strval)', function () {
        $user = makeUser(['id' => 10, 'role' => 'client', 'fcm_token' => 'token']);

        $this->notificationService->shouldReceive('create')->once();

        $this->firebaseService->shouldReceive('sendToDevice')
            ->once()
            ->with(
                deviceToken: 'token',
                title: 'X',
                body: 'Y',
                data: Mockery::on(fn ($d) => $d['booking_id'] === '1'
                    && $d['refund_id'] === '5'
                    && is_string($d['booking_id'])
                    && is_string($d['refund_id'])),
            );

        $this->service->send(
            user: $user,
            tipe: 'booking_created',
            judul: 'X',
            pesan: 'Y',
            data: ['booking_id' => 1, 'refund_id' => 5],
        );
    });
});

/*
|==========================================================================
| KATEGORI 3 — send: FCM error handling
|==========================================================================
*/

describe('send — FCM gagal', function () {
    it('firebase throw → di-catch, in-app tetap sukses', function () {
        $user = makeUser(['id' => 10, 'role' => 'client', 'fcm_token' => 'token']);

        $this->notificationService->shouldReceive('create')->once();

        $this->firebaseService->shouldReceive('sendToDevice')
            ->once()
            ->andThrow(new RuntimeException('FCM down'));

        Log::shouldReceive('warning')
            ->once()
            ->with('NotificationDispatcher: gagal push FCM', Mockery::on(
                fn ($ctx) => $ctx['user_id'] === 10
                    && $ctx['tipe'] === 'booking_created'
                    && $ctx['error'] === 'FCM down'
            ));

        // Tidak throw
        $this->service->send(
            user: $user,
            tipe: 'booking_created',
            judul: 'X',
            pesan: 'Y',
        );

        expect(true)->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 4 — resolveUrl (via reflection)
|==========================================================================
*/

describe('resolveUrl — priority data.url explicit', function () {
    it('data.url explicit → override semua', function () {
        $user = makeUser(['role' => 'client']);

        $result = invokeResolveUrl($this->service, 'booking_created', $user, [
            'url' => '/custom/path',
            'booking_id' => 1,
        ]);

        expect($result)->toBe('/custom/path');
    });
});

describe('resolveUrl — booking tipe', function () {
    it('booking_created + booking_id → /{role}/bookings/{id}', function () {
        $user = makeUser(['role' => 'client']);

        expect(invokeResolveUrl($this->service, 'booking_created', $user, ['booking_id' => 5]))
            ->toBe('/client/bookings/5');
    });

    it('booking_created tanpa booking_id → /{role}/bookings', function () {
        $user = makeUser(['role' => 'mitra']);

        expect(invokeResolveUrl($this->service, 'booking_created', $user))
            ->toBe('/mitra/bookings');
    });

    it('payment_verified + booking_id → /{role}/bookings/{id}', function () {
        $user = makeUser(['role' => 'client']);

        expect(invokeResolveUrl($this->service, 'payment_verified', $user, ['booking_id' => 10]))
            ->toBe('/client/bookings/10');
    });
});

describe('resolveUrl — refund tipe', function () {
    it('refund_succeeded + refund_id → /{role}/refunds', function () {
        $user = makeUser(['role' => 'client']);

        expect(invokeResolveUrl($this->service, 'refund_succeeded', $user, ['refund_id' => 3]))
            ->toBe('/client/refunds');
    });

    it('refund_succeeded tanpa refund_id → /{role}/refunds', function () {
        $user = makeUser(['role' => 'mitra']);

        expect(invokeResolveUrl($this->service, 'refund_succeeded', $user))
            ->toBe('/mitra/refunds');
    });
});

describe('resolveUrl — trip tipe', function () {
    it('trip_started → /{role}/trips', function () {
        $user = makeUser(['role' => 'mitra']);

        expect(invokeResolveUrl($this->service, 'trip_started', $user, ['booking_id' => 5]))
            ->toBe('/mitra/trips');
    });
});

describe('resolveUrl — admin tipe & fallback', function () {
    it('mitra_registered → /admin/mitras', function () {
        $user = makeUser(['role' => 'admin']);

        expect(invokeResolveUrl($this->service, 'mitra_registered', $user))
            ->toBe('/admin/mitras');
    });

    it('tipe tidak dikenal → /notifications', function () {
        $user = makeUser(['role' => 'client']);

        expect(invokeResolveUrl($this->service, 'random_type', $user))
            ->toBe('/notifications');
    });
});
