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

final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingQueryService $bookingQueryService,
        private readonly BookingPaymentService $bookingPaymentService,
        private readonly BookingCancellationService $bookingCancellationService,
    ) {}

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
            'statusCounts' => $this->bookingQueryService->countByStatusForMitra($mitra),
        ]);
    }

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

    public function uploadProof(UploadPaymentProofRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('uploadProof', $booking);

        $this->bookingPaymentService->uploadProof(
            $booking,
            $request->paymentProof(),
        );

        return back()->with('status', 'Bukti transfer berhasil diunggah.');
    }

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
