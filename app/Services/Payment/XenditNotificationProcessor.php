<?php

namespace App\Services\Payment;

use App\Data\Xendit\XenditWebhookEvent;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Events\BookingConfirmed;
use App\Jobs\DisburseToMitraJob;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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
        private readonly BookingPaymentLinkService $paymentLink,
    ) {}

    public function process(XenditWebhookEvent $event): void
    {
        if (str_starts_with($event->event, 'refund.')) {
            $this->processRefund($event);

            return;
        }

        if (str_starts_with($event->event, 'v3_payout.')
            || str_starts_with($event->event, 'payout.')) {
            $this->processPayout($event);

            return;
        }

        if (in_array($event->event, [
            'payment.failure',
            'payment_request.expiry',
            'payment_session.expired',
        ], true)) {
            $this->markUnsuccessful($event);

            return;
        }

        if ($event->event !== 'payment.capture') {
            Log::info('XenditNotificationProcessor: event tidak diproses', [
                'event' => $event->event,
            ]);

            return;
        }

        if ($event->status !== 'SUCCEEDED') {
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

            // Re-check amount dan reference di dalam lock agar perubahan
            // payload antara lookup dan transaksi tidak bisa ditipu.
            if ((int) $freshPayment->jumlah !== (int) $event->amount
                || $freshPayment->gateway_order_id !== $event->referenceId) {
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

    private function processRefund(XenditWebhookEvent $event): void
    {
        $referenceId = $event->referenceId;

        if ($referenceId === null || ! str_starts_with($referenceId, 'refund-')) {
            return;
        }

        $refund = Refund::find((int) substr($referenceId, 7));

        if ($refund === null || $refund->gateway_status !== 'PENDING') {
            return;
        }

        if ($event->event === 'refund.failed') {
            $refund->update([
                'gateway_status' => 'FAILED',
                'catatan' => trim(($refund->catatan ?? '')."\n[Xendit] Refund gagal: ".(string) data_get($event->payload, 'data.failure_code', '')),
            ]);

            AuditLogger::log('refund.gateway_failed', [
                'actor_id' => null,
                'actor_role' => 'system',
                'refund_id' => $refund->id,
                'booking_id' => $refund->booking_id,
            ]);

            return;
        }

        if ($event->event !== 'refund.succeeded') {
            return;
        }

        $completed = DB::transaction(function () use ($refund): bool {
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->first();

            if ($locked === null || $locked->gateway_status !== 'PENDING') {
                return false;
            }

            $locked->update([
                'status' => 'SUCCEEDED',
                'gateway_status' => 'SUCCESS',
            ]);

            Payment::create([
                'booking_id' => $locked->booking_id,
                'jumlah' => -$locked->amount,
                'metode' => PaymentMetode::XENDIT,
                'gateway' => PaymentGateway::XENDIT,
                'verified_at' => now(),
                'catatan' => 'Refund gateway Xendit berhasil.',
            ]);

            $booking = $locked->booking;

            if ($booking !== null) {
                $booking->update(['status' => BookingStatus::CANCELLED->value]);
            }

            AuditLogger::log('refund.approved', [
                'actor_id' => null,
                'actor_role' => 'system',
                'refund_id' => $locked->id,
                'booking_id' => $locked->booking_id,
                'amount' => (float) $locked->amount,
                'gateway' => PaymentGateway::XENDIT->value,
            ]);

            return true;
        });

        if (! $completed) {
            return;
        }

        $client = $refund->booking?->client;

        if ($client !== null) {
            try {
                $this->notificationDispatcher->send(
                    user: $client,
                    tipe: 'refund_succeeded',
                    judul: 'Refund Berhasil',
                    pesan: "Dana refund sebesar {$refund->amountRupiah()} untuk booking {$refund->booking->kode} telah diproses.",
                    data: ['refund_id' => $refund->id, 'booking_id' => $refund->booking_id],
                );
            } catch (Throwable $e) {
                Log::warning('XenditNotificationProcessor: gagal notifikasi refund', [
                    'refund_id' => $refund->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function processPayout(XenditWebhookEvent $event): void
    {
        // Disbursement legacy mengirim payout.succeeded / payout.failed;
        // payout v3 mengirim v3_payout.*. Perlakukan keduanya sama.
        $eventName = str_starts_with($event->event, 'payout.')
            ? 'v3_payout.'.substr($event->event, strlen('payout.'))
            : $event->event;
        $event = new XenditWebhookEvent(
            event: $eventName,
            paymentId: $event->paymentId,
            payoutId: $event->payoutId,
            referenceId: $event->referenceId,
            amount: $event->amount,
            status: $event->status,
            payload: $event->payload,
        );

        if ($event->payoutId === null) {
            Log::info('XenditNotificationProcessor: event payout tanpa payout_id diabaikan', [
                'event' => $event->event,
            ]);

            return;
        }

        $payment = Payment::query()
            ->where('gateway', PaymentGateway::XENDIT)
            ->whereNotNull('released_at')
            ->get()
            ->first(fn (Payment $item): bool => data_get($item->gateway_payload, 'payout_id') === $event->payoutId);

        if ($payment === null) {
            // Bisa terjadi bila webhook tiba sebelum job menyimpan payout_id.
            // Melempar membuat job gagal agar dedupe dihapus dan Xendit
            // mengirim ulang event setelah payout_id tersedia.
            throw new RuntimeException('Payout '.$event->payoutId.' belum tercatat di database.');
        }

        if ($payment->settled_at !== null) {
            return;
        }

        if ($event->event === 'v3_payout.succeeded') {
            DB::transaction(function () use ($payment, $event): void {
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
                    'payout_id' => $event->payoutId,
                ]);
            });

            return;
        }

        if (! in_array($event->event, [
            'v3_payout.failed',
            'v3_payout.reversed',
            'v3_payout.rejected',
        ], true)) {
            Log::info('XenditNotificationProcessor: event payout dicatat', [
                'event' => $event->event,
                'payout_id' => $event->payoutId,
            ]);

            return;
        }

        $failureCode = (string) data_get($event->payload, 'data.failure_code', '');
        $retryable = in_array($failureCode, [
            'INSUFFICIENT_BALANCE',
            'TEMPORARY_TRANSFER_ERROR',
            'REJECTED_BY_CHANNEL',
        ], true);

        DB::transaction(function () use ($payment, $failureCode, $retryable): void {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked === null || $locked->settled_at !== null) {
                return;
            }

            $payload = is_array($locked->gateway_payload) ? $locked->gateway_payload : [];
            $payload['payout_failure_code'] = $failureCode;

            if ($retryable) {
                $payload['payout_attempt'] = ((int) data_get($payload, 'payout_attempt', 1)) + 1;
                unset($payload['payout_id']);

                $locked->update([
                    'released_at' => null,
                    'gateway_status' => 'SUCCESS',
                    'gateway_payload' => $payload,
                ]);
            } else {
                $locked->update([
                    'gateway_status' => 'PAYOUT_FAILED',
                    'gateway_payload' => $payload,
                ]);
            }

            AuditLogger::log('payment.payout_failed', [
                'actor_id' => null,
                'actor_role' => 'system',
                'booking_id' => $locked->booking_id,
                'payout_id' => data_get($payload, 'payout_id'),
                'failure_code' => $failureCode,
                'retryable' => $retryable,
            ]);
        });

        if ($retryable) {
            DisburseToMitraJob::dispatch($payment->booking_id);
        }
    }

    private function markUnsuccessful(XenditWebhookEvent $event): void
    {
        if ($event->referenceId === null) {
            return;
        }

        $payment = Payment::query()
            ->where('gateway', PaymentGateway::XENDIT)
            ->where('gateway_order_id', $event->referenceId)
            ->where('gateway_status', 'pending')
            ->latest()
            ->first();

        if ($payment === null) {
            return;
        }

        $newStatus = $event->event === 'payment.failure' ? 'FAILED' : 'EXPIRED';
        $alasan = $newStatus === 'FAILED'
            ? 'Pembayaran gagal.'
            : 'Link pembayaran kedaluwarsa.';

        $updated = DB::transaction(function () use ($payment, $newStatus, $alasan): ?Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked === null || $locked->gateway_status !== 'pending') {
                return null;
            }

            $locked->update([
                'gateway_status' => $newStatus,
                'catatan' => trim(($locked->catatan ?? '')."\n[Xendit] {$alasan}"),
            ]);

            AuditLogger::log('payment.unsuccessful', [
                'actor_id' => null,
                'actor_role' => 'system',
                'booking_id' => $locked->booking_id,
                'payment_id' => $locked->id,
                'status' => $newStatus,
            ]);

            return $locked;
        });

        if ($updated === null) {
            return;
        }

        // Sesi sudah tidak bisa dipakai: cabut tautan WA agar client tidak
        // membuka halaman dengan tombol bayar ke URL mati.
        $this->paymentLink->revokeActive($updated->booking);

        $client = $updated->booking?->client;

        if ($client === null) {
            return;
        }

        try {
            $this->notificationDispatcher->send(
                user: $client,
                tipe: 'payment_rejected',
                judul: 'Pembayaran Belum Berhasil',
                pesan: "Pembayaran booking {$updated->booking->kode} {$alasan} Silakan coba lagi.",
                data: ['booking_id' => $updated->booking_id],
            );
        } catch (Throwable $e) {
            Log::warning('XenditNotificationProcessor: gagal notifikasi pembayaran gagal', [
                'payment_id' => $updated->id,
                'error' => $e->getMessage(),
            ]);
        }
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
