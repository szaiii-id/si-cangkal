<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Support\LikeEscaper;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repository booking.
 *
 * Perubahan dari versi lama:
 * - Query pencarian kini memakai whereRaw(... ESCAPE '\') (termasuk pada
 *   relasi client dan package) agar karakter %, _, \ benar-benar
 *   di-escape - bukan dianggap wildcard oleh database (BUG FIX).
 * - generateKode() tidak lagi memfilter LENGTH(kode) dan tidak lagi
 *   memakai substr(-3). Sequence diambil setelah prefix, order numerik
 *   via CAST(... AS INTEGER) - aman untuk sequence berapa pun, termasuk
 *   >= 1000 (BUG FIX).
 */
final class BookingRepository implements BookingRepositoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $packageId = $filters['package_id'] ?? null;
        $userId = $filters['user_id'] ?? null;
        $mitraId = $filters['mitra_id'] ?? null;
        $tanggalDari = $filters['tanggal_dari'] ?? null;
        $tanggalSampai = $filters['tanggal_sampai'] ?? null;

        $isToday = filter_var($filters['is_today'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $allowedSorts = ['created_at', 'tanggal_keberangkatan', 'total_harga', 'status', 'kode'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $operator = LikeEscaper::operator(DB::connection());
        $escape = LikeEscaper::ESCAPE_CHAR;

        return Booking::query()
            ->with([
                'package:id,nama,kode,timezone',
                'client:id,name,email',
                'mitra:id,name',
            ])
            ->when($search, function (Builder $query, string $search) use ($operator, $escape): void {
                $escaped = LikeEscaper::contains($search);

                /** @var literal-string $sqlKode */
                $sqlKode = "kode {$operator} ? ESCAPE '{$escape}'";

                /** @var literal-string $sqlClientName */
                $sqlClientName = "name {$operator} ? ESCAPE '{$escape}'";

                /** @var literal-string $sqlClientEmail */
                $sqlClientEmail = "email {$operator} ? ESCAPE '{$escape}'";

                /** @var literal-string $sqlPackageNama */
                $sqlPackageNama = "nama {$operator} ? ESCAPE '{$escape}'";

                $query->where(function (Builder $q) use (
                    $escaped,
                    $sqlKode,
                    $sqlClientName,
                    $sqlClientEmail,
                    $sqlPackageNama
                ): void {
                    $q->whereRaw($sqlKode, [$escaped])
                        ->orWhereHas('client', function (Builder $c) use ($escaped, $sqlClientName, $sqlClientEmail): void {
                            $c->where(function (Builder $nested) use ($escaped, $sqlClientName, $sqlClientEmail): void {
                                $nested->whereRaw($sqlClientName, [$escaped])
                                    ->orWhereRaw($sqlClientEmail, [$escaped]);
                            });
                        })
                        ->orWhereHas('package', function (Builder $p) use ($escaped, $sqlPackageNama): void {
                            $p->whereRaw($sqlPackageNama, [$escaped]);
                        });
                });
            })
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($packageId, fn (Builder $q, int $id) => $q->where('package_id', $id))
            ->when($userId, fn (Builder $q, string $id) => $q->where('user_id', $id))
            ->when($mitraId, fn (Builder $q, string $id) => $q->where('mitra_id', $id))
            ->when($tanggalDari, fn (Builder $q, string $tgl) => $q->where('tanggal_keberangkatan', '>=', $tgl))
            ->when($tanggalSampai, fn (Builder $q, string $tgl) => $q->where('tanggal_keberangkatan', '<=', $tgl))
            ->when($isToday, function (Builder $q) {
                $q->whereDate('tanggal_keberangkatan', Carbon::today());
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForClient(User $client, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginate(array_merge($filters, ['user_id' => $client->id]), $perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(string $id): ?Booking
    {
        return Booking::with([
            'package:id,nama,kode,timezone,harga_per_orang,harga_per_rombongan,durasi_hari,durasi_malam',
            'client:id,name,email,username',
            'participants',
            'payments.verifier:id,name,email',
            'mitra.bankAccounts',
        ])->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdForUpdate(string $id): ?Booking
    {
        return Booking::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByKode(string $kode): ?Booking
    {
        return Booking::with(['package', 'client', 'participants', 'payments'])
            ->where('kode', $kode)
            ->first();
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Booking $booking, array $data): Booking
    {
        $booking->update($data);

        return $booking->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Booking $booking): bool
    {
        return (bool) $booking->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function kodeExists(string $kode, ?string $exceptId = null): bool
    {
        return Booking::withTrashed()
            ->where('kode', $kode)
            ->when($exceptId, fn (Builder $q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * Generate kode booking berikutnya untuk tahun tertentu.
     *
     * FIX dari versi lama:
     * - SEBELUM: whereRaw('LENGTH(kode) = ?', [$expectedLength]) +
     *   substr($lastKode, -3).
     *   - Bug: hanya kode 3 digit yang dihitung, kode 4+ digit (BKG-2026-1000)
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
        $prefix = "BKG-{$tahun}-";

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::selectOne('SELECT pg_advisory_xact_lock(?)', [crc32('booking_creation_global')]);
        }

        /** @var literal-string $orderSql */
        $orderSql = 'CAST(SUBSTR(kode, ?) AS INTEGER) DESC';

        $lastKode = Booking::withTrashed()
            ->where('kode', 'like', "{$prefix}%")
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
     */
    public function findExpired(): Collection
    {
        return Booking::expired()->get();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $counts = Booking::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (BookingStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, int>
     */
    public function countByStatusForClient(User $client): array
    {
        $counts = Booking::query()
            ->where('user_id', $client->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (BookingStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, int>
     */
    public function countByStatusForMitra(User $mitra): array
    {
        $counts = Booking::query()
            ->where('mitra_id', $mitra->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (BookingStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }
}
