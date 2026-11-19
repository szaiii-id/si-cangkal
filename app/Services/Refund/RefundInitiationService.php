<?php

namespace App\Services\Refund;

use App\Enums\BookingStatus;
use App\Enums\PaymentMetode;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service pembuatan refund baru oleh mitra.
 *
 * Dipakai saat mitra memutuskan mengembalikan dana booking tanpa ada
 * permintaan refund tertunda dari client. Refund langsung berstatus
 * SUCCEEDED karena mitra memprosesnya secara manual (transfer balik).
 *
 * Berbeda dari RefundApprovalService yang memproses refund
 * berstatus PENDING (hasil pengajuan refund).
 *
 * Tanggung jawab:
 * - Validasi kelayakan booking (status, kepemilikan, jumlah).
 * - Membuat record Refund (status SUCCEEDED).
 * - Mencatat Payment negatif sebagai jejak pengembalian dana.
 * - Mengubah status booking menjadi CANCELLED.
 * - Mengirim notifikasi ke client (in-app + push FCM).
 *
 * Semua transisi state menulis audit log (S-16). Notifikasi dikirim
 * di luar transaksi DB agar kegagalan FCM tidak me-rollback refund.
 */
final class RefundInitiationService
{
    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    /**
     * Buat refund baru (langsung SUCCEEDED).
     *
     * @throws ValidationException
     */
    public function initiate(
        Booking $booking,
        RefundReason $reason,
        ?float $amount,
        ?string $catatan,
        User $mitra,
    ): Refund {
        $this->ensureBookingOwnedByMitra($booking, $mitra);
        $this->ensureBookingStatusEligible($booking);
        $this->ensureNoActiveRefund($booking);

        $paidAmount = $this->resolvePaidAmount($booking);
        $refundAmount = $this->resolveRefundAmount($amount, $paidAmount);

        $refund = DB::transaction(function () use (
            $booking,
            $refundAmount,
            $reason,
            $catatan,
            $mitra
        ): Refund {
            $refund = Refund::create([
                'booking_id' => $booking->id,
                'mitra_id' => $booking->mitra_id,
                'amount' => $refundAmount,
                'currency' => 'IDR',
                'reason' => $reason->value,
                'status' => RefundStatus::SUCCEEDED->value,
                'initiated_by_mitra' => $mitra->id,
                'catatan' => $catatan,
            ]);

            Payment::create([
                'booking_id' => $booking->id,
                'jumlah' => -$refundAmount,
                'metode' => PaymentMetode::TRANSFER_MANUAL->value,
                'verified_by' => $mitra->id,
                'verified_at' => now(),
                'catatan' => "Refund manual oleh Mitra: {$mitra->name}. Alasan: {$reason->label()}",
            ]);

            $booking->update([
                'status' => BookingStatus::CANCELLED->value,
                'catatan' => trim(($booking->catatan ?? '')."\n[Refund] ".($catatan ?? 'Refund diproses oleh Mitra')),
            ]);

            AuditLogger::log('refund.initiated', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'refund_id' => $refund->id,
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'amount' => $refundAmount,
                'reason' => $reason->value,
            ]);

            return $refund->load('booking.client');
        });

        $this->notifyRefundSucceededToClient($refund);

        return $refund;
    }

    // ==========================================
    // VALIDATION HELPERS
    // ==========================================

    /**
     * @throws ValidationException
     */
    private function ensureBookingOwnedByMitra(Booking $booking, User $mitra): void
    {
        if ($booking->mitra_id !== $mitra->id) {
            throw ValidationException::withMessages([
                'booking' => ['Anda tidak memiliki akses untuk me-refund booking ini.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureBookingStatusEligible(Booking $booking): void
    {
        $eligible = [
            BookingStatus::PAYMENT_VERIFIED,
            BookingStatus::CONFIRMED,
            BookingStatus::IN_PROGRESS,
        ];

        if (! in_array($booking->status, $eligible, true)) {
            throw ValidationException::withMessages([
                'booking' => ["Booking berstatus '{$booking->status->label()}' tidak bisa di-refund."],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureNoActiveRefund(Booking $booking): void
    {
        $existing = Refund::active()->where('booking_id', $booking->id)->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'refund' => ["Refund untuk booking ini sudah ada (status: {$existing->status->label()})."],
            ]);
        }
    }

    private function resolvePaidAmount(Booking $booking): float
    {
        $paid = (float) ($booking->payments()->orderByDesc('verified_at')->value('jumlah') ?? 0.0);

        return $paid > 0 ? $paid : (float) $booking->total_harga;
    }

    /**
     * @throws ValidationException
     */
    private function resolveRefundAmount(?float $amount, float $paidAmount): float
    {
        $refundAmount = $amount ?? $paidAmount;

        if ($refundAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Jumlah refund harus lebih dari 0.'],
            ]);
        }

        if ($refundAmount > $paidAmount) {
            throw ValidationException::withMessages([
                'amount' => ['Jumlah refund melebihi jumlah yang dibayar.'],
            ]);
        }

        return $refundAmount;
    }

    // ==========================================
    // NOTIFICATION
    // ==========================================

    private function notifyRefundSucceededToClient(Refund $refund): void
    {
        $booking = $refund->booking;
        $client = $booking->client;

        if ($client === null) {
            return;
        }

        $this->notificationDispatcher->send(
            user: $client,
            tipe: 'refund_succeeded',
            judul: 'Refund Berhasil',
            pesan: "Dana refund sebesar {$refund->amountRupiah()} untuk booking {$booking->kode} telah diproses. Silakan cek rekening Anda.",
            data: [
                'refund_id' => $refund->id,
                'booking_id' => $refund->booking_id,
            ],
        );
    }
}
