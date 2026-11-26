<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\Booking\BookingPaymentService;
use App\Services\Booking\BookingQueryService;
use App\Services\ETicket\Contracts\ETicketServiceInterface;
use App\Services\Trip\TripService;
use App\Support\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Controller monitoring booking untuk admin.
 *
 * Tanggung jawab:
 * - Menampilkan daftar & detail booking.
 * - Menyediakan akses privat ke bukti transfer (S-09/B-01).
 * - Menyediakan aksi "Regenerate E-Ticket" untuk recovery manual
 *   jika `GenerateETicketJob` gagal.
 * - TIDAK melakukan mutasi state booking (upload, verify, cancel) —
 *   itu tanggung jawab Mitra/Client.
 *
 * Otorisasi dilakukan via `BookingPolicy` (S-02/S-03) di setiap
 * method, walaupun middleware `role:admin` sudah menjaga.
 */
final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingQueryService $bookingQueryService,
        private readonly ETicketServiceInterface $eticketService,
        private readonly TripService $tripService,
    ) {}

    /**
     * Daftar booking dengan filter, search, dan pagination.
     */
    public function index(ListBookingRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', Booking::class);

        $validated = $request->validated();

        $filters = [
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'package_id' => $validated['package_id'] ?? null,
            'tanggal_dari' => $validated['tanggal_dari'] ?? null,
            'tanggal_sampai' => $validated['tanggal_sampai'] ?? null,
            'is_today' => $validated['is_today'] ?? false,
            'sort' => $validated['sort'] ?? 'created_at',
            'direction' => $validated['direction'] ?? 'desc',
        ];

        $perPage = (int) ($validated['per_page'] ?? 15);

        return Inertia::render('Admin/Bookings/Index', [
            'bookings' => $this->bookingQueryService->paginate($filters, $perPage),
            'filters' => $filters,
            'statusCounts' => $this->bookingQueryService->countByStatus(),
        ]);
    }

    /**
     * Detail booking (dengan relasi lengkap untuk halaman admin).
     *
     * Eager-load `mitra.bankAccounts` wajib karena `BookingResource`
     * mengirimkan rekening mitra. Tanpa eager-load, akan terjadi N+1
     * per booking.
     */
    public function show(Booking $booking): InertiaResponse
    {
        $this->authorize('view', $booking);

        $booking->load([
            'package:id,nama,kode,slug,timezone,harga_per_orang,harga_per_rombongan,durasi_hari,durasi_malam,cover_image',
            'client:id,name,email,username',
            'mitra:id,name',
            'mitra.bankAccounts',
            'participants',
            'payments.verifier:id,name,email',
            'refunds',
        ]);

        return Inertia::render('Admin/Bookings/Show', [
            'booking' => (new BookingResource($booking))->resolve(),
        ]);
    }

    /**
     * Akses privat bukti transfer (S-09/B-01).
     *
     * File disimpan di disk `local` sehingga tidak bisa diakses via
     * `/storage/`. Route ini adalah satu-satunya jalan untuk admin
     * melihat bukti transfer yang diunggah client/mitra.
     */
    public function downloadProof(Booking $booking): StreamedResponse
    {
        $this->authorize('view', $booking);

        $path = $booking->payment_proof_path;

        abort_if($path === null || $path === '', 404);

        $disk = Storage::disk(BookingPaymentService::PROOF_DISK);

        if (! $disk->exists($path)) {
            $disk = Storage::disk('local'); // fallback bukti lama
        }

        abort_unless($disk->exists($path), 404);

        $filename = 'bukti-transfer-'.$booking->kode.'.'.pathinfo($path, PATHINFO_EXTENSION);

        return $disk->download($path, $filename);
    }

    /**
     * Regenerate E-Ticket untuk booking yang sudah CONFIRMED.
     *
     * Berguna jika `GenerateETicketJob` gagal karena:
     * - Queue worker sedang down saat job di-dispatch.
     * - Storage permission bermasalah saat generate file.
     * - Job masuk `failed_jobs` setelah 3x retry.
     *
     * Aksi:
     * - Kalau Trip belum ada (edge case), buat Trip terlebih dahulu
     *   via `TripService::createFromBooking()`.
     * - Panggil `ETicketService::generateForTrip()` untuk generate ulang
     *   (idempoten karena `updateOrCreate` pada `booking_id`).
     * - Audit log untuk tracking.
     *
     * Hanya untuk booking yang statusnya CONFIRMED/IN_PROGRESS/COMPLETED.
     * Status PENDING_PAYMENT/PAYMENT_VERIFIED/CANCELLED tidak relevan.
     */
    public function regenerateETicket(Booking $booking): RedirectResponse
    {
        $this->authorize('view', $booking);

        // Validasi status: hanya boleh untuk booking yang sudah dikonfirmasi
        if (! in_array($booking->status, [
            BookingStatus::CONFIRMED,
            BookingStatus::IN_PROGRESS,
            BookingStatus::COMPLETED,
        ], true)) {
            return back()->with('error', 'E-Ticket hanya bisa di-generate untuk booking yang sudah dikonfirmasi.');
        }

        try {
            $trip = Trip::where('booking_id', $booking->id)->first();

            if ($trip === null) {
                // Edge case: Trip belum dibuat (mis. listener gagal sebelumnya).
                // Buat Trip terlebih dahulu, baru generate E-Ticket.
                $trip = $this->tripService->createFromBooking($booking);
            }

            $this->eticketService->generateForTrip($trip);

            Log::info('Admin regenerate E-Ticket berhasil', [
                'admin_id' => auth()->id(),
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'trip_id' => $trip->id,
                'trip_kode' => $trip->kode,
            ]);

            return back()->with('status', 'E-Ticket berhasil di-generate ulang.');
        } catch (Throwable $e) {
            Log::error('Admin regenerate E-Ticket gagal', [
                'admin_id' => auth()->id(),
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Gagal regenerate E-Ticket: '.$e->getMessage());
        }
    }
}
