<?php

namespace App\Services\Notification;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * NotificationDispatcher — Gerbang Pusat Notifikasi.
 *
 * Menyatukan dua lapis notifikasi:
 * 1. IN-APP (Database) — Ikon lonceng di aplikasi.
 * 2. PUSH (FCM) — Notifikasi yang muncul di layar HP.
 *
 * ✅ Deep Linking: Dispatcher otomatis menentukan URL tujuan
 * berdasarkan `tipe` notifikasi dan `role` user. `url` disimpan
 * di `data` (DB) DAN dikirim ke FCM, sehingga klik notifikasi
 * in-app maupun push sama-sama redirect ke halaman yang sama.
 */
final class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly FirebaseNotificationService $firebaseNotificationService,
    ) {}

    /**
     * Kirim notifikasi lengkap (In-App + Push).
     */
    public function send(
        User $user,
        string $tipe,
        string $judul,
        string $pesan,
        array $data = []
    ): void {
        // ✅ Hitung URL DULU, sebelum simpan ke DB
        $url = $this->resolveUrl($tipe, $user, $data);

        // Gabungkan `url` ke `data` — agar konsisten di DB & FCM
        $dataWithUrl = array_merge($data, ['url' => $url]);

        // ==========================================
        // 1. LAPIS IN-APP (Database)
        // ==========================================
        $this->notificationService->create(
            user: $user,
            tipe: $tipe,
            judul: $judul,
            pesan: $pesan,
            data: $dataWithUrl, // ← `url` IKUT tersimpan di DB
        );

        // ==========================================
        // 2. LAPIS PUSH (Firebase FCM)
        // ==========================================
        if (empty($user->fcm_token)) {
            return;
        }

        // Payload data untuk FCM harus array of strings
        $fcmData = array_map('strval', $dataWithUrl);
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
     * Tentukan URL tujuan berdasarkan tipe notifikasi & role user.
     */
    private function resolveUrl(string $tipe, User $user, array $data): string
    {
        // Prioritas 1: Pemanggil sudah menentukan URL eksplisit
        if (!empty($data['url'])) {
            return (string) $data['url'];
        }

        $bookingId = $data['booking_id'] ?? null;
        $refundId = $data['refund_id'] ?? null;
        $role = $user->role;

        return match ($tipe) {
            // Booking-related
            'payment_proof_uploaded',
            'booking_confirmed',
            'payment_verified',
            'payment_rejected',
            'booking_created',
            'booking_cancelled',
            'booking_in_progress',
            'booking_completed' => $bookingId
                ? "/{$role}/bookings/{$bookingId}"
                : "/{$role}/bookings",

            // Refund-related
            'refund_requested',
            'refund_succeeded',
            'refund_rejected',
            'refund_approved' => $refundId
                ? "/{$role}/refunds"
                : "/{$role}/refunds",

            // Trip-related
            'trip_started',
            'trip_completed',
            'trip_reminder' => $bookingId
                ? "/{$role}/trips"
                : "/{$role}/trips",

            // Admin-specific
            'mitra_registered' => '/admin/mitras',

            // Fallback
            default => '/notifications',
        };
    }
}