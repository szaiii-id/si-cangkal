<?php

namespace App\Services\Refund;

use App\Data\Xendit\XenditRefundResult;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundApprovalService
{
    public function __construct(
        private readonly RefundRepositoryInterface $refundRepository,
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly NotificationDispatcherInterface $notificationDispatcher,
        private readonly XenditPaymentServiceInterface $paymentGateway,
        private readonly RefundInitiationService $refundInitiation,
    ) {}

    public function approve(Refund $refund, User $mitra, ?string $catatan): Refund
    {
        // Guard awal mencegah HTTP call yang tidak perlu untuk refund yang
        // sudah diproses. Guard ulang tetap ada di dalam transaksi.
        $this->ensureRefundPending($refund);

        $gatewayPayment = Payment::query()
            ->where('booking_id', $refund->booking_id)
            ->where('gateway', PaymentGateway::XENDIT)
            ->latest()
            ->first();

        // Dana sudah cair ke rekening mitra: gateway tidak bisa menarik dana
        // kembali. Selesaikan refund yang sudah ada lewat jalur manual.
        if ($gatewayPayment !== null
            && filled(data_get($gatewayPayment->gateway_payload, 'payout_id'))
            && $gatewayPayment->settled_at !== null) {
            return $this->refundInitiation->completeManuallyAfterPayout(
                $refund,
                $mitra,
                $catatan,
            );
        }

        $gatewayResult = null;

        if ($gatewayPayment !== null) {
            // Tandai niat sebelum HTTP call. Bila proses mati di tengah,
            // refund tetap terlihat oleh rekonsiliasi.
            $this->markRefundIntent($refund);

            // HTTP call dilakukan DI LUAR transaksi agar lock booking tidak
            // lama ditahan.
            try {
                $gatewayResult = $this->paymentGateway->refund($gatewayPayment, $refund);
            } catch (Throwable $e) {
                Log::warning('RefundApprovalService: gagal refund gateway', [
                    'refund_id' => $refund->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Simpan id refund dari Xendit SEBELUM transaksi utama. Kalau
            // transaksi utama rollback, id tetap ada sehingga reconciliation
            // bisa menyelesaikan refund yang sudah benar-benar diproses.
            $this->persistGatewayResult($refund, $gatewayResult);
        }

        // Xendit mengembalikan PENDING bila refund masih diproses issuer;
        // hasil akhirnya menyusul lewat webhook refund.succeeded/failed.
        $gatewayStatus = $gatewayResult?->status;
        $gatewaySuccess = in_array($gatewayStatus, ['SUCCEEDED', 'PENDING'], true);
        $gatewayPending = $gatewayStatus === 'PENDING';
        $gatewayMessage = $gatewayStatus ?? 'tidak ada respons';
        $gatewayRefundNo = $gatewayResult?->refundId;

        $refund = DB::transaction(function () use (
            $refund,
            $mitra,
            $catatan,
            $gatewayPayment,
            $gatewaySuccess,
            $gatewayPending,
            $gatewayMessage,
            $gatewayRefundNo,
        ): Refund {
            $locked = $this->lockRefund($refund);

            $this->ensureRefundPending($locked);
            $this->ensureOwnership($locked, $mitra);

            $booking = $this->lockBooking($locked);

            $amount = (float) $locked->amount;
            $noteLine = '[Approved] '.now()->format('Y-m-d H:i').($catatan !== null ? " - {$catatan}" : '');

            // Gateway menolak (issuer tidak mendukung / limit refund).
            // Status sengaja tetap PENDING agar mitra bisa retry manual.
            if ($gatewayPayment !== null && ! $gatewaySuccess) {
                $locked->update([
                    'gateway_status' => 'FAILED',
                    'catatan' => trim(($locked->catatan ?? '')."\n[Xendit] Refund gateway gagal: {$gatewayMessage}"),
                ]);

                AuditLogger::log('refund.gateway_failed', [
                    'actor_id' => $mitra->id,
                    'actor_role' => 'mitra',
                    'refund_id' => $locked->id,
                    'booking_id' => $booking->id,
                    'message' => $gatewayMessage,
                ]);

                return $locked->refresh()->load('booking.client');
            }

            $locked->update([
                'status' => $gatewayPending
                    ? RefundStatus::PENDING->value
                    : RefundStatus::SUCCEEDED->value,
                'gateway_refund_id' => $gatewaySuccess ? $gatewayRefundNo : null,
                'gateway_status' => $gatewayPending ? 'PENDING' : ($gatewaySuccess ? 'SUCCESS' : null),
                'catatan' => trim(($locked->catatan ?? '')."\n{$noteLine}"),
            ]);

            if ($gatewayPending) {
                return $locked->refresh()->load('booking.client');
            }

            Payment::create([
                'booking_id' => $booking->id,
                'jumlah' => -$amount,
                'metode' => $gatewaySuccess ? PaymentMetode::XENDIT : PaymentMetode::TRANSFER_MANUAL,
                'gateway' => $gatewaySuccess ? PaymentGateway::XENDIT : PaymentGateway::MANUAL,
                'verified_by' => $mitra->id,
                'verified_at' => now(),
                'catatan' => "Refund oleh Mitra: {$mitra->name}. Alasan: {$locked->reason->label()}",
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
                'gateway' => $gatewaySuccess ? PaymentGateway::XENDIT->value : PaymentGateway::MANUAL->value,
            ]);

            return $locked->refresh()->load('booking.client');
        });

        // Notifikasi sukses hanya untuk refund yang benar-benar berhasil.
        if ($refund->status === RefundStatus::SUCCEEDED) {
            $this->notifyRefundSucceededToClient($refund);
        }

        return $refund;
    }

    /**
     * Catat niat refund sebelum memanggil gateway.
     *
     * Hanya refund PENDING tanpa gateway_refund_id yang ditandai, supaya
     * retry tidak mengubah status refund yang sudah diproses.
     */
    private function markRefundIntent(Refund $refund): void
    {
        Refund::query()
            ->whereKey($refund->id)
            ->where('status', RefundStatus::PENDING->value)
            ->whereNull('gateway_refund_id')
            ->update(['gateway_status' => 'INTENT']);
    }

    /**
     * Simpan hasil gateway di luar transaksi utama.
     *
     * Status sengaja dibiarkan PENDING: transaksi utama/webhook yang akan
     * menentukan SUCCESS/FAILED. Yang penting di sini adalah id refund
     * tidak ikut ter-rollback bersama transaksi bisnis.
     */
    private function persistGatewayResult(Refund $refund, ?XenditRefundResult $result): void
    {
        $query = Refund::query()
            ->whereKey($refund->id)
            ->where('status', RefundStatus::PENDING->value)
            ->whereNull('gateway_refund_id');

        if ($result === null || $result->refundId === '') {
            $query->update(['gateway_status' => 'FAILED']);

            return;
        }

        $query->update([
            'gateway_refund_id' => $result->refundId,
            'gateway_status' => 'PENDING',
        ]);
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
