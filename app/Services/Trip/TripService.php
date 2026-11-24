<?php

namespace App\Services\Trip;

use App\Enums\BookingStatus;
use App\Enums\PackageFacilityTipe;
use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\User;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Trip\Contracts\TripStatusSynchronizerInterface;
use App\Support\Timezone;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class TripService
{
    public function __construct(
        protected TripRepositoryInterface $tripRepository,
        private readonly TripStatusSynchronizerInterface $statusSynchronizer,
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Trip>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->tripRepository->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Trip>
     */
    public function paginateForMitra(User $mitra, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->tripRepository->paginateForMitra($mitra, $filters, $perPage);
    }

    public function findDetail(int $id): Trip
    {
        $trip = $this->tripRepository->findById($id);
        if ($trip === null) {
            throw ValidationException::withMessages(['trip' => ['Trip tidak ditemukan.']]);
        }

        return $trip;
    }

    public function findByBookingId(int $bookingId): ?Trip
    {
        return $this->tripRepository->findByBookingId($bookingId);
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        return $this->tripRepository->countByStatus();
    }

    /**
     * @return array<string, int>
     */
    public function countByStatusForMitra(User $mitra): array
    {
        return $this->tripRepository->countByStatusForMitra($mitra);
    }

    public function createFromBooking(Booking $booking): Trip
    {
        if ($booking->status !== BookingStatus::CONFIRMED) {
            throw ValidationException::withMessages([
                'booking' => ['Hanya booking yang sudah dikonfirmasi bisa membuat trip.'],
            ]);
        }

        if ($this->tripRepository->findByBookingId($booking->id) !== null) {
            throw ValidationException::withMessages([
                'booking' => ['Booking ini sudah memiliki trip.'],
            ]);
        }

        return DB::transaction(function () use ($booking): Trip {
            /** @var Package $package */
            $package = $booking->package;

            $package->loadMissing(['days', 'facilities']);
            $snapshot = $this->buildSnapshot($package);

            $tanggalMulai = Carbon::parse($booking->tanggal_keberangkatan);
            $tanggalSelesai = $tanggalMulai->copy()->addDays($package->durasi_hari - 1);
            $kode = $this->tripRepository->generateKode((int) now()->year);

            $trip = $this->tripRepository->create([
                'kode' => $kode,
                'booking_id' => $booking->id,
                'package_id' => $package->id,
                'mitra_id' => $booking->mitra_id,
                'snapshot_package' => $snapshot,
                'tanggal_mulai' => $tanggalMulai->format('Y-m-d'),
                'tanggal_selesai' => $tanggalSelesai->format('Y-m-d'),
                'jumlah_peserta' => $booking->jumlah_peserta,
                'timezone' => $package->timezone,
                'status' => TripStatus::SCHEDULED->value,
            ]);

            $this->generateTripDays($trip, $package);

            Log::info('Trip dibuat dari booking', [
                'trip_id' => $trip->id,
                'trip_kode' => $trip->kode,
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'package_id' => $package->id,
                'mitra_id' => $trip->mitra_id,
            ]);

            return $trip->load(['package', 'booking.client', 'days']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSnapshot(Package $package): array
    {
        return [
            'nama' => $package->nama,
            'kode' => $package->kode,
            'slug' => $package->slug,
            'deskripsi' => $package->deskripsi,
            'kategori' => $package->kategori->value,
            'durasi_hari' => $package->durasi_hari,
            'durasi_malam' => $package->durasi_malam,
            'timezone' => $package->timezone,
            'days' => $package->days->map(fn (PackageDay $day): array => [
                'hari_ke' => $day->hari_ke,
                'judul' => $day->judul,
                'deskripsi' => $day->deskripsi,
                'jam_mulai' => $day->jam_mulai,
                'jam_selesai' => $day->jam_selesai,
                'lokasi' => $day->lokasi,
            ])->values()->all(),
            'facilities' => [
                'include' => $package->facilities
                    ->where('tipe', PackageFacilityTipe::INCLUDE)
                    ->pluck('nama')->values()->all(),
                'exclude' => $package->facilities
                    ->where('tipe', PackageFacilityTipe::EXCLUDE)
                    ->pluck('nama')->values()->all(),
            ],
        ];
    }

    protected function generateTripDays(Trip $trip, Package $package): void
    {
        $package->loadMissing('days');

        foreach ($package->days as $packageDay) {
            TripDay::create([
                'trip_id' => $trip->id,
                'hari_ke' => $packageDay->hari_ke,
                'tanggal' => Carbon::parse($trip->tanggal_mulai)
                    ->addDays($packageDay->hari_ke - 1)
                    ->format('Y-m-d'),
                'judul' => $packageDay->judul,
                'deskripsi' => $packageDay->deskripsi,
                'status' => TripDay::STATUS_PENDING,
            ]);
        }
    }

    public function activate(Trip $trip): Trip
    {
        [$updated, $booking] = DB::transaction(function () use ($trip): array {
            // Lock baris trip agar dua request (manual + scheduler) tidak
            // memproses transisi status yang sama dua kali.
            $locked = $this->tripRepository->findByIdForUpdate($trip->id) ?? $trip;

            if ($locked->status !== TripStatus::SCHEDULED) {
                throw ValidationException::withMessages([
                    'status' => ['Trip harus berstatus "Terjadwal" untuk diaktifkan.'],
                ]);
            }

            $today = Timezone::today($locked->timezoneOrDefault());

            if ($locked->tanggal_mulai->format('Y-m-d') > $today->format('Y-m-d')) {
                throw ValidationException::withMessages([
                    'status' => ['Trip belum waktunya dimulai.'],
                ]);
            }

            $updated = $this->tripRepository->update($locked, [
                'status' => TripStatus::ACTIVE->value,
            ]);

            $booking = $this->statusSynchronizer->markActivated($updated);

            Log::info('Trip diaktifkan', ['trip_id' => $updated->id, 'kode' => $updated->kode]);

            return [$updated, $booking];
        });

        if ($booking !== null) {
            $this->notifyClientTripStatus(
                $booking,
                tipe: 'trip_started',
                judul: 'Perjalanan Dimulai',
                pesan: "Perjalanan {$updated->kode} untuk booking {$booking->kode} telah dimulai. Selamat menikmati perjalanan!",
            );
        }

        return $updated;
    }

    public function complete(Trip $trip): Trip
    {
        [$updated, $booking] = DB::transaction(function () use ($trip): array {
            $locked = $this->tripRepository->findByIdForUpdate($trip->id) ?? $trip;

            if ($locked->status !== TripStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'status' => ['Trip harus berstatus "Sedang Berjalan" untuk diselesaikan.'],
                ]);
            }

            $updated = $this->tripRepository->update($locked, [
                'status' => TripStatus::COMPLETED->value,
            ]);

            $booking = $this->statusSynchronizer->markCompleted($updated);

            Log::info('Trip diselesaikan', ['trip_id' => $updated->id, 'kode' => $updated->kode]);

            return [$updated, $booking];
        });

        if ($booking !== null) {
            $this->notifyClientTripStatus(
                $booking,
                tipe: 'trip_completed',
                judul: 'Perjalanan Selesai',
                pesan: "Perjalanan {$updated->kode} untuk booking {$booking->kode} telah selesai. Terima kasih telah mempercayakan perjalanan Anda kepada kami.",
            );
        }

        return $updated;
    }

    public function cancel(Trip $trip, string $alasan = 'Dibatalkan'): Trip
    {
        return DB::transaction(function () use ($trip, $alasan): Trip {
            $locked = $this->tripRepository->findByIdForUpdate($trip->id) ?? $trip;

            if (! $locked->canBeCancelled()) {
                throw ValidationException::withMessages([
                    'status' => ['Trip ini tidak bisa dibatalkan.'],
                ]);
            }

            $updated = $this->tripRepository->update($locked, [
                'status' => TripStatus::CANCELLED->value,
            ]);

            Log::warning('Trip dibatalkan', [
                'trip_id' => $updated->id,
                'kode' => $updated->kode,
                'alasan' => $alasan,
            ]);

            return $updated;
        });
    }

    /**
     * @return array{activated: int, skipped_no_mitra: int}
     */
    public function activateDueTrips(): array
    {
        $candidates = $this->tripRepository->findDueScheduled();

        $activated = 0;
        $skippedNoMitra = 0;

        foreach ($candidates as $trip) {
            if (! $trip->hasMitra()) {
                $skippedNoMitra++;

                Log::warning('Trip due dilewati: belum punya mitra', [
                    'trip_id' => $trip->id,
                    'kode' => $trip->kode,
                ]);

                continue;
            }

            $tripToday = Timezone::today($trip->timezoneOrDefault());

            if ($trip->tanggal_mulai->format('Y-m-d') > $tripToday->format('Y-m-d')) {
                continue;
            }

            try {
                $this->activate($trip);
                $activated++;

                Log::info('Trip auto-activated', ['trip_id' => $trip->id, 'kode' => $trip->kode]);
            } catch (Throwable $e) {
                Log::error('Gagal auto-activate trip', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'activated' => $activated,
            'skipped_no_mitra' => $skippedNoMitra,
        ];
    }

    /**
     * Kirim notifikasi status trip ke client.
     *
     * Dipanggil SETELAH transaksi commit dan tidak boleh menggagalkan
     * perubahan status operasional bila pengiriman notifikasi error.
     */
    private function notifyClientTripStatus(
        Booking $booking,
        string $tipe,
        string $judul,
        string $pesan,
    ): void {
        $client = $booking->client;

        if ($client === null) {
            return;
        }

        try {
            $this->notificationDispatcher->send(
                user: $client,
                tipe: $tipe,
                judul: $judul,
                pesan: $pesan,
                data: [
                    'booking_id' => $booking->id,
                    // URL eksplisit: client tidak punya halaman /client/trips.
                    'url' => "/client/bookings/{$booking->id}",
                ],
            );
        } catch (Throwable $e) {
            Log::warning('TripService: gagal mengirim notifikasi status trip', [
                'booking_id' => $booking->id,
                'tipe' => $tipe,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
