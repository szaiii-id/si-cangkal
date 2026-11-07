<?php

namespace App\Services\Notification;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * NotificationService — Pengelola Notifikasi In-App (Database).
 *
 * Fokus HANYA pada penyimpanan dan pembacaan notifikasi dari tabel
 * `notifications` (schema custom `AppNotification`).
 *
 * TIDAK bertanggung jawab untuk push notification (FCM) —
 * itu tugas `FirebaseNotificationService` dan `NotificationDispatcher`.
 */
final class NotificationService
{
    /**
     * Simpan notifikasi in-app baru.
     *
     * @param  array<string, mixed>  $data  Metadata tambahan (mis. booking_id).
     */
    public function create(
        User $user,
        string $tipe,
        string $judul,
        string $pesan,
        array $data = [],
    ): AppNotification {
        return AppNotification::create([
            'user_id' => $user->id,
            'tipe' => $tipe,
            'judul' => $judul,
            'pesan' => $pesan,
            'data' => $data,
        ]);
    }

    /**
     * Paginate notifikasi milik user.
     *
     * Ordering: created_at DESC, id DESC sebagai tie-breaker.
     * - created_at bisa kembar (presisi detik di beberapa DB).
     * - id adalah UUID v7 (time-ordered) → tie-breaker yang stabil & unik.
     * - Tanpa tie-breaker, pagination bisa duplikat/skip antar halaman.
     *
     * @return LengthAwarePaginator<int, AppNotification>
     */
    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Hitung notifikasi yang belum dibaca.
     */
    public function unreadCount(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markAsRead(AppNotification $notif): void
    {
        if ($notif->isRead()) {
            return;
        }

        $notif->update(['read_at' => now()]);
    }

    /**
     * Tandai SEMUA notifikasi user sebagai sudah dibaca.
     */
    public function markAllAsRead(User $user): void
    {
        AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}