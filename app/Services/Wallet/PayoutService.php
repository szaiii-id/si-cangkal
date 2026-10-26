<?php

namespace App\Services\Wallet;

use App\Repositories\Contracts\PaymentGatewayInterface;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\User;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    public const MINIMUM_PAYOUT = 100000;

    public function __construct(
        protected PayoutRepositoryInterface $payoutRepository,
        protected WalletService $walletService,
        protected PaymentGatewayInterface $paymentGateway, // ✅ Interface, bukan XenditService
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Payout>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->payoutRepository->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Payout>
     */
    public function paginateForMitra(User $mitra, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->payoutRepository->paginateForMitra($mitra, $filters, $perPage);
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        return $this->payoutRepository->countByStatus();
    }

    /** @return array<string, int> */
    public function countByStatusForMitra(User $mitra): array
    {
        return $this->payoutRepository->countByStatusForMitra($mitra);
    }

    /**
     * @param  array{
     *     jumlah: float|int,
     *     bank_tujuan: string,
     *     no_rekening: string,
     *     nama_pemilik: string,
     *     catatan?: string|null
     * }  $data
     */
    public function request(User $mitra, array $data): Payout
    {
        $wallet = $this->walletService->findByMitra($mitra);

        $jumlah = (float) $data['jumlah'];

        if ((float) $wallet->saldo < $jumlah) {
            throw ValidationException::withMessages(['jumlah' => ['Saldo tidak cukup.']]);
        }

        if ($jumlah < self::MINIMUM_PAYOUT) {
            throw ValidationException::withMessages([
                'jumlah' => ['Minimum pencairan Rp '.number_format(self::MINIMUM_PAYOUT, 0, ',', '.')],
            ]);
        }

        $pending = $this->payoutRepository->findPendingForMitraToday($mitra);
        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                'payout' => ['Anda sudah memiliki pengajuan hari ini. Tunggu review admin.'],
            ]);
        }

        return DB::transaction(function () use ($mitra, $data, $jumlah) {
            $kode = $this->payoutRepository->generateKode((int) now()->year);

            $payout = $this->payoutRepository->create([
                'kode' => $kode,
                'mitra_id' => $mitra->id,
                'jumlah' => $jumlah,
                'bank_tujuan' => $data['bank_tujuan'],
                'no_rekening' => $data['no_rekening'],
                'nama_pemilik' => $data['nama_pemilik'],
                'status' => PayoutStatus::PENDING->value,
                'requested_at' => now(),
                'catatan' => $data['catatan'] ?? null,
            ]);

            Log::info('Payout requested', [
                'payout_id' => $payout->id,
                'mitra_id' => $mitra->id,
                'jumlah' => $jumlah,
            ]);

            return $payout;
        });
    }

    /**
     * Approve Payout & Otomatis Kirim Dana via Payment Gateway.
     *
     * Alur:
     * 1. Potong saldo mitra (di dalam DB transaction).
     * 2. Panggil PaymentGatewayInterface->createPayout().
     * 3. Jika gateway gagal → Rollback (saldo kembali).
     * 4. Jika sukses → Simpan gateway_payout_id & update status.
     */
    public function approve(Payout $payout, User $admin): Payout
    {
        if ($payout->status !== PayoutStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Hanya pengajuan pending yang bisa disetujui.'],
            ]);
        }

        $mitra = $payout->mitra;
        if (! $mitra) {
            throw ValidationException::withMessages([
                'mitra' => ['Mitra tidak ditemukan untuk payout ini.'],
            ]);
        }

        // 1. Potong Saldo Mitra (di dalam transaksi DB)
        //    Jika gateway gagal, kita akan melakukan rollback saldo manual.
        DB::beginTransaction();

        try {
            // Potong saldo & catat di wallet_transactions
            $this->walletService->debitForPayout(
                $mitra,
                (float) $payout->jumlah,
                "Pencairan {$payout->kode}",
                $payout->id,
            );

            // 2. Kirim Dana via Payment Gateway
            $gatewayPayout = $this->paymentGateway->createPayout($payout);

            // 3. Update status Payout dengan ID dari gateway
            $payout = $this->payoutRepository->update($payout, [
                'status' => PayoutStatus::APPROVED->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'xendit_payout_id' => $gatewayPayout['id'] ?? null,
                'xendit_payout_status' => $gatewayPayout['status'] ?? null,
            ]);

            DB::commit();

            Log::info('Payout approved & dikirim ke Payment Gateway', [
                'payout_id' => $payout->id,
                'admin_id' => $admin->id,
                'gateway_payout_id' => $gatewayPayout['id'] ?? null,
            ]);

            return $payout;

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Gagal approve payout via Payment Gateway. Saldo mitra dikembalikan.', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment_gateway' => ['Gagal memproses pencairan ke Payment Gateway: '.$e->getMessage()],
            ]);
        }
    }

    public function reject(Payout $payout, User $admin, string $reason): Payout
    {
        if ($payout->status !== PayoutStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Hanya pengajuan pending yang bisa ditolak.'],
            ]);
        }

        return DB::transaction(function () use ($payout, $admin, $reason) {
            $payout = $this->payoutRepository->update($payout, [
                'status' => PayoutStatus::REJECTED->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            Log::warning('Payout rejected', [
                'payout_id' => $payout->id,
                'reason' => $reason,
            ]);

            return $payout;
        });
    }

    /**
     * Update Status Payout via Webhook Payment Gateway.
     *
     * Dipanggil dari XenditWebhookController saat menerima event 'payout.succeeded'
     * atau 'payout.failed'.
     */
    public function updateFromWebhook(Payout $payout, string $xenditStatus): Payout
    {
        // Mapping status gateway ke PayoutStatus internal
        $statusEnum = match (strtoupper($xenditStatus)) {
            'SUCCEEDED' => PayoutStatus::PAID,
            'FAILED' => PayoutStatus::REJECTED,
            default => PayoutStatus::APPROVED,
        };

        return DB::transaction(function () use ($payout, $xenditStatus, $statusEnum) {
            $payout = $this->payoutRepository->update($payout, [
                'status' => $statusEnum->value,
                'xendit_payout_status' => $xenditStatus,
                'paid_at' => $statusEnum === PayoutStatus::PAID ? now() : $payout->paid_at,
            ]);

            Log::info('Webhook: Payout diupdate dari Payment Gateway', [
                'payout_id' => $payout->id,
                'xendit_status' => $xenditStatus,
                'internal_status' => $statusEnum->value,
            ]);

            return $payout;
        });
    }
}