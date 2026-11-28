<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\ETicket;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class BookingQueryService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
    ) {}

    /**
     * Daftar booking dengan pagination, search, dan filter.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Booking>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->bookingRepository->paginate($filters, $perPage);
    }

    /**
     * Daftar booking milik client tertentu.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Booking>
     */
    public function paginateForClient(
        User $client,
        array $filters = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->bookingRepository->paginateForClient($client, $filters, $perPage);
    }

    /**
     * Ambil detail booking (dengan relasi yang dibutuhkan).
     *
     * @throws ValidationException Bila booking tidak ditemukan.
     */
    public function findDetail(string $id): Booking
    {
        $booking = $this->bookingRepository->findById($id);

        if ($booking === null) {
            throw ValidationException::withMessages([
                'booking' => ['Booking tidak ditemukan.'],
            ]);
        }

        return $booking;
    }

    /**
     * Jumlah booking per status (global, untuk dashboard admin).
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        return $this->bookingRepository->countByStatus();
    }

    /**
     * Jumlah booking per status untuk client tertentu.
     *
     * @return array<string, int>
     */
    public function countByStatusForClient(User $client): array
    {
        return $this->bookingRepository->countByStatusForClient($client);
    }

    /**
     * Jumlah booking per status untuk mitra tertentu.
     *
     * @return array<string, int>
     */
    public function countByStatusForMitra(User $mitra): array
    {
        return $this->bookingRepository->countByStatusForMitra($mitra);
    }

    /**
     * Ambil E-Ticket milik booking (bila sudah di-generate).
     */
    public function getETicketForBooking(Booking $booking): ?ETicket
    {
        return ETicket::where('booking_id', $booking->id)->first();
    }
}
