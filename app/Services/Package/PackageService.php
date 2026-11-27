<?php

namespace App\Services\Package;

use App\Enums\PackageStatus;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\PackageFacility;
use App\Models\User;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Support\AuditLogger;
use App\Support\UploadStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PackageService
{
    public function __construct(
        private readonly PackageRepositoryInterface $packageRepository,
    ) {}

    // ==========================================
    // READ
    // ==========================================

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Package>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->packageRepository->paginate($filters, $perPage);
    }

    public function findDetail(int $id): Package
    {
        $package = $this->packageRepository->findById($id);

        if ($package === null) {
            throw ValidationException::withMessages([
                'package' => ['Paket tidak ditemukan.'],
            ]);
        }

        return $package;
    }

    /** @return Collection<int, Package> */
    public function allAktif(): Collection
    {
        return $this->packageRepository->allAktif();
    }

    /** @return Collection<int, Package> */
    public function getLatestForClient(int $limit = 4): Collection
    {
        return $this->packageRepository->getLatestForClient($limit);
    }

    /** @return Collection<int, Package> */
    public function getBestSellerForClient(int $limit = 4): Collection
    {
        return $this->packageRepository->getBestSellerForClient($limit);
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        return $this->packageRepository->countByStatus();
    }

    // ==========================================
    // CREATE
    // ==========================================

    /** @param array<string, mixed> $data */
    public function create(array $data, User $mitra): Package
    {
        $this->validate($data);

        return DB::transaction(function () use ($data, $mitra): Package {
            $kode = $this->packageRepository->generateKode((int) now()->year);
            $slug = $this->generateUniqueSlug((string) $data['nama']);

            $coverPath = null;
            if (isset($data['cover_image']) && $data['cover_image'] instanceof UploadedFile) {
                $coverPath = UploadStorage::put(config('upload.public_disk'), 'packages', $data['cover_image']);
            }

            $package = $this->packageRepository->create([
                'mitra_id' => $mitra->id,
                'kode' => $kode,
                'nama' => $data['nama'],
                'slug' => $slug,
                'deskripsi' => $data['deskripsi'] ?? null,
                'kategori' => $data['kategori'],
                'durasi_hari' => $data['durasi_hari'],
                'durasi_malam' => $data['durasi_malam'],
                'harga_per_orang' => $data['harga_per_orang'],
                'harga_per_rombongan' => $data['harga_per_rombongan'] ?? null,
                'max_peserta' => $data['max_peserta'] ?? null,
                'timezone' => $data['timezone'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'cover_image' => $coverPath,
                'status' => PackageStatus::DRAFT->value,
            ]);

            AuditLogger::log('package.created', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'package_id' => $package->id,
                'package_kode' => $package->kode,
                'nama' => $package->nama,
            ]);

            return $package;
        });
    }

    // ==========================================
    // UPDATE
    // ==========================================

    /** @param array<string, mixed> $data */
    public function update(Package $package, array $data): Package
    {
        $this->validate($data, $package->id);

        return DB::transaction(function () use ($package, $data): Package {
            $updateData = [
                'nama' => $data['nama'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'kategori' => $data['kategori'],
                'durasi_hari' => $data['durasi_hari'],
                'durasi_malam' => $data['durasi_malam'],
                'harga_per_orang' => $data['harga_per_orang'],
                'harga_per_rombongan' => $data['harga_per_rombongan'] ?? null,
                'max_peserta' => $data['max_peserta'] ?? null,
                'timezone' => $data['timezone'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ];

            if ($package->nama !== $data['nama']) {
                $updateData['slug'] = $this->generateUniqueSlug((string) $data['nama'], $package->id);
            }

            if (isset($data['cover_image']) && $data['cover_image'] instanceof UploadedFile) {
                if ($package->cover_image !== null && $package->cover_image !== '') {
                    UploadStorage::deleteAfterCommit(config('upload.public_disk'), $package->cover_image);
                }
                $updateData['cover_image'] = UploadStorage::put(config('upload.public_disk'), 'packages', $data['cover_image']);
            } elseif (isset($data['remove_cover_image']) && $data['remove_cover_image'] == '1') {
                if ($package->cover_image !== null && $package->cover_image !== '') {
                    UploadStorage::deleteAfterCommit(config('upload.public_disk'), $package->cover_image);
                }
                $updateData['cover_image'] = null;
            }

            $updated = $this->packageRepository->update($package, $updateData);

            AuditLogger::log('package.updated', [
                'actor_id' => auth()->id(),
                'package_id' => $updated->id,
                'package_kode' => $updated->kode,
            ]);

            return $updated;
        });
    }

    /**
     * Update paket + sinkronisasi hari & fasilitas sekaligus.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateWithRelations(Package $package, array $data): Package
    {
        $this->validate($data, $package->id);

        return DB::transaction(function () use ($package, $data): Package {
            $updateData = [
                'nama' => $data['nama'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'kategori' => $data['kategori'],
                'durasi_hari' => $data['durasi_hari'],
                'durasi_malam' => $data['durasi_malam'],
                'harga_per_orang' => $data['harga_per_orang'],
                'harga_per_rombongan' => $data['harga_per_rombongan'] ?: null,
                'max_peserta' => $data['harga_per_rombongan'] ? ($data['max_peserta'] ?? null) : null,
                'timezone' => $data['timezone'],
            ];

            if ($package->nama !== $data['nama']) {
                $updateData['slug'] = $this->generateUniqueSlug((string) $data['nama'], $package->id);
            }

            if (isset($data['cover_image']) && $data['cover_image'] instanceof UploadedFile) {
                if ($package->cover_image !== null && $package->cover_image !== '') {
                    UploadStorage::deleteAfterCommit(config('upload.public_disk'), $package->cover_image);
                }
                $updateData['cover_image'] = UploadStorage::put(config('upload.public_disk'), 'packages', $data['cover_image']);
            } elseif (isset($data['remove_cover_image']) && $data['remove_cover_image'] == '1') {
                if ($package->cover_image !== null && $package->cover_image !== '') {
                    UploadStorage::deleteAfterCommit(config('upload.public_disk'), $package->cover_image);
                }
                $updateData['cover_image'] = null;
            }

            $package = $this->packageRepository->update($package, $updateData);

            $this->syncDays($package, $data['days'] ?? null);

            $this->syncFacilities($package, $data['facilities'] ?? null);

            AuditLogger::log('package.updated_with_relations', [
                'actor_id' => auth()->id(),
                'package_id' => $package->id,
                'package_kode' => $package->kode,
                'days_count' => is_string($data['days'] ?? null) ? count(json_decode($data['days'], true) ?? []) : 0,
                'facilities_count' => is_string($data['facilities'] ?? null) ? count(json_decode($data['facilities'], true) ?? []) : 0,
            ]);

            return $package->refresh()->load(['days', 'facilities']);
        });
    }

    // ==========================================
    // STATUS TRANSITIONS
    // ==========================================

    public function publish(Package $package): Package
    {
        if ($package->status === PackageStatus::AKTIF) {
            throw ValidationException::withMessages([
                'status' => ['Paket sudah aktif.'],
            ]);
        }

        if ($package->days()->count() < $package->durasi_hari) {
            throw ValidationException::withMessages([
                'status' => [
                    "Itinerary belum lengkap. Paket ini untuk {$package->durasi_hari} hari, ".
                    "tapi baru ada {$package->days()->count()} hari terisi.",
                ],
            ]);
        }

        if (! $package->facilities()->where('tipe', 'include')->exists()) {
            throw ValidationException::withMessages([
                'status' => ['Paket harus punya minimal 1 fasilitas "include" sebelum dipublish.'],
            ]);
        }

        if (! $package->facilities()->where('tipe', 'exclude')->exists()) {
            throw ValidationException::withMessages([
                'status' => ['Paket harus punya minimal 1 fasilitas "exclude" sebelum dipublish.'],
            ]);
        }

        $package = $this->packageRepository->update($package, [
            'status' => PackageStatus::AKTIF->value,
        ]);

        AuditLogger::log('package.published', [
            'actor_id' => auth()->id(),
            'package_id' => $package->id,
            'package_kode' => $package->kode,
        ]);

        return $package;
    }

    public function archive(Package $package): Package
    {
        if ($package->status === PackageStatus::ARSIP) {
            throw ValidationException::withMessages([
                'status' => ['Paket sudah diarsipkan.'],
            ]);
        }

        $package = $this->packageRepository->update($package, [
            'status' => PackageStatus::ARSIP->value,
        ]);

        AuditLogger::log('package.archived', [
            'actor_id' => auth()->id(),
            'package_id' => $package->id,
            'package_kode' => $package->kode,
        ]);

        return $package;
    }

    public function restore(Package $package): Package
    {
        if ($package->status !== PackageStatus::ARSIP) {
            throw ValidationException::withMessages([
                'status' => ['Hanya paket yang diarsipkan bisa direstore.'],
            ]);
        }

        $package = $this->packageRepository->update($package, [
            'status' => PackageStatus::DRAFT->value,
        ]);

        AuditLogger::log('package.restored', [
            'actor_id' => auth()->id(),
            'package_id' => $package->id,
            'package_kode' => $package->kode,
        ]);

        return $package;
    }

    // ==========================================
    // DELETE
    // ==========================================

    public function delete(Package $package): void
    {
        if ($package->bookings()->exists()) {
            throw ValidationException::withMessages([
                'package' => ['Paket yang sudah punya booking tidak bisa dihapus. Arsipkan saja.'],
            ]);
        }

        AuditLogger::log('package.deleted', [
            'actor_id' => auth()->id(),
            'package_id' => $package->id,
            'package_kode' => $package->kode,
        ]);

        $this->packageRepository->delete($package);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function generateUniqueSlug(string $nama, ?int $exceptId = null): string
    {
        $base = Str::slug($nama);
        $slug = $base;
        $counter = 2;

        while ($this->packageRepository->slugExists($slug, $exceptId)) {
            $slug = "{$base}-{$counter}";
            $counter++;

            if ($counter > 1000) {
                do {
                    $slug = $base.'-'.Str::random(6);
                } while ($this->packageRepository->slugExists($slug, $exceptId));
                break;
            }
        }

        return $slug;
    }

    private function syncDays(Package $package, ?string $rawJson): void
    {
        $package->days()->delete();

        if ($rawJson === null || $rawJson === '') {
            return;
        }

        $days = json_decode($rawJson, true);

        if (! is_array($days)) {
            return;
        }

        foreach (array_values($days) as $index => $day) {
            if (! is_array($day) || ! isset($day['judul'])) {
                continue;
            }

            PackageDay::create([
                'package_id' => $package->id,
                'hari_ke' => $index + 1,
                'judul' => (string) $day['judul'],
                'deskripsi' => $day['deskripsi'] ?? null,
                'jam_mulai' => $day['jam_mulai'] ?? null,
                'jam_selesai' => $day['jam_selesai'] ?? null,
                'lokasi' => $day['lokasi'] ?? null,
            ]);
        }
    }

    private function syncFacilities(Package $package, ?string $rawJson): void
    {
        $package->facilities()->delete();

        if ($rawJson === null || $rawJson === '') {
            return;
        }

        $facilities = json_decode($rawJson, true);

        if (! is_array($facilities)) {
            return;
        }

        $urutan = ['include' => 0, 'exclude' => 0];

        foreach ($facilities as $facility) {
            if (! is_array($facility) || ! isset($facility['tipe'], $facility['nama'])) {
                continue;
            }

            $tipe = (string) $facility['tipe'];

            if (! isset($urutan[$tipe])) {
                continue;
            }

            $urutan[$tipe]++;

            PackageFacility::create([
                'package_id' => $package->id,
                'tipe' => $tipe,
                'nama' => (string) $facility['nama'],
                'urutan' => $urutan[$tipe],
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data, ?int $exceptId = null): void
    {
        $durasiHari = (int) ($data['durasi_hari'] ?? 1);
        $durasiMalam = (int) ($data['durasi_malam'] ?? 0);

        if ($durasiMalam >= $durasiHari && $durasiHari > 0) {
            throw ValidationException::withMessages([
                'durasi_malam' => ['Durasi malam harus kurang dari durasi hari (contoh: 2D1N, 3D2N).'],
            ]);
        }

        $hargaPerRombongan = $data['harga_per_rombongan'] ?? null;

        if ($hargaPerRombongan !== null && (float) $hargaPerRombongan > 0) {
            if (empty($data['max_peserta'])) {
                throw ValidationException::withMessages([
                    'max_peserta' => ['Max peserta wajib diisi untuk paket rombongan.'],
                ]);
            }
        }
    }
}
