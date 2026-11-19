<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\StoreManualBookingRequest;
use App\Models\Package;
use App\Models\User;
use App\Services\Booking\BookingCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller pesanan manual oleh mitra (pelanggan offline / WhatsApp).
 *
 * Perubahan dari versi lama:
 * - `abort_unless($package->mitra_id === auth()->id(), 403)` diganti
 *   `$this->authorize('createManualBooking', $package)` via Policy
 *   `PackagePolicy` (S-02/S-03).
 * - Validasi inline (17 baris) -> `StoreManualBookingRequest` (NF-03).
 * - `BookingService::createManual` -> `BookingCreationService::createManual`
 *   (PRD 5.7).
 * - `User::firstOrCreate` untuk pembuatan akun client otomatis
 *   tetap di controller karena melibatkan `Hash::make(Str::random(16))`
 *   yang spesifik untuk alur HTTP ini (bukan domain Booking).
 */
final class ManualBookingController extends Controller
{
    public function __construct(
        private readonly BookingCreationService $bookingCreationService,
    ) {}

    public function create(Package $package): InertiaResponse
    {
        $this->authorize('createManualBooking', $package);

        return Inertia::render('Mitra/Bookings/CreateManual', [
            'package' => $package,
        ]);
    }

    public function store(StoreManualBookingRequest $request, Package $package): RedirectResponse
    {
        $this->authorize('createManualBooking', $package);

        $validated = $request->validated();

        try {
            $client = User::firstOrCreate(
                ['email' => $validated['email_pelanggan']],
                [
                    'name' => $validated['nama_pelanggan'],
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'client',
                    'timezone' => $package->timezone,
                ],
            );

            $booking = $this->bookingCreationService->createManual(
                package: $package,
                client: $client,
                mitra: $request->user(),
                data: $validated,
            );

            return redirect()
                ->route('mitra.bookings.show', $booking->id)
                ->with('status', 'Pesanan manual berhasil dibuat. Silakan unggah bukti pembayaran dan konfirmasi pesanan.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()
                ->with('error', 'Gagal membuat pesanan manual: '.$e->getMessage())
                ->withInput();
        }
    }
}
