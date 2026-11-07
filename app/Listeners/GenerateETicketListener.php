<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Jobs\GenerateETicketJob;
use App\Models\Trip;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener: jadwalkan pembuatan E-Ticket setelah Trip dibuat.
 *
 * Berjalan di QUEUE (`ShouldQueue`) karena pembuatan PDF + QR memakan
 * waktu dan tidak boleh menahan response HTTP `verifyPayment`
 * (NF-02: verifikasi < 200ms).
 *
 * Urutan eksekusi:
 * - Listener ini hanya boleh diproses SETELAH `CreateTripForBooking`
 *   selesai (Trip sudah ada). Karena `CreateTripForBooking` sinkron dan
 *   dipanggil sebelum listener ini oleh dispatcher event, urutan
 *   ini terjamin.
 *
 * Idempotensi:
 * - Bila `GenerateETicketJob` di-dispatch berulang (mis. karena retry
 *   listener), `ETicketService::generateForTrip` memakai `updateOrCreate`
 *   pada `booking_id`, sehingga tidak membuat duplikat.
 */
final class GenerateETicketListener implements ShouldQueue
{
    /**
     * Nama koneksi queue (null = pakai default).
     */
    public ?string $connection = null;

    /**
     * Nama queue.
     */
    public string $queue = 'e-ticket';

    /**
     * Jumlah percobaan listener.
     *
     * Bila gagal (mis. DB sementara tidak tersedia), listener dicoba ulang.
     * Job E-Ticket punya retry terpisah (3x) setelah listener berhasil
     * meng-dispatch-nya.
     */
    public int $tries = 3;

    /**
     * Backoff antar percobaan listener (detik).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    /**
     * Tangani event BookingConfirmed.
     */
    public function handle(BookingConfirmed $event): void
    {
        $trip = Trip::where('booking_id', $event->booking->id)->first();

        if ($trip === null) {
            /*
             * Trip belum ada. Ini kondisi abnormal karena
             * CreateTripForBooking harus selesai lebih dulu.
             * Lempar exception agar listener dicoba ulang (bisa jadi
             * ada race condition transient pada replika DB).
             */
            Log::warning('GenerateETicketListener: Trip belum ada, akan retry', [
                'booking_id' => $event->booking->id,
                'booking_kode' => $event->booking->kode,
            ]);

            throw new \RuntimeException(
                "Trip untuk booking {$event->booking->kode} belum tersedia."
            );
        }

        GenerateETicketJob::dispatch($trip->id);

        Log::info('GenerateETicketListener: Job E-Ticket dijadwalkan', [
            'trip_id' => $trip->id,
            'trip_kode' => $trip->kode,
            'booking_id' => $event->booking->id,
        ]);
    }
}