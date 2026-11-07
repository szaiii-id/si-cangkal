<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Services\Trip\TripService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Listener: buat Trip dari Booking yang baru dikonfirmasi.
 *
 * Berjalan SINKRON (tidak implements ShouldQueue) karena:
 * - E-Ticket (listener berikutnya) membutuhkan `trip_id` yang baru dibuat.
 * - Notifikasi ke client harus menyebut kode trip yang valid.
 *
 * Prinsip:
 * - Listener hanya memanggil Service; tidak menyentuh Model langsung.
 * - Bila TripService gagal (mis. validasi status), error DILOG dan
 *   DILEMPAR ULANG agar `verifyPayment` tahu bahwa alur otomatis
 *   tidak lengkap. Caller (BookingPaymentService) yang menentukan
 *   apakah transaksi harus di-rollback atau tidak.
 * - Idempoten: TripService::createFromBooking sudah menolak bila
 *   booking sudah punya Trip (unique constraint `booking_id`), sehingga
 *   event yang terkirim ganda tidak menghasilkan duplikat.
 */
final class CreateTripForBooking
{
    public function __construct(
        private readonly TripService $tripService,
    ) {}

    /**
     * Tangani event BookingConfirmed.
     *
     * @throws \Throwable Bila pembuatan Trip gagal (agar transaksi pemanggil bisa rollback).
     */
    public function handle(BookingConfirmed $event): void
    {
        try {
            $this->tripService->createFromBooking($event->booking);
        } catch (Throwable $e) {
            Log::error('Gagal membuat Trip dari Booking yang dikonfirmasi', [
                'booking_id' => $event->booking->id,
                'booking_kode' => $event->booking->kode,
                'verified_by_mitra_id' => $event->verifiedByMitraId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}