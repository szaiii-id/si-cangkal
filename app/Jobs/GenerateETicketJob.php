<?php

namespace App\Jobs;

use App\Models\Trip;
use App\Services\ETicket\ETicketService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job: generate E-Ticket untuk sebuah Trip di background.
 *
 * Ditembak oleh `GenerateETicketListener` setelah event `BookingConfirmed`.
 * Dipisah ke Job (bukan dikerjakan langsung di listener) karena:
 * - PDF + QR generation memakan waktu > 200ms (NF-02).
 * - Harus tahan terhadap kegagalan sementara (disk lambat, dsb) lewat retry.
 *
 * Kebijakan retry:
 * - 3 percobaan, backoff 10s, 30s, 60s (eksponensial).
 * - Bila tetap gagal, Job masuk ke `failed_jobs`. Client TIDAK
 *   mendapat notifikasi "e-ticket siap" (lihat `NotifyClientListener`).
 *
 * Idempotensi:
 * - `ETicketService::generateForTrip` memakai `updateOrCreate` pada
 *   `booking_id`, sehingga retry tidak menghasilkan file ganda di DB.
 */
final class GenerateETicketJob implements ShouldQueue
{
    use Queueable;

    /**
     * Jumlah percobaan maksimum.
     */
    public int $tries = 3;

    /**
     * Timeout per percobaan (detik).
     *
     * PDF generation biasanya < 5 detik; beri margin.
     */
    public int $timeout = 60;

    /**
     * @param  int  $tripId  ID Trip yang akan di-generate E-Ticket-nya.
     *                       Disimpan sebagai ID (bukan model) agar payload
     *                       queue tetap kecil dan tahan terhadap perubahan
     *                       schema.
     */
    public function __construct(
        public readonly int $tripId,
    ) {}

    /**
     * Tentukan nama queue tempat job ini akan dijalankan.
     *
     * Ini adalah cara yang benar di Laravel 11+ untuk mengatur queue,
     * bukan dengan mendeklarasikan properti `$queue` secara manual.
     */
    public function viaQueue(): string
    {
        return 'e-ticket';
    }

    /**
     * Backoff antar percobaan (detik).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Eksekusi job.
     */
    public function handle(ETicketService $eTicketService): void
    {
        $trip = Trip::find($this->tripId);

        if ($trip === null) {
            Log::warning('GenerateETicketJob: Trip tidak ditemukan, dilewati', [
                'trip_id' => $this->tripId,
            ]);

            return;
        }

        $eTicketService->generateForTrip($trip);

        Log::info('GenerateETicketJob: E-Ticket berhasil dibuat', [
            'trip_id' => $trip->id,
            'trip_kode' => $trip->kode,
            'booking_id' => $trip->booking_id,
        ]);
    }

    /**
     * Tangani kegagalan permanen (setelah semua retry habis).
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateETicketJob: gagal permanen setelah retry', [
            'trip_id' => $this->tripId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
