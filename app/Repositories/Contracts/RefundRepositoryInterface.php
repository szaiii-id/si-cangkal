<?php

namespace App\Repositories\Contracts;

use App\Models\Refund;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RefundRepositoryInterface
{
    /**
     * Daftar refund untuk admin (dengan filter search & status).
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Refund>
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Daftar refund untuk mitra (hanya refund miliknya).
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Refund>
     */
    public function paginateForMitra(User $mitra, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Cari refund berdasarkan ID (dengan relasi untuk detail admin).
     */
    public function findDetailById(int $id): ?Refund;

    /**
     * Hitung refund per status (untuk badge counter admin).
     *
     * @return array<string, int>
     */
    public function countByStatus(): array;

    /**
     * Hitung refund per status untuk mitra tertentu.
     *
     * @return array<string, int>
     */
    public function countByStatusForMitra(User $mitra): array;
}
