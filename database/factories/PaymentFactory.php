<?php

namespace Database\Factories;

use App\Enums\PaymentMetode;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id'  => Booking::factory()->pendingPayment(),
            'jumlah'      => fake()->numberBetween(300_000, 5_000_000),
            'metode'      => PaymentMetode::XENDIT->value,
            'verified_by' => null,
            'verified_at' => null,
            'catatan'     => null,
        ];
    }

    /**
     * State: payment sudah diverifikasi.
     *
     * Catatan:
     * - `verified_by` diisi ID user (bukan factory object).
     * - Untuk payment via Xendit, verifikasi OTOMATIS oleh webhook,
     *   `verified_by` bisa `null` (tidak ada admin yang klik).
     *   Kalau butuh simulasi admin yang klik manual (legacy),
     *   pakai `->verified($admin)`.
     */
    public function verified(?User $admin = null): static
    {
        return $this->state(fn (): array => [
            'verified_by' => $admin?->id,
            'verified_at' => now(),
        ]);
    }

    /**
     * State: payment yang diverifikasi OTOMATIS oleh Xendit webhook.
     *
     * `verified_by = null` (tidak ada admin), `verified_at = now()`,
     * dan `catatan` berisi ID invoice Xendit.
     */
    public function viaXendit(string $invoiceId = 'test-invoice-123'): static
    {
        return $this->state(fn (): array => [
            'verified_by' => null,
            'verified_at' => now(),
            'catatan'     => "Pembayaran otomatis via Xendit (Invoice: {$invoiceId})",
        ]);
    }
}