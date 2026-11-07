<?php

namespace App\Repositories\Eloquent;

use App\Enums\PackageStatus;
use App\Models\Package;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Support\LikeEscaper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repository paket wisata.
 *
 * Perubahan dari versi lama:
 * - `'%{$search}%'` -> `LikeEscaper::contains()` + `LikeEscaper::operator()`
 *   (S-06).
 */
final class PackageRepository implements PackageRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $kategori = $filters['kategori'] ?? null;
        $mitraId = $filters['mitra_id'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $allowedSorts = ['created_at', 'nama', 'harga_per_orang', 'durasi_hari', 'status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $operator = LikeEscaper::operator(DB::connection());

        return Package::query()
            ->with(['mitra:id,name,email'])
            ->when($search, function (Builder $query, string $search) use ($operator): void {
                $escaped = LikeEscaper::contains($search);

                $query->where(function (Builder $q) use ($escaped, $operator): void {
                    $q->where('nama', $operator, $escaped)
                        ->orWhere('kode', $operator, $escaped)
                        ->orWhere('deskripsi', $operator, $escaped);
                });
            })
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($kategori, fn (Builder $q, string $k) => $q->where('kategori', $k))
            ->when($mitraId, fn (Builder $q, int $id) => $q->where('mitra_id', $id))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Package
    {
        return Package::with(['mitra:id,name,email', 'days', 'facilities'])->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findBySlug(string $slug): ?Package
    {
        return Package::with(['days', 'facilities'])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByKode(string $kode): ?Package
    {
        return Package::where('kode', $kode)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Package
    {
        return Package::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(Package $package, array $data): Package
    {
        $package->update($data);

        return $package->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Package $package): bool
    {
        return (bool) $package->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return Package::where('slug', $slug)
            ->when($exceptId, fn (Builder $q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function kodeExists(string $kode, ?int $exceptId = null): bool
    {
        return Package::where('kode', $kode)
            ->when($exceptId, fn (Builder $q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function generateKode(int $tahun): string
    {
        $prefix = "PKG-{$tahun}-";
        $lastKode = Package::withTrashed()
            ->where('kode', 'like', "{$prefix}%")
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
    public function allAktif(): Collection
    {
        return Package::aktif()->orderBy('nama')->get();
    }

    /**
     * {@inheritDoc}
     */
    public function countByStatus(): array
    {
        $counts = Package::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            PackageStatus::DRAFT->value => (int) ($counts[PackageStatus::DRAFT->value] ?? 0),
            PackageStatus::AKTIF->value => (int) ($counts[PackageStatus::AKTIF->value] ?? 0),
            PackageStatus::ARSIP->value => (int) ($counts[PackageStatus::ARSIP->value] ?? 0),
        ];
    }
}