<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Support\LikeEscaper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repository booking.
 *
 * Perubahan dari versi lama:
 * - `'%{$search}%'` -> `LikeEscaper::contains()` + `LikeEscaper::operator()`
 *   (S-06).
 * - `paginate()` dan `paginateForClient()` sekarang menambahkan
 *   `with(['mitra:id,name'])` secara eksplisit (B-03) agar tidak
 *   memicu lazy load di `BookingResource`.
 */
final class BookingRepository implements BookingRepositoryInterface
{
    /**
     * {@inheritDoc}
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
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $allowedSorts = ['created_at', 'tanggal_keberangkatan', 'total_harga', 'status', 'kode'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $operator = LikeEscaper::operator(DB::connection());

        return Booking::query()
            ->with([
                'package:id,nama,kode,timezone',
                'client:id,name,email',
                'mitra:id,name',
            ])
            ->when($search, function (Builder $query, string $search) use ($operator): void {
                $escaped = LikeEscaper::contains($search);

                $query->where(function (Builder $q) use ($escaped, $operator): void {
                    $q->where('kode', $operator, $escaped)
                        ->orWhereHas('client', function (Builder $c) use ($escaped, $operator): void {
                            $c->where('name', $operator, $escaped)
                                ->orWhere('email', $operator, $escaped);
                        })
                        ->orWhereHas('package', fn (Builder $p) => $p->where('nama', $operator, $escaped));
                });
            })
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($packageId, fn (Builder $q, int $id) => $q->where('package_id', $id))
            ->when($userId, fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($mitraId, fn (Builder $q, int $id) => $q->where('mitra_id', $id))
            ->when($tanggalDari, fn (Builder $q, string $tgl) => $q->where('tanggal_keberangkatan', '>=', $tgl))
            ->when($tanggalSampai, fn (Builder $q, string $tgl) => $q->where('tanggal_keberangkatan', '<=', $tgl))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForClient(User $client, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginate(array_merge($filters, ['user_id' => $client->id]), $perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Booking
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
    public function findByKode(string $kode): ?Booking
    {
        return Booking::with(['package', 'client', 'participants', 'payments'])
            ->where('kode', $kode)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    /**
     * {@inheritDoc}
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
    public function kodeExists(string $kode, ?int $exceptId = null): bool
    {
        return Booking::withTrashed()
            ->where('kode', $kode)
            ->when($exceptId, fn (Builder $q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function generateKode(int $tahun): string
    {
        $prefix = "BKG-{$tahun}-";
        $expectedLength = strlen($prefix) + 3;

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::selectOne('SELECT pg_advisory_xact_lock(?)', [crc32('booking_creation_global')]);
        }

        $lastKode = Booking::withTrashed()
            ->where('kode', 'like', "{$prefix}%")
            ->whereRaw('LENGTH(kode) = ?', [$expectedLength])
            ->orderByDesc('kode')
            ->value('kode');

        if ($lastKode === null) {
            return $prefix.'001';
        }

        $lastNumber = (int) substr((string) $lastKode, -3);

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
}