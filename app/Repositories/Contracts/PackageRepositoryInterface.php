<?php

namespace App\Repositories\Contracts;

use App\Models\Package;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PackageRepositoryInterface
{
    /**
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     kategori?: string|null,
     *     mitra_id?: string|null,
     *     per_page?: int,
     *     sort?: string,
     *     direction?: string
     * }  $filters
     * @return LengthAwarePaginator<int, Package>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?Package;

    public function findBySlug(string $slug): ?Package;

    public function findByKode(string $kode): ?Package;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Package;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Package $package, array $data): Package;

    public function delete(Package $package): bool;

    public function slugExists(string $slug, ?int $exceptId = null): bool;

    public function kodeExists(string $kode, ?int $exceptId = null): bool;

    public function generateKode(int $tahun): string;

    /**
     * @return Collection<int, Package>
     */
    public function allAktif(): Collection;

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array;

    /**
     * @return Collection<int, Package>
     */
    public function getLatestForClient(int $limit = 4): Collection;

    /**
     * @return Collection<int, Package>
     */
    public function getBestSellerForClient(int $limit = 4): Collection;
}
