<?php

namespace App\Repositories\Contracts;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface
{
    /**
     * Buat review baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Review;

    /**
     * Cari review untuk sebuah booking (1 booking = 1 review).
     */
    public function findByBooking(Booking $booking): ?Review;

    /**
     * Daftar review sebuah paket, terbaru dulu, lengkap dengan
     * data penulis (nama saja) untuk ditampilkan di katalog.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginateForPackage(Package $package, int $perPage = 10): LengthAwarePaginator;

    /**
     * Agregat rating sebuah paket dalam satu query.
     *
     * @return array{rata_rata: float, jumlah: int}
     */
    public function statistikForPackage(Package $package): array;

    /**
     * Agregat rating untuk banyak paket dalam satu query.
     *
     * @param  array<int, int>  $packageIds
     * @return array<int, array{rata_rata: float, jumlah: int}>
     */
    public function statistikForPackages(array $packageIds): array;
}
