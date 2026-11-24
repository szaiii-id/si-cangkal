<?php

namespace App\Services\Payment;

use App\Data\Doku\DokuNotification;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Payment;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Memproses notifikasi pembayaran DOKU ke state aplikasi.
 *
 * Semua perubahan state terjadi di dalam transaksi dengan row lock.
 * Notifikasi dan event dipicu setelah commit supaya kegagalan I/O tidak
 * membatalkan pembayaran yang sudah sah.
 */
final class DokuNotificationProcessor
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    public function process(DokuNotification $notification): void
    {
        $payment = Payment::query()
            ->where('gateway', PaymentGateway::DOKU)
            ->where('gateway_order_id', $notification->invoiceNumber)
            ->first();

        if ($payment === null) {
            Log::warning('DokuNotificationProcessor: payment tidak ditemukan', [
                'invoice_number' => $notification->invoiceNumber,
                'request_id' => $notification->requestId,
            ]);

            return;
        }

        if ((int) $payment->jumlah !== $notification->amount) {
            Log::warning('DokuNotificationProcessor: amount tidak cocok', [
                'booking_id' => $payment->booking_id,
                'expected' => (int) $payment->jumlah,
                'received' => $notification->amount,
            ]);

            return;
        }

        if ($notification->isFailed()) {
            // Checkout sengaja mengabaikan FAILED: user bisa mengganti
            // metode pembayaran tanpa perlu booking dibatalkan.
            Log::info('DokuNotificationProcessor: status FAILED diabaikan', [
                'booking_id' => $payment->booking_id,
                'request_id' => $notification->requestId,
            ]);

            return;
        }

        if (! $notification->isSuccess()) {
            Log::warning('DokuNotificationProcessor: status tidak dikenal', [
                'booking_id' => $payment->booking_id,
                'status' => $notification->status,
            ]);

            return;
        }

        if ($payment->gateway_status === 'SUCCESS') {
            return;
        }

        $booking = DB::transaction(function () use ($payment): ?Booking {
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

            $freshPayment->update([
                'gateway_status' => 'SUCCESS',
                'paid_at' => now(),
                'verified_at' => now(),
                'catatan' => trim(($freshPayment->catatan ?? '')."\n[Doku] Pembayaran terverifikasi via webhook"),
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
                'gateway' => PaymentGateway::DOKU->value,
                'total_harga' => (float) $freshPayment->jumlah,
            ]);

            return $updated;
        });

        if ($booking === null) {
            return;
        }

        $this->notifyAndDispatch($booking);
    }

    private function notifyAndDispatch(Booking $booking): void
    {
        $client = $booking->client;

        if ($client !== null) {
            try {
                $this->notificationDispatcher->send(
                    user: $client,
                    tipe: 'payment_verified',
                    judul: 'Pembayaran Diterima',
                    pesan: "Pembayaran booking {$booking->kode} telah diterima. E-Ticket sedang disiapkan.",
                    data: ['booking_id' => $booking->id],
                );
            } catch (Throwable $e) {
                Log::warning('DokuNotificationProcessor: gagal notifikasi client', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            BookingConfirmed::dispatch($booking->refresh(), (int) $booking->mitra_id);
        } catch (Throwable $e) {
            Log::error('DokuNotificationProcessor: gagal dispatch BookingConfirmed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
