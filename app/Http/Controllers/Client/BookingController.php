<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreBookingRequest;
use App\Models\Booking;
use App\Models\ETicket;
use App\Models\Package;
use App\Services\Booking\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response; // ✅ TAMBAHKAN INI

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
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
        $bookings = $this->bookingService->paginateForClient($client, $filters, $perPage);

        return Inertia::render('Client/Bookings/Index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'statusCounts' => $this->bookingService->countByStatusForClient($client),
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

    /**
     * Simpan booking baru & redirect ke Xendit Invoice.
     */
    // ✅ UBAH RETURN TYPE: Tambahkan Symfony Response
    public function store(StoreBookingRequest $request, Package $package): Response|InertiaResponse|RedirectResponse
    {
        $booking = $this->bookingService->create(
            package: $package,
            client: $request->user(),
            data: $request->validated(),
        );

        // ✅ Gunakan Inertia::location() agar browser melakukan navigasi penuh
        if ($booking->xendit_invoice_url !== null) {
            return Inertia::location($booking->xendit_invoice_url);
        }

        // Fallback jika gagal
        return redirect()
            ->route('client.bookings.show', $booking->id)
            ->with('status', 'Booking berhasil dibuat, tapi invoice pembayaran gagal dibuat otomatis.');
    }

    public function show(Request $request, Booking $booking): InertiaResponse
    {
        $this->authorizeOwnership($request, $booking);

        $booking->load([
            'package:id,nama,kode,slug,timezone,harga_per_orang,harga_per_rombongan,durasi_hari,durasi_malam,cover_image',
            'participants',
            'payments.verifier:id,name,email',
        ]);

        $eticket = ETicket::where('booking_id', $booking->id)->first();

        return Inertia::render('Client/Bookings/Show', [
            'booking' => $booking,
            'eticket' => $eticket ? [
                'uuid' => $eticket->uuid,
                'pdf_url' => asset('storage/'.$eticket->pdf_path),
                'generated_at' => $eticket->generated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeOwnership($request, $booking);

        $this->bookingService->cancelByClient($booking);

        return redirect()
            ->route('client.bookings.show', $booking->id)
            ->with('status', 'Booking berhasil dibatalkan.');
    }

    protected function authorizeOwnership(Request $request, Booking $booking): void
    {
        abort_unless(
            $booking->user_id === $request->user()->id,
            403,
            'Anda tidak memiliki akses ke booking ini.'
        );
    }
}