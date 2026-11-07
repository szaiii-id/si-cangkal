<?php

namespace App\Repositories\Eloquent;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Support\LikeEscaper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repository trip.
 *
 * Perubahan dari versi lama:
 * - `searchOperator()` diganti `LikeEscaper::operator()` + `LikeEscaper::contains()`
 *   (S-06).
 * - `paginate()` menambahkan eager-load `days.trip` TIDAK dilakukan di sini
 *   (itu tugas controller `show()`); `paginate()` cukup memuat relasi yang
 *   dibutuhkan `TripResource` di level list (mitra, package, booking.client).
 */
final class TripRepository implements TripRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $packageId = $filters['package_id'] ?? null;
        $mitraId = $filters['mitra_id'] ?? null;
        $tanggalDari = $filters['tanggal_dari'] ?? null;
        $tanggalSampai = $filters['tanggal_sampai'] ?? null;
        $sort = $filters['sort'] ?? 'tanggal_mulai';
        $direction = $filters['direction'] ?? 'desc';

        $allowedSorts = ['tanggal_mulai', 'tanggal_selesai', 'created_at', 'status', 'kode'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'tanggal_mulai';
        }

        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $operator = LikeEscaper::operator(DB::connection());

        return Trip::query()
            ->with([
                'package:id,nama,kode,timezone',
                'booking:id,kode,user_id',
                'booking.client:id,name,email',
                'mitra:id,name',
            ])
            ->when($search, function (Builder $query, string $search) use ($operator): void {
                $escaped = LikeEscaper::contains($search);

                $query->where(function (Builder $q) use ($escaped, $operator): void {
                    $q->where('kode', $operator, $escaped)
                        ->orWhereHas('booking', fn (Builder $b) => $b->where('kode', $operator, $escaped))
                        ->orWhereHas('package', function (Builder $p) use ($escaped, $operator): void {
                            $p->where('nama', $operator, $escaped)
                                ->orWhere('kode', $operator, $escaped);
                        })
                        ->orWhereHas('booking.client', function (Builder $c) use ($escaped, $operator): void {
                            $c->where('name', $operator, $escaped)
                                ->orWhere('email', $operator, $escaped);
                        });
                });
            })
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($packageId, fn (Builder $q, int $id) => $q->where('package_id', $id))
            ->when($mitraId, fn (Builder $q, int $id) => $q->where('mitra_id', $id))
            ->when($tanggalDari, fn (Builder $q, string $tgl) => $q->where('tanggal_mulai', '>=', $tgl))
            ->when($tanggalSampai, fn (Builder $q, string $tgl) => $q->where('tanggal_mulai', '<=', $tgl))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForMitra(User $mitra, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginate(array_merge($filters, ['mitra_id' => $mitra->id]), $perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Trip
    {
        return Trip::with([
            'package:id,nama,kode,slug,timezone,cover_image',
            'booking:id,kode,user_id,tanggal_keberangkatan,jumlah_peserta,total_harga,status',
            'booking.client:id,name,email,username',
            'mitra:id,name,email,username,timezone',
            'days.trip',
        ])->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByKode(string $kode): ?Trip
    {
        return Trip::with(['package', 'booking.client', 'mitra', 'days.trip'])
            ->where('kode', $kode)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByBookingId(int $bookingId): ?Trip
    {
        return Trip::with(['package', 'mitra', 'days.trip'])
            ->where('booking_id', $bookingId)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Trip
    {
        return Trip::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(Trip $trip, array $data): Trip
    {
        $trip->update($data);

        return $trip->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function kodeExists(string $kode, ?int $exceptId = null): bool
    {
        return Trip::where('kode', $kode)
            ->when($exceptId, fn (Builder $q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function generateKode(int $tahun): string
    {
        $prefix = "TRP-{$tahun}-";
        $expectedLength = strlen($prefix) + 3;

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::selectOne(
                'SELECT pg_advisory_xact_lock(?)',
                [crc32("trip_kode_{$tahun}")],
            );
        }

        $lastKode = Trip::where('kode', 'like', "{$prefix}%")
            ->whereRaw('LENGTH(kode) = ?', [$expectedLength])
            ->orderByDesc('kode')
            ->value('kode');

        if ($lastKode === null) {
            return $prefix.'001';
        }

        $lastNumber = (int) substr((string) $lastKode, -3);
        $nextNumber = $lastNumber + 1;

        return $prefix.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * {@inheritDoc}
     */
    public function findScheduledDueToday(string $tanggal): Collection
    {
        return Trip::scheduled()
            ->where('tanggal_mulai', '<=', $tanggal)
            ->with(['mitra', 'booking', 'package'])
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function countByStatus(): array
    {
        $counts = Trip::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (TripStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function countByStatusForMitra(User $mitra): array
    {
        $counts = Trip::query()
            ->where('mitra_id', $mitra->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (TripStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }
}