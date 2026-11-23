<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Review;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ReviewRepository implements ReviewRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Review
    {
        return Review::create($data);
    }

    public function findByBooking(Booking $booking): ?Review
    {
        return Review::where('booking_id', $booking->id)->first();
    }

    /**
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginateForPackage(Package $package, int $perPage = 10): LengthAwarePaginator
    {
        return Review::where('package_id', $package->id)
            ->with('client:id,name')
            ->terbaru()
            ->paginate($perPage);
    }

    /**
     * @return array{rata_rata: float, jumlah: int}
     */
    public function statistikForPackage(Package $package): array
    {
        $statistik = DB::table('reviews')
            ->where('package_id', $package->id)
            ->selectRaw('COALESCE(AVG(rating), 0) as rata_rata, COUNT(*) as jumlah')
            ->first();

        // Query agregat tanpa GROUP BY selalu mengembalikan satu baris,
        // termasuk saat tabel kosong (COALESCE → 0, COUNT(*) → 0).
        return [
            'rata_rata' => (float) $statistik->rata_rata,
            'jumlah' => (int) $statistik->jumlah,
        ];
    }
}
