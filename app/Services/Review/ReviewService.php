<?php

namespace App\Services\Review;

use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\Notification\Contracts\NotificationServiceInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReviewService
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviewRepository,
        private readonly NotificationServiceInterface $notificationService,
    ) {}

    /**
     * Satu sumber aturan untuk UI (canReview prop) dan server (Policy/store).
     *
     * Syarat: client pemilik booking, trip related selesai, belum direview.
     */
    public function canReview(User $user, Booking $booking): bool
    {
        if (! $user->isClient() || $booking->user_id !== $user->id) {
            return false;
        }

        if ($booking->trip?->status !== TripStatus::COMPLETED) {
            return false;
        }

        return $this->reviewRepository->findByBooking($booking) === null;
    }

    /**
     * @param  array{rating: int, komentar: string}  $data
     */
    public function create(User $user, Booking $booking, array $data): Review
    {
        try {
            return DB::transaction(function () use ($user, $booking, $data): Review {
                // Cek ulang di dalam transaksi untuk menutup race condition
                // (dua submit bersamaan tetap dicegah oleh unique booking_id).
                if (! $this->canReview($user, $booking)) {
                    throw ValidationException::withMessages([
                        'booking' => ['Review tidak dapat dibuat untuk booking ini.'],
                    ]);
                }

                $review = $this->reviewRepository->create([
                    'booking_id' => $booking->id,
                    'package_id' => $booking->package_id,
                    'user_id' => $user->id,
                    'rating' => (int) $data['rating'],
                    'komentar' => trim($data['komentar']),
                ]);

                $booking->loadMissing('package:id,nama');
                $mitra = $booking->mitra;

                if ($mitra !== null) {
                    $this->notificationService->create(
                        $mitra,
                        'review_baru',
                        'Review baru untuk paket Anda',
                        "Paket {$booking->package?->nama} mendapat review {$data['rating']} bintang.",
                        [
                            'review_id' => $review->id,
                            'booking_id' => $booking->id,
                            // NotificationService dipakai langsung (tanpa dispatcher),
                            // jadi URL tujuan harus disertakan di sini.
                            'url' => "/mitra/bookings/{$booking->id}",
                        ],
                    );
                }

                return $review;
            });
        } catch (QueryException $exception) {
            // Unique constraint booking_id adalah garda terakhir race condition.
            if (str_contains($exception->getMessage(), 'reviews_booking_id_unique')) {
                throw ValidationException::withMessages([
                    'booking' => ['Review untuk booking ini sudah ada.'],
                ]);
            }

            throw $exception;
        }
    }
}
