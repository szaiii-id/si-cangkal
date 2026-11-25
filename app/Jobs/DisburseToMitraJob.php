<?php

namespace App\Jobs;

use App\Enums\PaymentGateway;
use App\Models\Payment;
use App\Models\Refund;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;
use App\Support\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim dana booking ke rekening bank mitra lewat Xendit Payout.
 *
 * Idempotensi dijaga oleh idempotency-key yang terikat kode booking dan
 * guard payout_id di database. Job aman di-retry bila saldo belum cukup.
 */
final class DisburseToMitraJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(
        public readonly int $bookingId,
    ) {}

    public function handle(
        XenditPaymentServiceInterface $gateway,
        BookingRepositoryInterface $bookingRepository,
        NotificationDispatcherInterface $notificationDispatcher,
    ): void {
        $booking = $bookingRepository->findById($this->bookingId);

        if ($booking === null) {
            Log::warning('DisburseToMitraJob: booking tidak ditemukan', [
                'booking_id' => $this->bookingId,
            ]);

            return;
        }

        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::XENDIT)
            ->where('gateway_status', 'SUCCESS')
            ->whereNull('released_at')
            ->latest()
            ->first();

        if ($payment === null) {
            return;
        }

        $payload = is_array($payment->gateway_payload) ? $payment->gateway_payload : [];

        if (filled(data_get($payload, 'payout_id'))) {
            return;
        }

        // Refund masih diproses: dana harus tetap tertahan.
        if (Refund::active()->where('booking_id', $booking->id)->exists()) {
            Log::info('DisburseToMitraJob: dilewati, refund masih aktif', [
                'booking_id' => $booking->id,
            ]);

            return;
        }

        try {
            $result = $gateway->payout($booking, $payment);
        } catch (Throwable $e) {
            Log::error('DisburseToMitraJob: gagal mengirim payout', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            // Idempotency-key Xendit meng-cache respons 4xx. Naikkan attempt
            // supaya retry berikutnya memakai key baru.
            $payment->update([
                'gateway_payload' => [
                    ...$payload,
                    'payout_attempt' => ((int) data_get($payload, 'payout_attempt', 1)) + 1,
                ],
            ]);

            throw $e;
        }

        $payment->update([
            'released_at' => now(),
            'gateway_status' => 'PAYOUT_REQUESTED',
            'gateway_payload' => [
                ...$payload,
                'payout_id' => $result->payoutId,
            ],
        ]);

        AuditLogger::log('payment.payout_requested', [
            'actor_id' => null,
            'actor_role' => 'system',
            'booking_id' => $booking->id,
            'booking_kode' => $booking->kode,
            'payout_id' => $result->payoutId,
            'status' => $result->status,
        ]);

        $mitra = $booking->mitra;

        if ($mitra !== null) {
            try {
                $notificationDispatcher->send(
                    user: $mitra,
                    tipe: 'settlement_released',
                    judul: 'Dana Perjalanan Diproses',
                    pesan: "Dana booking {$booking->kode} sedang dikirim ke rekening Anda.",
                    data: ['booking_id' => $booking->id],
                );
            } catch (Throwable $e) {
                Log::warning('DisburseToMitraJob: gagal notifikasi mitra', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
