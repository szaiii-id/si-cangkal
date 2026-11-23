<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Services\Notification\Contracts\FirebaseNotificationServiceInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Notification\Contracts\NotificationServiceInterface;
use Illuminate\Support\Facades\Log;

final class NotificationDispatcher implements NotificationDispatcherInterface
{
    public function __construct(
        private readonly NotificationServiceInterface $notificationService,
        private readonly FirebaseNotificationServiceInterface $firebaseNotificationService,
    ) {}

    /**
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

        $fcmData['tipe'] = $tipe;

        try {
            $this->firebaseNotificationService->sendToDevice(
                deviceToken: $user->fcm_token,
                title: $judul,
                body: $pesan,
                data: $fcmData,
            );
        } catch (\Throwable $e) {
            Log::warning('NotificationDispatcher: gagal push FCM', [
                'user_id' => $user->id,
                'tipe' => $tipe,
                'error' => $e->getMessage(),
            ]);
        }
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

            'trip_started',
            'trip_completed',
            'trip_reminder' => $bookingId
                ? "/{$role}/trips"
                : "/{$role}/trips",

            'mitra_registered' => '/admin/mitras',

            default => '/notifications',
        };
    }
}
