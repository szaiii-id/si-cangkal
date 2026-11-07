<?php

namespace App\Services\Refund;

use App\Enums\BookingStatus;
use App\Enums\PaymentMetode;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Notification\NotificationDispatcher;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service pemrosesan refund yang sudah ada (approve/reject).
 *
 * Berbeda dari {@see RefundInitiationService} yang membuat refund baru.
 * Service ini hanya menangani refund berstatus `PENDING` (hasil
 * pengajuan refund oleh client atau mitra).
 *
 * Transisi state:
 * - `PENDING` -> `SUCCEEDED` via approve()  (dana dikembalikan).
 * - `PENDING` -> `CANCELLED` via reject()   (dana tidak dikembalikan).
 *
 * Semua transisi state menulis audit log (S-16). Notifikasi dikirim
 * di luar transaksi DB agar kegagalan FCM tidak me-rollback refund.
 */
final class RefundApprovalService
{
    public function __construct(
        private readonly NotificationDispatcher $notificationDispatcher,
    ) {}

    /**
     * Setujui refund (PENDING -> SUCCEEDED).
     *
     * Aksi:
     * - Update status refund menjadi `SUCCEEDED`.
     * - Buat `Payment` negatif sebagai jejak pengembalian dana.
     * - Update status booking menjadi `CANCELLED`.
     * - Append catatan approval ke refund & booking.
     * - Notifikasi client.
     *
     * @throws ValidationException
     */
    public function approve(Refund $refund, User $mitra, ?string $catatan): Refund
    {
        $this->ensureRefundPending($refund);
        $this->ensureOwnership($refund, $mitra);

        $booking = $this->resolveBooking($refund);
        $amount = (float) $refund->amount;

        $refund = DB::transaction(function () use (
            $refund,
            $booking,
            $mitra,
            $catatan,
            $amount
        ): Refund {
            $noteLine = '[Approved] '.now()->format('Y-m-d H:i').($catatan !== null ? " - {$catatan}" : '');

            $refund->update([
                'status' => RefundStatus::SUCCEEDED->value,
                'catatan' => trim(($refund->catatan ?? '')."\n{$noteLine}"),
            ]);

            Payment::create([
                'booking_id' => $booking->id,
                'jumlah' => -$amount,
                'metode' => PaymentMetode::TRANSFER_MANUAL->value,
                'verified_by' => $mitra->id,
                'verified_at' => now(),
                'catatan' => "Refund manual oleh Mitra: {$mitra->name}. Alasan: {$refund->reason->label()}",
            ]);

            $booking->update([
                'status' => BookingStatus::CANCELLED->value,
                'catatan' => trim(($booking->catatan ?? '')."\n[Refund Approved] ".($catatan ?? 'Refund disetujui oleh Mitra')),
            ]);

            AuditLogger::log('refund.approved', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'refund_id' => $refund->id,
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'amount' => $amount,
                'reason' => $refund->reason->value,
            ]);

            return $refund->refresh()->load('booking.client');
        });

        $this->notifyRefundSucceededToClient($refund);

        return $refund;
    }

    /**
     * Tolak refund (PENDING -> CANCELLED).
     *
     * Aksi:
     * - Update status refund menjadi `CANCELLED`.
     * - Append catatan penolakan ke refund.
     * - Tidak ada `Payment` negatif (dana tidak dikembalikan).
     * - Status booking TIDAK diubah.
     * - Notifikasi client dengan alasan penolakan.
     *
     * @throws ValidationException
     */
    public function reject(Refund $refund, User $mitra, string $alasan): Refund
    {
        $this->ensureRefundPending($refund);
        $this->ensureOwnership($refund, $mitra);

        $noteLine = '[Rejected] '.now()->format('Y-m-d H:i')." - {$alasan}";

        $refund = DB::transaction(function () use ($refund, $mitra, $noteLine, $alasan): Refund {
            $refund->update([
                'status' => RefundStatus::CANCELLED->value,
                'catatan' => trim(($refund->catatan ?? '')."\n{$noteLine}"),
            ]);

            AuditLogger::log('refund.rejected', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'refund_id' => $refund->id,
                'booking_id' => $refund->booking_id,
                'alasan' => $alasan,
            ]);

            return $refund->refresh()->load('booking.client');
        });

        $this->notifyRefundRejectedToClient($refund, $alasan);

        return $refund;
    }

    // ==========================================
    // VALIDATION HELPERS
    // ==========================================

    /**
     * @throws ValidationException
     */
    private function ensureRefundPending(Refund $refund): void
    {
        if ($refund->status !== RefundStatus::PENDING) {
            throw ValidationException::withMessages([
                'refund' => ["Refund berstatus '{$refund->status->label()}' tidak bisa diproses."],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureOwnership(Refund $refund, User $mitra): void
    {
        if ($refund->mitra_id !== $mitra->id) {
            throw ValidationException::withMessages([
                'refund' => ['Anda tidak memiliki akses untuk memproses refund ini.'],
            ]);
        }

        $booking = $refund->booking;

        if ($booking === null) {
            throw ValidationException::withMessages([
                'refund' => ['Booking terkait refund tidak ditemukan.'],
            ]);
        }

        if ($booking->mitra_id !== $mitra->id) {
            throw ValidationException::withMessages([
                'refund' => ['Anda tidak memiliki akses ke booking refund ini.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function resolveBooking(Refund $refund): Booking
    {
        $booking = $refund->booking;

        if ($booking === null) {
            throw ValidationException::withMessages([
                'refund' => ['Booking terkait refund tidak ditemukan.'],
            ]);
        }

        return $booking;
    }

    // ==========================================
    // NOTIFICATION
    // ==========================================

    private function notifyRefundSucceededToClient(Refund $refund): void
    {
        $client = $refund->booking?->client;

        if ($client === null) {
            return;
        }

        $this->notificationDispatcher->send(
            user: $client,
            tipe: 'refund_succeeded',
            judul: 'Refund Berhasil',
            pesan: "Dana refund sebesar {$refund->amountRupiah()} untuk booking {$refund->booking?->kode} telah diproses. Silakan cek rekening Anda.",
            data: [
                'refund_id' => $refund->id,
                'booking_id' => $refund->booking_id,
            ],
        );
    }

    private function notifyRefundRejectedToClient(Refund $refund, string $alasan): void
    {
        $client = $refund->booking?->client;

        if ($client === null) {
            return;
        }

        $this->notificationDispatcher->send(
            user: $client,
            tipe: 'refund_rejected',
            judul: 'Pengajuan Refund Ditolak',
            pesan: "Pengajuan refund untuk booking {$refund->booking?->kode} ditolak. Alasan: {$alasan}",
            data: [
                'refund_id' => $refund->id,
                'booking_id' => $refund->booking_id,
            ],
        );
    }
}