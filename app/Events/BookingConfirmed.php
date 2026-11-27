<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: pembayaran booking telah diverifikasi oleh mitra.
 *
 * Dipancarkan oleh `BookingPaymentService::verifyPayment()` setelah
 * transaksi database (status booking -> confirmed, payment dicatat)
 * berhasil. Event ini menjadi titik awal alur otomatis:
 *
 *     verifyPayment()
 *       -> dispatch(BookingConfirmed)
 *          -> CreateTripForBooking (sync)  : buat Trip + TripDay,
 *                                            lalu dispatch GenerateETicketJob
 *
 * Prinsip:
 * - Event bersifat immutable. Listener tidak boleh mengubah state Booking;
 *   perubahan state hanya boleh lewat Service.
 * - Event membawa model `Booking` (SerializesModels) beserta `mitraId`
 *   agar listener queue tidak bergantung pada `auth()->user()`.
 * - Event TIDAK membawa data sensitif (bukti transfer, dsb). Listener
 *   yang butuh akses file harus query sendiri lewat Booking.
 */
final class BookingConfirmed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Booking  $booking  Booking yang baru diverifikasi.
     * @param  string  $verifiedByMitraId  ID mitra yang memverifikasi pembayaran.
     *                                     Dipakai listener queue agar tidak
     *                                     bergantung pada sesi auth.
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly string $verifiedByMitraId,
    ) {}
}
