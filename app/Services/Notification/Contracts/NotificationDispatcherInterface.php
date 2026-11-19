<?php

namespace App\Services\Notification\Contracts;

use App\Models\User;

/**
 * Interface untuk NotificationDispatcher.
 *
 * Memungkinkan dependency injection ke interface (SOLID: DIP),
 * sehingga service yang bergantung pada dispatcher bisa di-test
 * tanpa terikat ke final class.
 */
interface NotificationDispatcherInterface
{
    /**
     * Kirim notifikasi lengkap (In-App + Push).
     *
     * @param  array<string, mixed>  $data
     */
    public function send(
        User $user,
        string $tipe,
        string $judul,
        string $pesan,
        array $data = [],
    ): void;
}
