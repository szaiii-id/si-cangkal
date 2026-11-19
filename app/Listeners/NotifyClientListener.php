<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener: handle event BookingConfirmed.
 *
 *   SEBELUMNYA: Mengirim email via `BookingConfirmedNotification`.
 * SEKARANG: Email DIHAPUS karena notifikasi ke Client sudah ditangani
 * oleh `BookingPaymentService::notifyClientPaymentConfirmed()` via
 * `NotificationDispatcher` (In-App + Push FCM).
 *
 * Listener ini tetap dipertahankan (tidak dihapus) agar:
 * - Tidak ada breaking change di EventServiceProvider.
 * - Bisa dipakai lagi nanti jika butuh channel tambahan (SMS/WA).
 */
final class NotifyClientListener implements ShouldQueue
{
    public ?string $connection = null;

    public string $queue = 'default';

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60];
    }

    /**
     * Tangani event BookingConfirmed.
     *
     *  Notifikasi Client (In-App + FCM) sudah dikirim oleh
     *    `BookingPaymentService::notifyClientPaymentConfirmed()`.
     *    Jadi listener ini TIDAK perlu mengirim email lagi.
     */
    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        Log::info('NotifyClientListener: event diterima (email dinonaktifkan, FCM sudah handle)', [
            'booking_id' => $booking->id,
            'booking_kode' => $booking->kode,
        ]);
    }
}
