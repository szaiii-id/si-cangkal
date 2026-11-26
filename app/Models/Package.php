<?php

namespace App\Models;

use App\Enums\PackageKategori;
use App\Enums\PackageStatus;
use App\Support\Timezone;
use App\Support\UploadStorage;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Model Package — paket wisata template.
 *
 * @property int $id
 * @property int $mitra_id
 * @property string $kode
 * @property string $nama
 * @property string $slug
 * @property string|null $deskripsi
 * @property PackageKategori $kategori
 * @property int $durasi_hari
 * @property int $durasi_malam
 * @property numeric-string $harga_per_orang
 * @property numeric-string|null $harga_per_rombongan
 * @property int|null $max_peserta
 * @property string $timezone
 * @property string|null $cover_image
 * @property PackageStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $mitra
 * @property-read Collection<int, PackageDay> $days
 * @property-read Collection<int, PackageFacility> $facilities
 * @property-read Collection<int, Booking> $bookings
 * @property-read Collection<int, Review> $reviews
 *
 * @use HasFactory<PackageFactory>
 */
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $appends = ['cover_image_url'];

    /** @var list<string> */
    protected $fillable = [
        'mitra_id',
        'kode',
        'nama',
        'slug',
        'deskripsi',
        'kategori',
        'durasi_hari',
        'durasi_malam',
        'harga_per_orang',
        'harga_per_rombongan',
        'max_peserta', // nullable (hanya wajib jika rombongan)
        'timezone',
        'cover_image',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kategori' => PackageKategori::class,
            'status' => PackageStatus::class,
            'durasi_hari' => 'integer',
            'durasi_malam' => 'integer',
            'harga_per_orang' => 'decimal:2',
            'harga_per_rombongan' => 'decimal:2',
            'max_peserta' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    // ==========================================
    // RELASI
    // ==========================================

    /**
     * Mitra pemilik paket ini.
     *
     * @return BelongsTo<User, $this>
     */
    public function mitra(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mitra_id');
    }

    /**
     * @return HasMany<PackageDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(PackageDay::class)->orderBy('hari_ke');
    }

    /**
     * @return HasMany<PackageFacility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(PackageFacility::class)->orderBy('urutan');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Rata-rata rating paket (0 kalau belum ada review).
     *
     * Catatan: helper ini nyaman untuk pemakaian tunggal. Untuk halaman
     * katalog yang menampilkan banyak paket, pakai query agregat di
     * ReviewRepositoryInterface::statistikForPackage() agar tidak N+1.
     */
    public function ratingRataRata(): float
    {
        return (float) ($this->reviews()
            ->selectRaw('AVG(rating) as avg_rating')
            ->value('avg_rating') ?? 0);
    }

    public function jumlahReview(): int
    {
        return (int) $this->reviews()->count();
    }

    // ==========================================
    // SCOPE
    // ==========================================

    /**
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', PackageStatus::AKTIF->value);
    }

    /**
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PackageStatus::DRAFT->value);
    }

    /**
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeArsip(Builder $query): Builder
    {
        return $query->where('status', PackageStatus::ARSIP->value);
    }

    // ==========================================
    // HELPER — STATUS & VALIDASI HARGA
    // ==========================================

    public function isAktif(): bool
    {
        return $this->status === PackageStatus::AKTIF;
    }

    public function isDraft(): bool
    {
        return $this->status === PackageStatus::DRAFT;
    }

    public function isArsip(): bool
    {
        return $this->status === PackageStatus::ARSIP;
    }

    public function hasDays(): bool
    {
        return $this->days()->exists();
    }

    /**
     * Apakah paket ini menggunakan harga per rombongan (fixed)?
     */
    public function isRombongan(): bool
    {
        return $this->harga_per_rombongan !== null && (float) $this->harga_per_rombongan > 0;
    }

    /**
     * Hitung total harga berdasarkan jumlah peserta.
     * Aturan PRD §13.1:
     * - Jika rombongan, harga fixed (abaikan jumlah peserta).
     * - Jika per orang, kalikan dengan jumlah peserta.
     */
    public function calculateTotalHarga(int $jumlahPeserta): float
    {
        if ($this->isRombongan()) {
            return (float) $this->harga_per_rombongan;
        }

        return (float) $this->harga_per_orang * $jumlahPeserta;
    }

    public function timezoneOrDefault(): string
    {
        return $this->timezone ?: Timezone::DEFAULT;
    }

    /**
     * URL cover dari disk publik R2. Untuk data lama yang masih di disk
     * lokal, frontend punya fallback `/storage/{cover_image}`.
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if ($this->cover_image === null || $this->cover_image === '') {
            return null;
        }

        return UploadStorage::url(config('upload.public_disk'), $this->cover_image);
    }
}
