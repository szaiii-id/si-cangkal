<?php

namespace App\Services\Refund;

use App\Enums\BookingStatus;
use App\Enums\PaymentMetode;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundApprovalService
{
    public function __construct(
        private readonly RefundRepositoryInterface $refundRepository,
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    public function approve(Refund $refund, User $mitra, ?string $catatan): Refund
    {
        // Lock refund + booking di dalam transaksi: dua request approve
        // bersamaan harus diproses serial agar Payment negatif tidak ganda.
        $refund = DB::transaction(function () use ($refund, $mitra, $catatan): Refund {
            $locked = $this->lockRefund($refund);

            $this->ensureRefundPending($locked);
            $this->ensureOwnership($locked, $mitra);

            $booking = $this->lockBooking($locked);

            $amount = (float) $locked->amount;
            $noteLine = '[Approved] '.now()->format('Y-m-d H:i').($catatan !== null ? " - {$catatan}" : '');

            $locked->update([
                'status' => RefundStatus::SUCCEEDED->value,
                'catatan' => trim(($locked->catatan ?? '')."\n{$noteLine}"),
            ]);

            Payment::create([
                'booking_id' => $booking->id,
                'jumlah' => -$amount,
                'metode' => PaymentMetode::TRANSFER_MANUAL->value,
                'verified_by' => $mitra->id,
                'verified_at' => now(),
                'catatan' => "Refund manual oleh Mitra: {$mitra->name}. Alasan: {$locked->reason->label()}",
            ]);

            $booking->update([
                'status' => BookingStatus::CANCELLED->value,
                'catatan' => trim(($booking->catatan ?? '')."\n[Refund Approved] ".($catatan ?? 'Refund disetujui oleh Mitra')),
            ]);

            AuditLogger::log('refund.approved', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'refund_id' => $locked->id,
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'amount' => $amount,
                'reason' => $locked->reason->value,
            ]);

            return $locked->refresh()->load('booking.client');
        });

        // Notifikasi dikirim setelah commit: kegagalan FCM/IO tidak boleh
        // membatalkan refund yang sudah sah.
        $this->notifyRefundSucceededToClient($refund);

        return $refund;
    }

    public function reject(Refund $refund, User $mitra, string $alasan): Refund
    {
        $refund = DB::transaction(function () use ($refund, $mitra, $alasan): Refund {
            $locked = $this->lockRefund($refund);

            $this->ensureRefundPending($locked);
            $this->ensureOwnership($locked, $mitra);

            $noteLine = '[Rejected] '.now()->format('Y-m-d H:i')." - {$alasan}";

            $locked->update([
                'status' => RefundStatus::CANCELLED->value,
                'catatan' => trim(($locked->catatan ?? '')."\n{$noteLine}"),
            ]);

            AuditLogger::log('refund.rejected', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'refund_id' => $locked->id,
                'booking_id' => $locked->booking_id,
                'alasan' => $alasan,
            ]);

            return $locked->refresh()->load('booking.client');
        });

        $this->notifyRefundRejectedToClient($refund, $alasan);

        return $refund;
    }

    /**
     * Ambil refund dengan row lock. Guard status & kepemilikan wajib
     * dicek ulang SETELAH lock agar state yang dibaca adalah state final.
     */
    private function lockRefund(Refund $refund): Refund
    {
        return $this->refundRepository->findByIdForUpdate($refund->id) ?? $refund;
    }

    private function lockBooking(Refund $refund): Booking
    {
        $booking = $this->bookingRepository->findByIdForUpdate($refund->booking_id)
            ?? $refund->booking;

        if ($booking === null) {
            throw ValidationException::withMessages([
                'refund' => ['Booking terkait refund tidak ditemukan.'],
            ]);
        }

        return $booking;
    }

    private function ensureRefundPending(Refund $refund): void
    {
        if ($refund->status !== RefundStatus::PENDING) {
            throw ValidationException::withMessages([
                'refund' => ["Refund berstatus '{$refund->status->label()}' tidak bisa diproses."],
            ]);
        }
    }

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

    private function notifyRefundSucceededToClient(Refund $refund): void
    {
        $booking = $refund->booking;

        if ($booking === null) {
            return;
        }

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

    private function notifyRefundRejectedToClient(Refund $refund, string $alasan): void
    {
        $booking = $refund->booking;

        if ($booking === null) {
            return;
        }

        $client = $booking->client;

        if ($client === null) {
            return;
        }

        $this->notificationDispatcher->send(
            user: $client,
            tipe: 'refund_rejected',
            judul: 'Pengajuan Refund Ditolak',
            pesan: "Pengajuan refund untuk booking {$booking->kode} ditolak. Alasan: {$alasan}",
            data: [
                'refund_id' => $refund->id,
                'booking_id' => $refund->booking_id,
            ],
        );
    }
}
