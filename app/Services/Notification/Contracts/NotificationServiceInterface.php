<?php

namespace App\Services\Notification\Contracts;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface untuk NotificationService.
 *
 * Memungkinkan dependency injection ke interface (SOLID: DIP),
 * sehingga NotificationDispatcher bisa di-test tanpa terikat ke
 * final class NotificationService.
 */
interface NotificationServiceInterface
{
    /**
     * Simpan notifikasi in-app baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(
        User $user,
        string $tipe,
        string $judul,
        string $pesan,
        array $data = [],
    ): AppNotification;

    /**
     * Paginate notifikasi milik user.
     *
     * @return LengthAwarePaginator<int, AppNotification>
     */
    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator;

    /**
     * Hitung notifikasi yang belum dibaca.
     */
    public function unreadCount(User $user): int;

    /**
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markAsRead(AppNotification $notif): void;

    /**
     * Tandai SEMUA notifikasi user sebagai sudah dibaca.
     */
    public function markAllAsRead(User $user): void;
}
