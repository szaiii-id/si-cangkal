<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreReviewRequest;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\Review\ReviewService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService,
        protected ReviewRepositoryInterface $reviewRepository,
    ) {}

    /**
     * Form review — hanya untuk booking milik client yang trip-nya selesai.
     */
    public function create(Booking $booking): InertiaResponse
    {
        $this->authorize('create', [Review::class, $booking]);

        $booking->load('package:id,nama,kode');

        return Inertia::render('Client/Reviews/Create', [
            'booking' => [
                'id' => $booking->id,
                'kode' => $booking->kode,
                'paket' => $booking->package?->nama,
            ],
        ]);
    }

    /**
     * Simpan review. Redirect eksplisit ke detail booking (bukan back():
     * referer XHR Inertia adalah halaman form, jadi back() bisa kembali
     * ke form yang baru saja disubmit).
     */
    public function store(Booking $booking, StoreReviewRequest $request): RedirectResponse
    {
        $this->authorize('create', [Review::class, $booking]);

        /** @var User $user */
        $user = $request->user();
        $this->reviewService->create($user, $booking, $request->reviewData());

        return redirect()
            ->route('client.bookings.show', $booking)
            ->with('status', 'Terima kasih! Review kamu sudah terkirim.');
    }
}
