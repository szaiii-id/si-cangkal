<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundSearchRequest;
use App\Models\Refund;
use App\Services\Refund\RefundQueryService;
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
 */
final class RefundController extends Controller
{
    public function __construct(
        private readonly RefundQueryService $refundQueryService,
    ) {}

    /**
     * Daftar refund (admin).
     */
    public function index(RefundSearchRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', Refund::class);

        $validated = $request->validated();

        $filters = [
            'search' => is_string($validated['search'] ?? null) ? $validated['search'] : null,
            'status' => is_string($validated['status'] ?? null) ? $validated['status'] : null,
        ];

        $perPage = (int) ($validated['per_page'] ?? 15);

        return Inertia::render('Admin/Refunds/Index', [
            'refunds' => $this->refundQueryService->paginateForAdmin($filters, $perPage),
            'filters' => $filters,
            'statusCounts' => $this->refundQueryService->countByStatus(),
        ]);
    }
}