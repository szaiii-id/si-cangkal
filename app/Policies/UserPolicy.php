<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Apakah user boleh melihat daftar user (Mitra/Client/Admin)?
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Apakah user boleh melihat detail user tertentu?
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Apakah user boleh membuat user baru?
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Apakah user boleh mengedit user lain?
     */
    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Apakah user boleh menghapus user lain?
     */
    public function delete(User $user, User $model): bool
    {
        // Admin boleh hapus, TAPI tidak boleh hapus dirinya sendiri
        return $user->isAdmin() && $user->id !== $model->id;
    }
}