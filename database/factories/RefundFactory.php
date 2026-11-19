<?php

namespace Database\Factories;

use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'mitra_id' => User::factory()->mitra(),
            'amount' => fake()->numberBetween(100_000, 5_000_000),
            'currency' => 'IDR',
            'reason' => RefundReason::CANCELLATION->value,
            'status' => RefundStatus::PENDING->value,
            'failure_code' => null,
            'refund_fee_amount' => null,
            'initiated_by_mitra' => User::factory()->mitra(),
            'catatan' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::SUCCEEDED->value,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::FAILED->value,
            'failure_code' => 'INSUFFICIENT_BALANCE',
        ]);
    }
}
