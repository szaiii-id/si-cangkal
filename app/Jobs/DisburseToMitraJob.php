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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim dana booking ke rekening bank mitra lewat Xendit Payout.
 *
 * Idempotensi dijaga oleh idempotency-key yang terikat kode booking dan
 * guard payout_id di database. Job aman di-retry bila saldo belum cukup.
 */
final class DisburseToMitraJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120, 300];

    public int $timeout = 120;

    public function __construct(
        public readonly int $bookingId,
    ) {}

    /**
     * Satu booking hanya boleh punya satu payout aktif.
     */
    public function uniqueId(): string
    {
        return 'payout:booking:'.$this->bookingId;
    }

    public function uniqueFor(): int
    {
        return 300;
    }

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

        $attempt = (int) data_get($payload, 'payout_attempt', 1);

        // Claim atomik: tandai released_at SEBELUM memanggil gateway. Job
        // kedua yang masuk saat gateway masih dipanggil akan melihat claim
        // ini dan berhenti. lockForUpdate + cek ulang menutup race.
        $claimed = DB::transaction(function () use ($payment, $attempt): bool {
            /** @var Payment|null $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked === null || $locked->released_at !== null) {
                return false;
            }

            $lockedPayload = is_array($locked->gateway_payload) ? $locked->gateway_payload : [];

            if (filled(data_get($lockedPayload, 'payout_id'))) {
                return false;
            }

            $locked->update([
                'released_at' => now(),
                'gateway_status' => 'PAYOUT_REQUESTED',
                'gateway_payload' => [
                    ...$lockedPayload,
                    'payout_attempt' => $attempt,
                ],
            ]);

            return true;
        });

        if (! $claimed) {
            return;
        }

        try {
            $result = $gateway->payout($booking, $payment);
        } catch (Throwable $e) {
            // Lepaskan claim supaya retry berikutnya bisa mencoba lagi.
            // Idempotency-key Xendit meng-cache respons 4xx, jadi attempt
            // dinaikkan agar retry memakai key baru.
            $fresh = $payment->fresh();

            if ($fresh !== null) {
                $freshPayload = is_array($fresh->gateway_payload) ? $fresh->gateway_payload : [];

                $fresh->update([
                    'released_at' => null,
                    'gateway_status' => 'SUCCESS',
                    'gateway_payload' => [
                        ...$freshPayload,
                        'payout_attempt' => $attempt + 1,
                    ],
                ]);
            }

            Log::error('DisburseToMitraJob: gagal mengirim payout', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $fresh = $payment->fresh();

        if ($fresh === null) {
            return;
        }

        $freshPayload = is_array($fresh->gateway_payload) ? $fresh->gateway_payload : [];

        $fresh->update([
            'gateway_payload' => [
                ...$freshPayload,
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

    /**
     * Jejak saat retry habis. Alert realtime menyusul di T12 lewat
     * Queue::failing(); di sini cukup audit log + error log.
     */
    public function failed(Throwable $exception): void
    {
        AuditLogger::system('payment.payout_failed', [
            'booking_id' => $this->bookingId,
            'error' => $exception->getMessage(),
        ]);

        Log::error('DisburseToMitraJob: payout gagal permanen', [
            'booking_id' => $this->bookingId,
            'error' => $exception->getMessage(),
        ]);
    }
}
