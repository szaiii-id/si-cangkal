<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * Firebase Cloud Messaging (FCM) Service.
 *
 * Tugas UTAMA: Menembakkan notifikasi ke Google FCM.
 * Service ini TIDAK tahu menahu soal database, Inertia, atau bisnis logic.
 * Dia hanya butuh: Token HP user, Judul, Pesan, dan Data opsional.
 */
final class FirebaseNotificationService
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    /**
     * Kirim notifikasi push ke satu perangkat.
     *
     * @param string $deviceToken FCM Token unik perangkat user.
     * @param string $title       Judul notifikasi.
     * @param string $body        Isi pesan notifikasi.
     * @param array<string, mixed> $data Data tambahan (misal: booking_id, url).
     * @return bool True jika sukses, False jika gagal.
     */
    public function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array $data = []
    ): bool {
        if (trim($deviceToken) === '') {
            return false;
        }

        try {
            $notification = Notification::create($title, $body);

            // v8: Gunakan withToken() atau fromArray, bukan withTarget()
            $message = CloudMessage::new()
                ->withToken($deviceToken)
                ->withNotification($notification);

            // Konfigurasi Android: prioritas tinggi + suara
            $androidConfig = AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                ],
            ]);
            
            // Konfigurasi iOS (APNs): prioritas 10 + suara + badge
            $apnsConfig = ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ]);

            $message = $message
                ->withAndroidConfig($androidConfig)
                ->withApnsConfig($apnsConfig);

            if (!empty($data)) {
                // Pastikan semua nilai data adalah string (persyaratan FCM)
                $fcmData = array_map('strval', $data);
                $message = $message->withData($fcmData);
            }

            $this->messaging->send($message);

            return true;
        } catch (\Throwable $e) {
            // Jangan throw exception. Cukup log agar proses bisnis utama tidak terganggu.
            Log::warning('Gagal mengirim Firebase Push Notification', [
                'device_token_prefix' => substr($deviceToken, 0, 10) . '...',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}