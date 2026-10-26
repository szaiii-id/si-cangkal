<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectPayoutRequest;
use App\Models\Payout;
use App\Services\Wallet\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PayoutController extends Controller
{
    public function __construct(
        protected PayoutService $payoutService,
    ) {}

    /**
     * Daftar payout (semua mitra).
     *
     * Route: GET /admin/payouts
     */
    public function index(Request $request): InertiaResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ];

        $payouts = $this->payoutService->paginate($filters, 15);

        return Inertia::render('Admin/Payouts/Index', [
            'payouts' => $payouts,
            'filters' => $filters,
            'statusCounts' => $this->payoutService->countByStatus(),
        ]);
    }

    /**
     * Approve payout → transfer otomatis ke Xendit.
     *
     * Route: POST /admin/payouts/{payout}/approve
     *
     * Alur (dalam DB transaction):
     * 1. Potong saldo mitra (debitForPayout).
     * 2. Kirim API Xendit createPayout.
     * 3. Kalau sukses → status `approved` + simpan `xendit_payout_id`.
     * 4. Kalau gagal → rollback saldo & throw error.
     *
     * Setelah Xendit transfer sukses, webhook akan set status ke `paid`.
     */
    public function approve(Payout $payout): RedirectResponse
    {
        try {
            $this->payoutService->approve($payout, auth()->user());

            return redirect()->back()->with(
                'status',
                'Payout disetujui & sedang diproses oleh Xendit. '.
                'Status akan otomatis berubah setelah transfer berhasil.'
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }
    }

    /**
     * Reject payout.
     *
     * Route: POST /admin/payouts/{payout}/reject
     *
     * Menggunakan RejectPayoutRequest (validasi terpusat)
     */
    public function reject(RejectPayoutRequest $request, Payout $payout): RedirectResponse
    {
        $this->payoutService->reject(
            $payout,
            auth()->user(),
            $request->validated('reason'),
        );

        return redirect()->back()->with('status', 'Payout ditolak.');
    }

}