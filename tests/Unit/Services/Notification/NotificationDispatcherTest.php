<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Services\Notification\Contracts\NotificationServiceInterface;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Facades\Queue;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Cakupan:
| - `send` — panggil `notificationService->create` (in-app) sinkron.
| - `send` — antrekan `SendPushNotificationJob` bila user punya fcm_token.
| - `resolveUrl` — private, via reflection (pure function).
|
| Job FCM diuji terpisah di `tests/Feature/Jobs/SendPushNotificationJobTest.php`.
|
*/

beforeEach(function () {
    Queue::fake();

    $this->notificationService = Mockery::mock(NotificationServiceInterface::class);

    $this->service = new NotificationDispatcher($this->notificationService);
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
    it('user tanpa fcm_token → create notif in-app saja, tanpa push job', function () {
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

        $this->service->send(
            user: $user,
            tipe: 'booking_created',
            judul: 'Pesanan Baru',
            pesan: 'Pesan',
            data: ['booking_id' => 1],
        );

        Queue::assertNotPushed(SendPushNotificationJob::class);
    });

    it('user dengan fcm_token "" (empty) → skip push job', function () {
        $user = makeUser(['id' => 10, 'role' => 'mitra', 'fcm_token' => '']);

        $this->notificationService->shouldReceive('create')->once();

        $this->service->send(
            user: $user,
            tipe: 'info',
            judul: 'Info',
            pesan: 'Pesan',
        );

        Queue::assertNotPushed(SendPushNotificationJob::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — send: IN-APP + PUSH job (with fcm_token)
|==========================================================================
*/

describe('send — in-app + push job', function () {
    it('user dengan fcm_token → job dikirim dengan data string + tipe', function () {
        $user = makeUser(['id' => '00000000-0000-4000-8000-000000000010', 'role' => 'client', 'fcm_token' => 'device-token-abc']);

        $this->notificationService->shouldReceive('create')->once();

        $this->service->send(
            user: $user,
            tipe: 'booking_created',
            judul: 'Pesanan Baru',
            pesan: 'Pesan',
            data: ['booking_id' => '00000000-0000-4000-8000-000000000101', 'refund_id' => 5],
        );

        Queue::assertPushed(SendPushNotificationJob::class, function (SendPushNotificationJob $job): bool {
            return $job->userId === '00000000-0000-4000-8000-000000000010'
                && $job->tipe === 'booking_created'
                && $job->judul === 'Pesanan Baru'
                && $job->pesan === 'Pesan'
                && $job->data['booking_id'] === '00000000-0000-4000-8000-000000000101'
                && $job->data['refund_id'] === '5'
                && $job->data['url'] === '/client/bookings/00000000-0000-4000-8000-000000000101'
                && is_string($job->data['booking_id'])
                && is_string($job->data['refund_id']);
        });
    });
});

/*
|==========================================================================
| KATEGORI 3 — resolveUrl (via reflection)
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
    it('trip_started + booking_id → /{role}/bookings/{id}', function () {
        $user = makeUser(['role' => 'mitra']);

        expect(invokeResolveUrl($this->service, 'trip_started', $user, ['booking_id' => 5]))
            ->toBe('/mitra/bookings/5');
    });

    it('trip_started tanpa booking_id → /{role}/trips (mitra)', function () {
        $user = makeUser(['role' => 'mitra']);

        expect(invokeResolveUrl($this->service, 'trip_started', $user))
            ->toBe('/mitra/trips');
    });

    it('trip_started tanpa booking_id → /client/bookings (client)', function () {
        $user = makeUser(['role' => 'client']);

        expect(invokeResolveUrl($this->service, 'trip_started', $user))
            ->toBe('/client/bookings');
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
