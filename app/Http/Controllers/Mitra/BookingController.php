<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\CancelBookingRequest;
use App\Http\Requests\Mitra\ListBookingRequest;
use App\Http\Requests\Mitra\RejectPaymentRequest;
use App\Http\Requests\Mitra\UploadPaymentProofRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Booking\BookingCancellationService;
use App\Services\Booking\BookingPaymentService;
use App\Services\Booking\BookingQueryService;
use App\Support\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller booking untuk mitra.
 *
 * Tanggung jawab:
 * - Daftar & detail booking yang ditujukan ke mitra.
 * - Verifikasi / tolak bukti transfer.
 * - Batalkan booking (dengan alasan).
 * - Unggah bukti transfer atas nama client (pesanan manual).
 * - Akses privat bukti transfer (S-09/B-01).
 *
 * Otorisasi: via `BookingPolicy` di setiap method (S-02/S-03).
 * Refund ditangani terpisah di `Mitra\RefundController`.
 */
final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingQueryService $bookingQueryService,
        private readonly BookingPaymentService $bookingPaymentService,
        private readonly BookingCancellationService $bookingCancellationService,
    ) {}

    /**
     * Daftar booking mitra.
     */
    public function index(ListBookingRequest $request): InertiaResponse
    {
        $mitra = $request->user();
        $validated = $request->validated();

        $filters = [
            'search' => is_string($validated['search'] ?? null) ? $validated['search'] : null,
            'status' => is_string($validated['status'] ?? null) ? $validated['status'] : null,
            'sort' => is_string($validated['sort'] ?? null) ? $validated['sort'] : 'created_at',
            'direction' => is_string($validated['direction'] ?? null) ? $validated['direction'] : 'desc',
            'mitra_id' => $mitra->id,
        ];

        $perPage = (int) ($validated['per_page'] ?? 15);

        return Inertia::render('Mitra/Bookings/Index', [
            'bookings' => $this->bookingQueryService->paginate($filters, $perPage),
            'filters' => [
                'search' => $filters['search'],
                'status' => $filters['status'],
            ],
        ]);
    }

    /**
     * Detail booking.
     *
     * Eager-load `mitra.bankAccounts` wajib karena `BookingResource`
     * mengirim rekening mitra. Tanpa eager-load: N+1 per booking.
     */
    public function show(Booking $booking): InertiaResponse
    {
        $this->authorize('view', $booking);

        $booking->load([
            'package:id,nama,kode,slug,timezone,cover_image,harga_per_orang,harga_per_rombongan,durasi_hari,durasi_malam',
            'client:id,name,email,username',
            'mitra:id,name',
            'mitra.bankAccounts',
            'participants',
            'payments.verifier:id,name,email',
            'refunds',
        ]);

        return Inertia::render('Mitra/Bookings/Show', [
            'booking' => (new BookingResource($booking))->resolve(),
        ]);
    }

    /**
     * Verifikasi pembayaran.
     *
     * Guard tambahan: meskipun Policy `verifyPayment` tidak mengecek
     * keberadaan file bukti, kita tolak verifikasi tanpa bukti agar
     * mitra tidak "verifikasi kosong".
     */
    public function verifyPayment(Booking $booking): RedirectResponse
    {
        $this->authorize('verifyPayment', $booking);

        if ($booking->payment_proof_path === null || $booking->payment_proof_path === '') {
            return back()->with('error', 'Client belum mengunggah bukti transfer.');
        }

        try {
            $this->bookingPaymentService->verifyPayment($booking, request()->user());

            return back()->with('status', 'Pembayaran berhasil diverifikasi. Trip dan E-Ticket sedang diproses.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Tolak bukti pembayaran (dengan alasan wajib).
     */
    public function rejectPayment(RejectPaymentRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('rejectPayment', $booking);

        try {
            $this->bookingPaymentService->rejectPayment(
                $booking,
                $request->user(),
                $request->validated('reason'),
            );

            return back()->with('status', 'Bukti transfer ditolak. Client telah diberitahu.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Batalkan booking (dengan alasan wajib).
     */
    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancelByMitra', $booking);

        try {
            $this->bookingCancellationService->cancelByMitra(
                $booking,
                $request->user(),
                $request->validated('alasan'),
            );

            return redirect()
                ->route('mitra.bookings.index')
                ->with('status', 'Booking berhasil dibatalkan.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Unggah bukti transfer (untuk pesanan manual atas nama client).
     */
    public function uploadProof(UploadPaymentProofRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('uploadProof', $booking);

        $this->bookingPaymentService->uploadProof(
            $booking,
            $request->paymentProof(),
        );

        return back()->with('status', 'Bukti transfer berhasil diunggah.');
    }

    /**
     * Akses privat bukti transfer (S-09/B-01).
     *
     * File disimpan di disk `local`, tidak bisa diakses via `/storage/`.
     * Route ini adalah satu-satunya jalan untuk mitra melihat kembali
     * bukti transfer yang diunggah client (atau dirinya sendiri).
     */
    public function downloadProof(Booking $booking): StreamedResponse
    {
        $this->authorize('view', $booking);

        $path = $booking->payment_proof_path;

        abort_if($path === null || $path === '', 404);

        $disk = Storage::disk(BookingPaymentService::PROOF_DISK);

        abort_unless($disk->exists($path), 404);

        $filename = 'bukti-transfer-'.$booking->kode.'.'.pathinfo($path, PATHINFO_EXTENSION);

        return $disk->download($path, $filename);
    }
}