<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Services\Review\ReviewService;

class ReviewPolicy
{
    /**
     * Policy dan service memakai satu sumber aturan yang sama (canReview),
     * supaya UI dan server tidak bisa beda pendapat.
     */
    public function create(User $user, Booking $booking): bool
    {
        return app(ReviewService::class)->canReview($user, $booking);
    }
}
