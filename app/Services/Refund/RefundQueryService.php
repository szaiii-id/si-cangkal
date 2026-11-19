<?php

namespace App\Services\Refund;

use App\Http\Controllers\Admin\RefundController;
use App\Models\Refund;
use App\Models\User;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Service read-only untuk Refund.
 *
 * Hasil pemecahan tanggung jawab:
 * - {@see RefundInitiationService} fokus pada pembuatan refund baru.
 * - {@see RefundApprovalService} fokus pada approve/reject refund.
 * - Service ini fokus pada query (list, detail, count).
 *
 * Memisahkan query dari state transition memudahkan audit
 * "siapa yang bisa mengubah state" vs "siapa yang bisa membaca".
 *
 * Dipakai oleh:
 * - {@see RefundController}
 * - {@see \App\Http\Controllers\Mitra\RefundController}
 */
final class RefundQueryService
{
    public function __construct(
        private readonly RefundRepositoryInterface $refundRepository,
    ) {}

    /**
     * Daftar refund (admin).
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, Refund>
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->refundRepository->paginateForAdmin($filters, $perPage);
    }

    /**
     * Daftar refund (mitra, hanya miliknya).
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, Refund>
     */
    public function paginateForMitra(
        User $mitra,
        array $filters = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->refundRepository->paginateForMitra($mitra, $filters, $perPage);
    }

    /**
     * Detail refund (admin).
     *
     * @throws ValidationException Bila refund tidak ditemukan.
     */
    public function findDetail(int $id): Refund
    {
        $refund = $this->refundRepository->findDetailById($id);

        if ($refund === null) {
            throw ValidationException::withMessages([
                'refund' => ['Refund tidak ditemukan.'],
            ]);
        }

        return $refund;
    }

    /**
     * Hitung refund per status (global, untuk admin).
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        return $this->refundRepository->countByStatus();
    }

    /**
     * Hitung refund per status untuk mitra tertentu.
     *
     * @return array<string, int>
     */
    public function countByStatusForMitra(User $mitra): array
    {
        return $this->refundRepository->countByStatusForMitra($mitra);
    }
}
