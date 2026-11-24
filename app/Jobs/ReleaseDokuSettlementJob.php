<?php

namespace App\Jobs;

use App\Enums\PaymentGateway;
use App\Models\Payment;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Payment\Contracts\PaymentGatewayServiceInterface;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Melepas dana yang ditahan DOKU setelah trip dinyatakan selesai.
 *
 * Dana akan sampai ke rekening mitra H+1 sesuai response release DOKU.
 */
final class ReleaseDokuSettlementJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $bookingId,
    ) {}

    public function handle(
        PaymentGatewayServiceInterface $gateway,
        BookingRepositoryInterface $bookingRepository,
        NotificationDispatcherInterface $notificationDispatcher,
    ): void {
        $booking = $bookingRepository->findById($this->bookingId);

        if ($booking === null) {
            Log::warning('ReleaseDokuSettlementJob: booking tidak ditemukan', [
                'booking_id' => $this->bookingId,
            ]);

            return;
        }

        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::DOKU)
            ->whereNull('released_at')
            ->latest()
            ->first();

        if ($payment === null) {
            return;
        }

        try {
            $result = $gateway->release($booking);
        } catch (Throwable $e) {
            Log::error('ReleaseDokuSettlementJob: gagal melepas settlement', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $payment->update([
            'released_at' => now(),
            'settled_at' => Carbon::parse($result->settlementDate),
            'gateway_status' => 'SETTLED',
        ]);

        AuditLogger::log('payment.settlement_released', [
            'actor_id' => null,
            'actor_role' => 'system',
            'booking_id' => $booking->id,
            'booking_kode' => $booking->kode,
            'settlement_date' => $result->settlementDate,
            'settlement_amount' => $result->settlementAmount,
        ]);

        $mitra = $booking->mitra;

        if ($mitra !== null) {
            try {
                $notificationDispatcher->send(
                    user: $mitra,
                    tipe: 'settlement_released',
                    judul: 'Dana Perjalanan Diproses',
                    pesan: "Settlement booking {$booking->kode} telah dilepas dan akan masuk ke rekening Anda pada {$result->settlementDate}.",
                    data: ['booking_id' => $booking->id],
                );
            } catch (Throwable $e) {
                Log::warning('ReleaseDokuSettlementJob: gagal notifikasi mitra', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
