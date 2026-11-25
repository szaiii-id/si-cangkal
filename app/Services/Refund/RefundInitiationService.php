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
use App\Services\Booking\BookingPaymentService;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Support\AuditLogger;
use App\Support\UploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service pembuatan refund baru oleh mitra.
 *
 * Dipakai saat mitra memutuskan mengembalikan dana booking tanpa ada
 * permintaan refund tertunda dari client. Refund dibuat berstatus
 * AWAITING_PROOF dan baru SUCCEEDED setelah bukti transfer diunggah.
 *
 * Berbeda dari RefundApprovalService yang memproses refund
 * berstatus PENDING (hasil pengajuan refund).
 *
 * Tanggung jawab:
 * - Validasi kelayakan booking (status, kepemilikan, jumlah).
 * - Membuat record Refund (status AWAITING_PROOF).
 * - Setelah bukti diunggah: Payment negatif, booking CANCELLED, notifikasi client.
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
     * Buat refund manual. Status awal AWAITING_PROOF: belum ada uang yang
     * dianggap kembali sebelum mitra mengunggah bukti transfer.
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
                'status' => RefundStatus::AWAITING_PROOF->value,
                'initiated_by_mitra' => $mitra->id,
                'catatan' => trim(($catatan ?? '')."\n[Refund] Menunggu bukti transfer dari Mitra."),
            ]);

            AuditLogger::log('refund.initiated', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'refund_id' => $refund->id,
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'amount' => $refundAmount,
                'reason' => $reason->value,
                'status' => RefundStatus::AWAITING_PROOF->value,
            ]);

            return $refund->load('booking.client');
        });

        // Belum ada notifikasi sukses: uang belum terbukti dikembalikan.
        return $refund;
    }

    /**
     * Menyelesaikan refund yang sudah PENDING setelah dana cair ke mitra.
     *
     * Dipakai bila pengajuan refund dibuat sebelum payout selesai. Gateway
     * tidak bisa menarik dana dari rekening mitra, jadi refund diselesaikan
     * lewat jalur manual dengan tetap memakai record refund yang ada.
     */
    public function completeManuallyAfterPayout(
        Refund $refund,
        User $mitra,
        ?string $catatan,
    ): Refund {
        $refund = DB::transaction(function () use ($refund, $mitra, $catatan): Refund {
            $locked = Refund::query()
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== RefundStatus::PENDING) {
                throw ValidationException::withMessages([
                    'refund' => ["Refund berstatus '{$locked->status->label()}' tidak bisa diproses."],
                ]);
            }

            $booking = $locked->booking;

            if ($booking === null) {
                throw ValidationException::withMessages([
                    'booking' => ['Booking terkait refund tidak ditemukan.'],
                ]);
            }

            $noteLine = '[Refund Manual] '.now()->format('Y-m-d H:i')
                .($catatan !== null ? " - {$catatan}" : '')
                .' — menunggu bukti transfer.';

            // Dana sudah cair ke mitra, tetapi pengembalian ke client belum
            // terbukti. Status menunggu bukti diproses lewat uploadProof().
            $locked->update([
                'status' => RefundStatus::AWAITING_PROOF->value,
                'gateway_status' => null,
                'catatan' => trim(($locked->catatan ?? '')."\n{$noteLine}"),
            ]);

            AuditLogger::log('refund.awaiting_proof_after_payout', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'refund_id' => $locked->id,
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'amount' => (float) $locked->amount,
            ]);

            return $locked->load('booking.client');
        });

        return $refund;
    }

    /**
     * Mitra mengunggah bukti transfer; refund menjadi SUCCEEDED.
     *
     * Satu-satunya jalan bagi refund manual untuk mencapai status final.
     * Negative Payment, pembatalan booking, dan notifikasi client dibuat
     * di sini — bukan saat refund dibuat/disetujui.
     *
     * @throws ValidationException
     */
    public function uploadProof(
        Refund $refund,
        User $mitra,
        UploadedFile $file,
        ?string $catatan = null,
    ): Refund {
        $updated = DB::transaction(function () use ($refund, $mitra, $file, $catatan): Refund {
            /** @var Refund|null $locked */
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== RefundStatus::AWAITING_PROOF) {
                throw ValidationException::withMessages([
                    'refund' => ["Refund berstatus '{$locked->status->label()}' tidak bisa dilengkapi bukti."],
                ]);
            }

            $booking = $locked->booking()->first();

            if ($booking === null) {
                throw ValidationException::withMessages([
                    'booking' => ['Booking terkait refund tidak ditemukan.'],
                ]);
            }

            if ($locked->mitra_id !== $mitra->id || $booking->mitra_id !== $mitra->id) {
                throw ValidationException::withMessages([
                    'refund' => ['Anda tidak memiliki akses untuk refund ini.'],
                ]);
            }

            $path = UploadStorage::put(
                BookingPaymentService::PROOF_DISK,
                'refund_proofs',
                $file,
            );

            if ($locked->proof_path !== null && $locked->proof_path !== '') {
                UploadStorage::deleteAfterCommit(
                    BookingPaymentService::PROOF_DISK,
                    $locked->proof_path,
                );
            }

            $noteLine = '[Bukti Transfer] '.now()->format('Y-m-d H:i')
                .($catatan !== null ? " - {$catatan}" : '');
            $amount = (float) $locked->amount;

            $locked->update([
                'status' => RefundStatus::SUCCEEDED->value,
                'proof_path' => $path,
                'proof_uploaded_at' => now(),
                'proof_uploaded_by' => $mitra->id,
                'catatan' => trim(($locked->catatan ?? '')."\n{$noteLine}"),
            ]);

            Payment::create([
                'booking_id' => $booking->id,
                'jumlah' => -$amount,
                'metode' => PaymentMetode::TRANSFER_MANUAL->value,
                'verified_by' => $mitra->id,
                'verified_at' => now(),
                'catatan' => "Refund manual oleh Mitra: {$mitra->name}. Bukti transfer telah diunggah.",
            ]);

            $booking->update([
                'status' => BookingStatus::CANCELLED->value,
            ]);

            AuditLogger::log('refund.proof_uploaded', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'refund_id' => $locked->id,
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'amount' => $amount,
                'proof_path' => $path,
            ]);

            return $locked->load('booking.client');
        });

        $this->notifyRefundSucceededToClient($updated);

        return $updated;
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
