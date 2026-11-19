<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundSearchRequest;
use App\Http\Resources\RefundResource;
use App\Models\Refund;
use App\Services\Refund\RefundQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller monitoring refund untuk admin (read-only).
 *
 * Tanggung jawab:
 * - Menampilkan daftar refund dengan filter & pagination.
 *
 * Tidak ada aksi approve/reject di admin — itu tanggung jawab mitra.
 * Tidak ada halaman detail refund di admin — semua info yang relevan
 * sudah ditampilkan di card pada halaman index.
 *
 * Otorisasi:
 * - `viewAny` via `RefundPolicy` (S-02/S-03).
 *
 * Catatan:
 * - Query dibangun di `RefundQueryService` + `RefundRepository`
 *   (SOC, S-06: LikeEscaper untuk mencegah wildcard injection).
 * - Filter divalidasi via `RefundSearchRequest` (NF-03).
 * - Response di-wrap dengan `RefundResource::collection()` agar field
 *   turunan (`amountRupiah`, `statusLabel`, `isFinal`, dll) tersedia
 *   di frontend & type-safe terhadap `AdminRefundIndexProps`.
 */
final class RefundController extends Controller
{
    public function __construct(
        private readonly RefundQueryService $refundQueryService,
    ) {}

    /**
     * Daftar refund (admin).
     *
     * @throws AuthorizationException
     */
    public function index(RefundSearchRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', Refund::class);

        /** @var array{search?: string|null, status?: string|null, per_page?: int|string|null} $validated */
        $validated = $request->validated();

        $filters = [
            'search' => is_string($validated['search'] ?? null)
                ? $validated['search']
                : null,
            'status' => is_string($validated['status'] ?? null)
                ? $validated['status']
                : null,
        ];

        $perPage = (int) ($validated['per_page'] ?? 15);

        //    (amountRupiah, statusLabel, reasonLabel, isFinal, dll)
        //    tersedia di frontend.
        $refunds = RefundResource::collection(
            $this->refundQueryService->paginateForAdmin($filters, $perPage),
        );

        return Inertia::render('Admin/Refunds/Index', [
            'refunds' => $refunds,
            'filters' => $filters,
            'statusCounts' => $this->refundQueryService->countByStatus(),
        ]);
    }
}
