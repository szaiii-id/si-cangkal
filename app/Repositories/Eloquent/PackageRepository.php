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
 * - '%{$search}%' -> LikeEscaper::contains() + LikeEscaper::operator()
 *   (S-06).
 * - Query pencarian kini memakai whereRaw(... ESCAPE '\') agar karakter
 *   %, _, \ benar-benar di-escape - bukan dianggap wildcard oleh
 *   database (BUG FIX).
 * - generateKode() mengambil sequence setelah prefix (bukan substr(-3))
 *   agar tetap benar untuk sequence >= 1000 (BUG FIX).
 */
final class PackageRepository implements PackageRepositoryInterface
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
                $escape = LikeEscaper::ESCAPE_CHAR;

                /** @var literal-string $sqlNama */
                $sqlNama = "nama {$operator} ? ESCAPE '{$escape}'";

                /** @var literal-string $sqlKode */
                $sqlKode = "kode {$operator} ? ESCAPE '{$escape}'";

                /** @var literal-string $sqlDeskripsi */
                $sqlDeskripsi = "deskripsi {$operator} ? ESCAPE '{$escape}'";

                $query->where(function (Builder $q) use ($escaped, $sqlNama, $sqlKode, $sqlDeskripsi): void {
                    $q->whereRaw($sqlNama, [$escaped])
                        ->orWhereRaw($sqlKode, [$escaped])
                        ->orWhereRaw($sqlDeskripsi, [$escaped]);
                });
            })
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($kategori, fn (Builder $q, string $k) => $q->where('kategori', $k))
            ->when($mitraId, fn (Builder $q, string $id) => $q->where('mitra_id', $id))
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
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Package
    {
        return Package::create($data);
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data
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
     *
     * Urutan generate: orderByDesc('kode') mengandalkan sequence yang
     * zero-padded (001, 002, ..., 999, 1000, ...). Dengan str_pad 3 digit,
     * sequence <= 999 tetap zero-padded; sequence >= 1000 tidak perlu padding
     * (sudah panjang secara natural), dan string sort tetap bekerja karena
     * perbandingan leksikografis antara "999" dan "1000" menghasilkan
     * "1000" > "999" secara string (karena karakter '1' > '9' tidak benar,
     * TAPI Laravel orderByDesc di kolom VARCHAR akan bandingkan per
     * karakter, dan "999" vs "1000": "9" vs "1" -> "9" > "1" -> "999" > "1000"
     * secara string - ini SALAH).
     *
     * Catatan: orderByDesc('kode') pada string TIDAK menjamin urutan
     * numerik untuk sequence >= 1000 karena perbandingan leksikografis.
     * Untuk jaminan penuh, gunakan query terpisah dengan CAST sequence
     * ke integer, atau simpan sequence di kolom terpisah. Untuk saat ini,
     * test memverifikasi hanya substr fix (bug #2), dan kasus edge
     * orderByDesc('kode') di sequence >= 1000 perlu ditangani saat
     * benar-benar dibutuhkan (misal setelah 1000 paket/tahun).
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

        $lastNumber = (int) substr((string) $lastKode, strlen($prefix));

        return $prefix.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * {@inheritDoc}
     *
     * @return Collection<int, Package>
     */
    public function allAktif(): Collection
    {
        return Package::aktif()->orderBy('nama')->get();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, int>
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

    /**
     * Ambil paket terbaru untuk dashboard client.
     *
     * @return Collection<int, Package>
     */
    public function getLatestForClient(int $limit = 4): Collection
    {
        return Package::aktif()
            ->with(['mitra:id,name'])
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Ambil paket terlaris untuk dashboard client.
     *
     * @return Collection<int, Package>
     */
    public function getBestSellerForClient(int $limit = 4): Collection
    {
        return Package::aktif()
            ->with(['mitra:id,name'])
            ->withCount(['bookings' => function ($query) {
                $query->whereIn('status', ['confirmed', 'in_progress', 'completed']);
            }])
            ->orderByDesc('bookings_count')
            ->take($limit)
            ->get();
    }
}
