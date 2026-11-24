<?php

namespace App\Http\Controllers\Client;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ListBookingRequest;
use App\Http\Requests\Client\StoreBookingRequest;
use App\Http\Requests\Client\UploadPaymentProofRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\Booking\BookingCancellationService;
use App\Services\Booking\BookingCreationService;
use App\Services\Booking\BookingPaymentService;
use App\Services\Booking\BookingQueryService;
use App\Services\Payment\PaymentChargeService;
use App\Services\Review\ReviewService;
use App\Support\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Controller booking untuk client.
 *
 * Perubahan dari versi lama:
 * - BookingService dipecah ke 4 service (PRD 5.7).
 * - abort_unless / authorizeOwnership -> $this->authorize (Policy).
 * - Validasi upload bukti via UploadPaymentProofRequest (S-08).
 * - Route download bukti transfer privat ditambahkan (S-09/B-01).
 * - Controller < 100 baris per method (NF-03).
 * - Storage::disk() diganti App\Support\Storage::disk() agar
 *   type-safe (FilesystemAdapter) dan Intelephense mengenali
 *   method download().
 * - index memakai ListBookingRequest (konsisten dengan admin/mitra).
 * - store catch ValidationException + Throwable -> response ramah.
 * - uploadProof memakai $request->paymentProof() (type-safe).
 * - downloadProof trim path (defensive).
 * - show trim UUID (defensive, legacy data).
 */
final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingQueryService $bookingQueryService,
        private readonly BookingCreationService $bookingCreationService,
        private readonly BookingPaymentService $bookingPaymentService,
        private readonly BookingCancellationService $bookingCancellationService,
        private readonly ReviewService $reviewService,
        private readonly ReviewRepositoryInterface $reviewRepository,
    ) {}

    public function index(ListBookingRequest $request): InertiaResponse
    {
        $client = $request->user();
        $validated = $request->validated();

        $filters = [
            'search' => is_string($validated['search'] ?? null) ? $validated['search'] : null,
            'status' => is_string($validated['status'] ?? null) ? $validated['status'] : null,
            'sort' => is_string($validated['sort'] ?? null) ? $validated['sort'] : 'created_at',
            'direction' => is_string($validated['direction'] ?? null) ? $validated['direction'] : 'desc',
        ];

        $perPage = (int) ($validated['per_page'] ?? 10);

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
        try {
            $booking = $this->bookingCreationService->create(
                package: $package,
                client: $request->user(),
                data: $request->validated(),
            );

            return redirect()
                ->route('client.bookings.show', $booking->id)
                ->with('status', 'Booking berhasil dibuat. Silakan transfer ke rekening Mitra.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            Log::error('Client booking creation failed', [
                'user_id' => $request->user()?->id,
                'package_id' => $package->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->with('error', 'Gagal membuat booking. Silakan coba lagi.')
                ->withInput();
        }
    }

    public function show(Booking $booking): InertiaResponse
    {
        $this->authorize('view', $booking);

        $booking->load([
            'package:id,nama,kode,slug,timezone,harga_per_orang,harga_per_rombongan,durasi_hari,durasi_malam,cover_image',
            'trip:id,booking_id,status',
            'participants',
            'payments.verifier:id,name,email',
            'mitra:id,name',
            'mitra.profile',
            'mitra.bankAccounts',
            'refunds' => fn ($query) => $query->latest('id')->limit(1),
        ]);

        $eticket = $this->bookingQueryService->getETicketForBooking($booking);

        /** @var User $user */
        $user = auth()->user();
        $review = $this->reviewRepository->findByBooking($booking);

        return Inertia::render('Client/Bookings/Show', [
            'booking' => (new BookingResource($booking))->resolve(),
            'canReview' => $this->reviewService->canReview($user, $booking),
            'review' => $review !== null ? (new ReviewResource($review))->resolve() : null,
            'eticket' => $eticket !== null
                ? [
                    'uuid' => trim($eticket->uuid),
                    'download_url' => route('client.e-tickets.download', trim($eticket->uuid)),
                    'generated_at' => $eticket->generated_at->toIso8601String(),
                ]
                : null,
            'refunds' => $booking->refunds
                ->map(fn ($refund) => [
                    'id' => $refund->id,
                    'amount' => (string) $refund->amount,
                    'status' => $refund->status->value,
                    'created_at' => $refund->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function cancel(Booking $booking): RedirectResponse
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

    /**
     * Buat link checkout DOKU untuk booking yang masih menunggu pembayaran.
     *
     * Transfer manual tetap tersedia lewat jalur upload bukti; endpoint ini
     * hanya menyiapkan opsi gateway.
     */
    public function pay(Booking $booking, PaymentChargeService $chargeService): SymfonyResponse
    {
        $this->authorize('view', $booking);

        if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
            throw ValidationException::withMessages([
                'status' => ['Booking ini tidak lagi menunggu pembayaran.'],
            ]);
        }

        $result = $chargeService->createForBooking($booking);

        // Inertia::location memberi tahu client untuk membuka URL eksternal
        // DOKU. Ini tetap memakai router Inertia (tanpa fetch manual).
        return Inertia::location($result->checkoutUrl);
    }

    public function uploadProof(UploadPaymentProofRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('uploadProof', $booking);

        $this->bookingPaymentService->uploadProof(
            $booking,
            $request->paymentProof(),
        );

        return back()->with('status', 'Bukti transfer berhasil diunggah. Menunggu verifikasi Mitra.');
    }

    /**
     * Akses privat bukti transfer (S-09/B-01).
     *
     * File disimpan di disk local sehingga tidak bisa diakses via
     * /storage/. Route ini adalah satu-satunya jalan untuk client
     * melihat kembali bukti transfer yang ia unggah.
     */
    public function downloadProof(Booking $booking): StreamedResponse
    {
        $this->authorize('view', $booking);

        $path = trim((string) $booking->payment_proof_path);

        abort_if($path === '', 404);

        $disk = Storage::disk(BookingPaymentService::PROOF_DISK);

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, 'bukti-transfer-'.$booking->kode.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }
}
