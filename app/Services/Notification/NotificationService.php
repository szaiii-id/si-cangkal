<?php

namespace App\Services\Notification;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\Notification\Contracts\NotificationServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class NotificationService implements NotificationServiceInterface
{
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

    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(AppNotification $notif): void
    {
        if ($notif->isRead()) {
            return;
        }

        $notif->update(['read_at' => now()]);
    }

    public function markAllAsRead(User $user): void
    {
        AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
