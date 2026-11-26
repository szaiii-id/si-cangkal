<?php

namespace App\Http\Controllers\Mitra;

use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\ApproveRefundRequest;
use App\Http\Requests\Mitra\ListRefundRequest;
use App\Http\Requests\Mitra\RejectRefundRequest;
use App\Http\Requests\Mitra\UploadRefundProofRequest;
use App\Models\Refund;
use App\Services\Refund\RefundApprovalService;
use App\Services\Refund\RefundInitiationService;
use App\Services\Refund\RefundQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

/**
 * Controller refund untuk mitra.
 *
 * Tanggung jawab:
 * - Menampilkan daftar refund milik mitra (read-only).
 * - Menyetujui / menolak refund (state transition).
 *
 * Dependency:
 * - `RefundQueryService` untuk query (list, count).
 * - `RefundApprovalService` untuk approve/reject refund `PENDING`.
 *
 * Otorisasi:
 * - `viewAny`, `approve`, `reject` via `RefundPolicy` (S-02/S-03).
 *
 * Validasi:
 * - Filter via `ListRefundRequest` (search, status, per_page).
 * - Body approve/reject via Form Request masing-masing.
 */
final class RefundController extends Controller
{
    public function __construct(
        private readonly RefundApprovalService $refundApprovalService,
        private readonly RefundInitiationService $refundInitiationService,
        private readonly RefundQueryService $refundQueryService,
    ) {}

    /**
     * Daftar refund milik mitra.
     *
     * Mendukung filter `search` (kode booking / nama client / nama mitra)
     * dan `status` (enum RefundStatus). Hasil selalu di-scope ke
     * `mitra_id` pemilik.
     */
    public function index(ListRefundRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', Refund::class);

        $mitra = $request->user();
        $validated = $request->validated();

        $filters = [
            'search' => is_string($validated['search'] ?? null) ? $validated['search'] : null,
            'status' => is_string($validated['status'] ?? null) ? $validated['status'] : null,
        ];

        $perPage = (int) ($validated['per_page'] ?? 15);

        return Inertia::render('Mitra/Refunds/Index', [
            'refunds' => $this->refundQueryService->paginateForMitra($mitra, $filters, $perPage),
            'filters' => $filters,
            'statusCounts' => $this->refundQueryService->countByStatusForMitra($mitra),
        ]);
    }

    /**
     * Setujui refund (PENDING -> SUCCEEDED).
     *
     * Aksi (dilakukan di `RefundApprovalService`):
     * - Tambah `Payment` negatif sebagai jejak pengembalian dana.
     * - Ubah status booking menjadi `CANCELLED`.
     * - Append catatan approval.
     * - Notifikasi client (in-app + FCM).
     */
    public function approve(ApproveRefundRequest $request, Refund $refund): RedirectResponse
    {
        $this->authorize('approve', $refund);

        try {
            $result = $this->refundApprovalService->approve(
                refund: $refund,
                mitra: $request->user(),
                catatan: $request->validated('catatan'),
            );

            $message = $result->status === RefundStatus::AWAITING_PROOF
                ? 'Refund menunggu bukti transfer dari Anda.'
                : 'Refund berhasil diproses secara manual.';

            return redirect()
                ->route('mitra.refunds.index')
                ->with('status', $message);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            return back()
                ->with('error', 'Gagal memproses refund: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Unggah bukti transfer untuk refund yang menunggu bukti.
     *
     * Ini satu-satunya jalan bagi refund manual menjadi SUCCEEDED.
     */
    public function uploadProof(UploadRefundProofRequest $request, Refund $refund): RedirectResponse
    {
        $this->authorize('uploadProof', $refund);

        try {
            $this->refundInitiationService->uploadProof(
                refund: $refund,
                mitra: $request->user(),
                file: $request->proofFile(),
                catatan: $request->validated('catatan'),
            );

            return redirect()
                ->route('mitra.refunds.index')
                ->with('status', 'Bukti transfer tersimpan. Dana refund ditandai selesai.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            return back()
                ->with('error', 'Gagal menyimpan bukti transfer: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Tolak refund (PENDING -> CANCELLED).
     *
     * Aksi (dilakukan di `RefundApprovalService`):
     * - Status refund menjadi `CANCELLED` (dana tidak dikembalikan).
     * - Append catatan penolakan (audit trail).
     * - Notifikasi client dengan alasan penolakan.
     */
    public function reject(RejectRefundRequest $request, Refund $refund): RedirectResponse
    {
        $this->authorize('reject', $refund);

        try {
            $this->refundApprovalService->reject(
                refund: $refund,
                mitra: $request->user(),
                alasan: $request->validated('alasan_penolakan'),
            );

            return redirect()
                ->route('mitra.refunds.index')
                ->with('status', 'Pengajuan refund berhasil ditolak.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            return back()
                ->with('error', 'Gagal menolak refund: '.$e->getMessage())
                ->withInput();
        }
    }
}
