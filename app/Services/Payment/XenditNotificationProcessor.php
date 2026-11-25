<?php

namespace App\Services\Payment;

use App\Data\Xendit\XenditWebhookEvent;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Events\BookingConfirmed;
use App\Jobs\DisburseToMitraJob;
use App\Models\Booking;
use App\Models\Payment;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Memproses webhook Xendit ke state aplikasi.
 *
 * Perubahan state terjadi di dalam transaksi dengan row lock. Notifikasi
 * dan event dipicu setelah commit.
 */
final class XenditNotificationProcessor
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    public function process(XenditWebhookEvent $event): void
    {
        if (str_starts_with($event->event, 'v3_payout.')) {
            $this->processPayout($event);

            return;
        }

        if ($event->event !== 'payment.capture' || $event->status !== 'SUCCEEDED') {
            return;
        }

        if ($event->referenceId === null || $event->paymentId === null) {
            Log::warning('XenditNotificationProcessor: reference_id/payment_id tidak ada');

            return;
        }

        $payment = Payment::query()
            ->where('gateway', PaymentGateway::XENDIT)
            ->where('gateway_order_id', $event->referenceId)
            ->latest()
            ->first();

        if ($payment === null) {
            Log::warning('XenditNotificationProcessor: payment tidak ditemukan', [
                'reference_id' => $event->referenceId,
            ]);

            return;
        }

        if ((int) $payment->jumlah !== (int) $event->amount) {
            Log::warning('XenditNotificationProcessor: amount tidak cocok', [
                'payment_id' => $payment->id,
                'expected' => (int) $payment->jumlah,
                'received' => (int) $event->amount,
            ]);

            return;
        }

        if ($payment->gateway_status === 'SUCCESS') {
            return;
        }

        $booking = DB::transaction(function () use ($payment, $event): ?Booking {
            $locked = $this->bookingRepository->findByIdForUpdate($payment->booking_id);

            if ($locked === null
                || ! in_array($locked->status, [
                    BookingStatus::PENDING_PAYMENT,
                    BookingStatus::PAYMENT_VERIFIED,
                ], true)) {
                return null;
            }

            $freshPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if ($freshPayment === null || $freshPayment->gateway_status === 'SUCCESS') {
                return null;
            }

            $payload = is_array($freshPayment->gateway_payload)
                ? $freshPayment->gateway_payload
                : [];

            $freshPayment->update([
                'gateway_status' => 'SUCCESS',
                'paid_at' => now(),
                'verified_at' => now(),
                'gateway_payload' => [
                    ...$payload,
                    'payment_id' => $event->paymentId,
                ],
                'catatan' => trim(($freshPayment->catatan ?? '')."\n[Xendit] Pembayaran terverifikasi via webhook"),
            ]);

            $updated = $this->bookingRepository->update($locked, [
                'status' => BookingStatus::CONFIRMED->value,
                'payment_verified_at' => now(),
                'payment_reject_reason' => null,
            ]);

            AuditLogger::log('booking.payment_verified', [
                'actor_id' => null,
                'actor_role' => 'system',
                'booking_id' => $updated->id,
                'booking_kode' => $updated->kode,
                'gateway' => PaymentGateway::XENDIT->value,
                'total_harga' => (float) $freshPayment->jumlah,
            ]);

            return $updated;
        });

        if ($booking === null) {
            return;
        }

        $this->notifyClient($booking);

        try {
            BookingConfirmed::dispatch($booking->refresh(), (int) $booking->mitra_id);
        } catch (Throwable $e) {
            Log::error('XenditNotificationProcessor: dispatch BookingConfirmed gagal', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (config('payment.xendit.instant_payout_enabled')) {
            DisburseToMitraJob::dispatch($booking->id);
        }
    }

    private function processPayout(XenditWebhookEvent $event): void
    {
        if ($event->event !== 'v3_payout.succeeded' || $event->payoutId === null) {
            Log::info('XenditNotificationProcessor: event payout diabaikan', [
                'event' => $event->event,
                'payout_id' => $event->payoutId,
            ]);

            return;
        }

        $payment = Payment::query()
            ->where('gateway', PaymentGateway::XENDIT)
            ->whereNotNull('released_at')
            ->get()
            ->first(fn (Payment $item): bool => data_get($item->gateway_payload, 'payout_id') === $event->payoutId);

        if ($payment === null || $payment->settled_at !== null) {
            return;
        }

        DB::transaction(function () use ($payment): void {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked === null || $locked->settled_at !== null) {
                return;
            }

            $locked->update([
                'settled_at' => now(),
                'gateway_status' => 'SETTLED',
            ]);

            AuditLogger::log('payment.payout_settled', [
                'actor_id' => null,
                'actor_role' => 'system',
                'booking_id' => $locked->booking_id,
                'payout_id' => data_get($locked->gateway_payload, 'payout_id'),
            ]);
        });
    }

    private function notifyClient(Booking $booking): void
    {
        $client = $booking->client;

        if ($client === null) {
            return;
        }

        try {
            $this->notificationDispatcher->send(
                user: $client,
                tipe: 'payment_verified',
                judul: 'Pembayaran Diterima',
                pesan: "Pembayaran booking {$booking->kode} telah diterima. E-Ticket sedang disiapkan.",
                data: ['booking_id' => $booking->id],
            );
        } catch (Throwable $e) {
            Log::warning('XenditNotificationProcessor: gagal notifikasi client', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
