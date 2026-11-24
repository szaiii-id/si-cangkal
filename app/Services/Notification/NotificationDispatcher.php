<?php

namespace App\Services\Notification;

use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Notification\Contracts\NotificationServiceInterface;

final class NotificationDispatcher implements NotificationDispatcherInterface
{
    public function __construct(
        private readonly NotificationServiceInterface $notificationService,
    ) {}

    /**
     * Buat notifikasi in-app (sinkron) lalu antrekan push FCM.
     *
     * Push sengaja dijadikan job agar request user tidak menunggu I/O
     * Firebase. Transaksi pemanggil juga tidak ikut gagal bila FCM down.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(
        User $user,
        string $tipe,
        string $judul,
        string $pesan,
        array $data = []
    ): void {
        $url = $this->resolveUrl($tipe, $user, $data);
        $dataWithUrl = array_merge($data, ['url' => $url]);

        $this->notificationService->create(
            user: $user,
            tipe: $tipe,
            judul: $judul,
            pesan: $pesan,
            data: $dataWithUrl,
        );

        if (empty($user->fcm_token)) {
            return;
        }

        /** @var array<string, string> $fcmData */
        $fcmData = [];

        foreach ($dataWithUrl as $key => $value) {
            $keyString = (string) $key;

            if ($keyString === '') {
                continue;
            }

            $fcmData[$keyString] = (string) $value;
        }

        SendPushNotificationJob::dispatch(
            userId: $user->id,
            tipe: $tipe,
            judul: $judul,
            pesan: $pesan,
            data: $fcmData,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveUrl(string $tipe, User $user, array $data): string
    {
        if (! empty($data['url'])) {
            return (string) $data['url'];
        }

        $bookingId = $data['booking_id'] ?? null;
        $refundId = $data['refund_id'] ?? null;
        $role = $user->role;

        return match ($tipe) {
            'payment_proof_uploaded',
            'booking_confirmed',
            'payment_verified',
            'payment_rejected',
            'booking_created',
            'booking_cancelled',
            'booking_in_progress',
            'booking_completed',
            'review_baru' => $bookingId
                ? "/{$role}/bookings/{$bookingId}"
                : "/{$role}/bookings",

            'refund_requested',
            'refund_succeeded',
            'refund_rejected',
            'refund_approved' => $refundId
                ? "/{$role}/refunds"
                : "/{$role}/refunds",

            // Trip tidak punya halaman detail untuk semua role. Bila ada
            // booking_id, arahkan ke detail booking yang pasti ada.
            'trip_started',
            'trip_completed',
            'trip_reminder' => $bookingId
                ? "/{$role}/bookings/{$bookingId}"
                : ($role === 'client' ? '/client/bookings' : "/{$role}/trips"),

            'mitra_registered' => '/admin/mitras',

            default => '/notifications',
        };
    }
}
