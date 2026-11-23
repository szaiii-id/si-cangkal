<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'package_id' => Package::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'komentar' => fake()->paragraph(),
        ];
    }

    /**
     * Review untuk booking tertentu.
     *
     * package_id dan user_id diambil dari booking supaya data
     * tetap konsisten dengan constraint aplikasi.
     */
    public function forBooking(Booking $booking): static
    {
        return $this->state(fn (): array => [
            'booking_id' => $booking->id,
            'package_id' => $booking->package_id,
            'user_id' => $booking->user_id,
        ]);
    }

    public function forPackage(Package $package): static
    {
        return $this->state(fn (): array => [
            'package_id' => $package->id,
        ]);
    }

    public function forClient(User $client): static
    {
        return $this->state(fn (): array => [
            'user_id' => $client->id,
        ]);
    }

    public function rating(int $rating): static
    {
        return $this->state(fn (): array => [
            'rating' => $rating,
        ]);
    }
}
