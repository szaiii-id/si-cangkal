<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreBookingRequest;
use App\Http\Requests\Client\UploadPaymentProofRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Package;
use App\Services\Booking\BookingCancellationService;
use App\Services\Booking\BookingCreationService;
use App\Services\Booking\BookingPaymentService;
use App\Services\Booking\BookingQueryService;
use App\Support\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller booking untuk client.
 *
 * Perubahan dari versi lama:
 * - `BookingService` dipecah ke 4 service (PRD 5.7).
 * - `abort_unless` / `authorizeOwnership` -> `$this->authorize` (Policy).
 * - Validasi upload bukti via `UploadPaymentProofRequest` (S-08).
 * - Route download bukti transfer privat ditambahkan (S-09/B-01).
 * - Controller < 100 baris per method (NF-03).
 * - `Storage::disk()` diganti `App\Support\Storage::disk()` agar
 *   type-safe (FilesystemAdapter) dan Intelephense mengenali
 *   method `download()`.
 */
final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingQueryService $bookingQueryService,
        private readonly BookingCreationService $bookingCreationService,
        private readonly BookingPaymentService $bookingPaymentService,
        private readonly BookingCancellationService $bookingCancellationService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $client = $request->user();

        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort' => $request->input('sort', 'created_at'),
            'direction' => $request->input('direction', 'desc'),
        ];

        $perPage = (int) $request->input('per_page', 10);

        return Inertia::render('Client/Bookings/Index', [
            'bookings' => $this->bookingQueryService->paginateForClient($client, $filters, $perPage),
            'filters' => $filters,
            'statusCounts' => $this->bookingQueryService->countByStatusForClient($client),
        ]);
    }

    public function create(Package $package): InertiaResponse
    {
        abort_unless($package->isAktif(), 404);

        $package->load(['facilities']);

        return Inertia::render('Client/Bookings/Create', [
            'package' => $package,
        ]);
    }

    public function store(StoreBookingRequest $request, Package $package): RedirectResponse
    {
        $booking = $this->bookingCreationService->create(
            package: $package,
            client: $request->user(),
            data: $request->validated(),
        );

        return redirect()
            ->route('client.bookings.show', $booking->id)
            ->with('status', 'Booking berhasil dibuat. Silakan transfer ke rekening Mitra.');
    }

    public function show(Request $request, Booking $booking): InertiaResponse
    {
        $this->authorize('view', $booking);

        $booking->load([
            'package:id,nama,kode,slug,timezone,harga_per_orang,harga_per_rombongan,durasi_hari,durasi_malam,cover_image',
            'participants',
            'payments.verifier:id,name,email',
            'mitra:id,name',
            'mitra.profile',
            'mitra.bankAccounts',
            'refunds' => fn ($query) => $query->latest('id')->limit(1),
        ]);

        $eticket = $this->bookingQueryService->getETicketForBooking($booking);

        return Inertia::render('Client/Bookings/Show', [
            'booking' => (new BookingResource($booking))->resolve(),
            'eticket' => $eticket !== null
                ? [
                    'uuid' => $eticket->uuid,
                    'download_url' => route('client.e-tickets.download', $eticket->uuid),
                    'generated_at' => $eticket->generated_at?->toIso8601String(),
                ]
                : null,
            'refunds' => $booking->refunds
                ->map(fn ($refund) => [
                    'id' => $refund->id,
                    'amount' => (string) $refund->amount,
                    'status' => $refund->status->value,
                    'created_at' => $refund->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancelByClient', $booking);

        try {
            $this->bookingCancellationService->cancelByClient($booking);

            return redirect()
                ->route('client.bookings.show', $booking->id)
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
            $request->file('payment_proof'),
        );

        return back()->with('status', 'Bukti transfer berhasil diunggah. Menunggu verifikasi Mitra.');
    }

    /**
     * Akses privat bukti transfer (S-09/B-01).
     *
     * File disimpan di disk `local` sehingga tidak bisa diakses via
     * `/storage/`. Route ini adalah satu-satunya jalan untuk client
     * melihat kembali bukti transfer yang ia unggah.
     */
    public function downloadProof(Booking $booking): StreamedResponse
    {
        $this->authorize('view', $booking);

        $path = $booking->payment_proof_path;

        abort_if($path === null || $path === '', 404);

        $disk = Storage::disk(BookingPaymentService::PROOF_DISK);

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, 'bukti-transfer-'.$booking->kode.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }
}