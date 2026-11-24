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
 * - searchOperator() diganti LikeEscaper::operator() + LikeEscaper::contains()
 *   (S-06).
 * - Query pencarian kini memakai whereRaw(... ESCAPE '\') untuk kolom
 *   utama dan relasi nested (booking, package, booking.client) agar
 *   karakter %, _, \ benar-benar di-escape - bukan dianggap wildcard
 *   oleh database (BUG FIX).
 * - generateKode() tidak lagi memfilter LENGTH(kode) dan tidak lagi
 *   memakai substr(-3). Sequence diambil setelah prefix, order numerik
 *   via CAST(... AS INTEGER) - aman untuk sequence berapa pun, termasuk
 *   >= 1000 (BUG FIX).
 *
 * Catatan desain:
 * - Trip TIDAK memakai SoftDeletes dan TIDAK punya method delete().
 *   Trip tidak pernah dihapus - hanya di-cancel (lihat TripStatus).
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
        $escape = LikeEscaper::ESCAPE_CHAR;

        return Trip::query()
            ->with([
                'package:id,nama,kode,timezone',
                'booking:id,kode,user_id',
                'booking.client:id,name,email',
                'mitra:id,name',
            ])
            ->when($search, function (Builder $query, string $search) use ($operator, $escape): void {
                $escaped = LikeEscaper::contains($search);

                $query->where(function (Builder $q) use ($escaped, $operator, $escape): void {
                    // PHPStan butuh literal-string. Nilai dinamis ($operator,
                    // $escape) diinterpolasi ke SQL string; ini aman karena
                    // keduanya berasal dari LikeEscaper (bukan input user).
                    /** @var literal-string $sqlTrip */
                    $sqlTrip = "kode {$operator} ? ESCAPE '{$escape}'";

                    /** @var literal-string $sqlBooking */
                    $sqlBooking = "kode {$operator} ? ESCAPE '{$escape}'";

                    /** @var literal-string $sqlPackageNama */
                    $sqlPackageNama = "nama {$operator} ? ESCAPE '{$escape}'";

                    /** @var literal-string $sqlPackageKode */
                    $sqlPackageKode = "kode {$operator} ? ESCAPE '{$escape}'";

                    /** @var literal-string $sqlClientName */
                    $sqlClientName = "name {$operator} ? ESCAPE '{$escape}'";

                    /** @var literal-string $sqlClientEmail */
                    $sqlClientEmail = "email {$operator} ? ESCAPE '{$escape}'";

                    $q->whereRaw($sqlTrip, [$escaped])
                        ->orWhereHas('booking', function (Builder $b) use ($sqlBooking, $escaped): void {
                            $b->whereRaw($sqlBooking, [$escaped]);
                        })
                        ->orWhereHas('package', function (Builder $p) use ($sqlPackageNama, $sqlPackageKode, $escaped): void {
                            $p->where(function (Builder $nested) use ($sqlPackageNama, $sqlPackageKode, $escaped): void {
                                $nested->whereRaw($sqlPackageNama, [$escaped])
                                    ->orWhereRaw($sqlPackageKode, [$escaped]);
                            });
                        })
                        ->orWhereHas('booking.client', function (Builder $c) use ($sqlClientName, $sqlClientEmail, $escaped): void {
                            $c->where(function (Builder $nested) use ($sqlClientName, $sqlClientEmail, $escaped): void {
                                $nested->whereRaw($sqlClientName, [$escaped])
                                    ->orWhereRaw($sqlClientEmail, [$escaped]);
                            });
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
    public function findByIdForUpdate(int $id): ?Trip
    {
        return Trip::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
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
     *
     * FIX dari versi lama:
     * - SEBELUM: whereRaw('LENGTH(kode) = ?', [$expectedLength]) +
     *   substr($lastKode, -3).
     *   - Bug: hanya kode 3 digit yang dihitung, kode 4+ digit (TRP-2026-1000)
     *     tidak match - rollback ke 001, atau duplikat kode.
     * - SESUDAH: tidak pakai LENGTH filter, orderByRaw pakai CAST numerik,
     *   substr setelah prefix. Aman untuk sequence berapa pun.
     *
     * Lock:
     * - PostgreSQL: pg_advisory_xact_lock cegah race condition antar request.
     * - Driver lain: no-op (race ditangani oleh unique constraint DB).
     */
    public function generateKode(int $tahun): string
    {
        $prefix = "TRP-{$tahun}-";

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::selectOne(
                'SELECT pg_advisory_xact_lock(?)',
                [crc32("trip_kode_{$tahun}")],
            );
        }

        /** @var literal-string $orderSql */
        $orderSql = 'CAST(SUBSTR(kode, ?) AS INTEGER) DESC';

        $lastKode = Trip::where('kode', 'like', "{$prefix}%")
            ->orderByRaw($orderSql, [strlen($prefix) + 1])
            ->value('kode');

        if ($lastKode === null) {
            return $prefix.'001';
        }

        $lastNumber = (int) substr((string) $lastKode, strlen($prefix));

        return $prefix.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * {@inheritDoc}
     *
     * Tanpa pemotongan tanggal global — lihat interface untuk alasannya.
     */
    public function findDueScheduled(): Collection
    {
        return Trip::scheduled()
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
