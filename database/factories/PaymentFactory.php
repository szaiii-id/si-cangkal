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
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->pendingPayment(),
            'jumlah' => fake()->numberBetween(300_000, 5_000_000),
            'metode' => PaymentMetode::TRANSFER_MANUAL->value,
            'verified_by' => null,
            'verified_at' => null,
            'catatan' => null,
        ];
    }

    public function verified(?User $admin = null): static
    {
        return $this->state(fn (): array => [
            'verified_by' => $admin?->id,
            'verified_at' => now(),
        ]);
    }
}
