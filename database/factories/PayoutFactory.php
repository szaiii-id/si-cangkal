<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payout> */
class PayoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => 'PAY-'.now()->year.'-'.str_pad(
                (string) fake()->unique()->numberBetween(1, 9999), 3, '0', STR_PAD_LEFT
            ),
            'mitra_id' => User::factory()->mitra(),
            'jumlah' => fake()->numberBetween(100_000, 5_000_000),
            'bank_tujuan' => 'BCA',
            'no_rekening' => '1234567890',
            'nama_pemilik' => fake()->name(),
            'status' => PayoutStatus::PENDING->value,
            'requested_at' => now(),
        ];
    }

    /**
     * State: payout sudah di-approve admin.
     *
     * @param  User|null  $admin  Reviewer (opsional). Kalau null, `reviewed_by` = null.
     */
    public function approved(?User $admin = null): static
    {
        return $this->state(fn (): array => [
            'status' => PayoutStatus::APPROVED->value,
            'reviewed_by' => $admin?->id,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * State: payout sudah ditransfer ke rekening mitra.
     *
     * @param  User|null  $admin  Reviewer (opsional).
     */
    public function paid(?User $admin = null): static
    {
        return $this->state(fn (): array => [
            'status' => PayoutStatus::PAID->value,
            'reviewed_by' => $admin?->id,
            'reviewed_at' => now()->subHour(),
            'paid_at' => now(),
        ]);
    }

    /**
     * State: payout ditolak admin.
     *
     * @param  string  $reason  Alasan penolakan.
     * @param  User|null  $admin  Reviewer (opsional).
     */
    public function rejected(string $reason = 'Data rekening tidak valid', ?User $admin = null): static
    {
        return $this->state(fn (): array => [
            'status' => PayoutStatus::REJECTED->value,
            'reviewed_by' => $admin?->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * State: payout dengan Xendit tracking ID.
     *
     * @param  string  $xenditPayoutId  ID dari Xendit.
     * @param  string  $xenditStatus  Status Xendit (SUCCEEDED, FAILED, PENDING).
     */
    public function withXendit(
        string $xenditPayoutId = 'xnd_payout_test123',
        string $xenditStatus = 'PENDING',
    ): static {
        return $this->state(fn (): array => [
            'xendit_payout_id' => $xenditPayoutId,
            'xendit_payout_status' => $xenditStatus,
        ]);
    }
}