<?php

namespace App\Services\Notification\Contracts;

/**
 * Interface untuk FirebaseNotificationService.
 *
 * Memungkinkan dependency injection ke interface (SOLID: DIP),
 * sehingga NotificationDispatcher bisa di-test tanpa terikat ke
 * implementasi konkret Firebase.
 */
interface FirebaseNotificationServiceInterface
{
    /**
     * Kirim notifikasi ke device tertentu via FCM.
     *
     * @param  string  $deviceToken  FCM token device user.
     * @param  string  $title  Judul notifikasi.
     * @param  string  $body  Body notifikasi.
     * @param  array<string, string>  $data  Payload data (key-value string).
     * @return bool True jika sukses, False jika gagal.
     */
    public function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array $data = [],
    ): bool;
}
