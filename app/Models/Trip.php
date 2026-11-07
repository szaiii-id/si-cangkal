<?php

namespace App\Models;

use App\Enums\TripStatus;
use App\Support\Timezone;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Model Trip — instance dari package yang benar-benar jalan.
 *
 * Satu trip = satu mitra (`mitra_id`). Relasi mitra di-set sekali
 * saat trip dibuat via `TripService::createFromBooking()`.
 *
 * @property int $id
 * @property string $kode
 * @property int $booking_id
 * @property int $package_id
 * @property int $mitra_id
 * @property array<string, mixed>|null $snapshot_package
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property int $jumlah_peserta
 * @property string $timezone
 * @property TripStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking|null $booking
 * @property-read Package|null $package
 * @property-read User|null $mitra
 * @property-read Collection<int, TripDay> $days
 *
 * @use HasFactory<TripFactory>
 */
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kode',
        'booking_id',
        'package_id',
        'mitra_id',
        'snapshot_package',
        'tanggal_mulai',
        'tanggal_selesai',
        'jumlah_peserta',
        'timezone',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TripStatus::class,
            'snapshot_package' => 'array',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'jumlah_peserta' => 'integer',
        ];
    }

    // ==========================================
    // RELASI
    // ==========================================

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Mitra pemilik trip ini (1 trip = 1 mitra).
     *
     * @return BelongsTo<User, $this>
     */
    public function mitra(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mitra_id');
    }

    /**
     * @return HasMany<TripDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(TripDay::class)->orderBy('hari_ke');
    }

    // ==========================================
    // SCOPE
    // ==========================================

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', TripStatus::SCHEDULED->value);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TripStatus::ACTIVE->value);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', TripStatus::COMPLETED->value);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', TripStatus::CANCELLED->value);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TripStatus::SCHEDULED->value,
            TripStatus::ACTIVE->value,
        ]);
    }

    /**
     * Pre-filter kasar: trip scheduled yang tanggal_mulai <= hari ini.
     *
     * Catatan: perbandingan `tanggal_mulai` (DATE) dengan `today`
     * menggunakan timezone aplikasi (UTC). Untuk akurasi timezone
     * trip, verifikasi tambahan dilakukan di `TripService::activateDueTrips()`
     * via `Timezone::today($trip->timezoneOrDefault())`.
     *
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeScheduledForToday(Builder $query): Builder
    {
        return $query
            ->where('status', TripStatus::SCHEDULED->value)
            ->where('tanggal_mulai', '<=', now()->format('Y-m-d'));
    }

    // ==========================================
    // HELPER — STATUS
    // ==========================================

    public function isAktif(): bool
    {
        return $this->status->isActive();
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    /**
     * Apakah trip sudah punya mitra.
     *
     * Wajib true sebelum trip bisa diaktifkan (lihat `TripService::activate()`).
     */
    public function hasMitra(): bool
    {
        return $this->mitra_id !== null;
    }

    // ==========================================
    // HELPER — DURASI
    // ==========================================

    /**
     * Durasi hari trip (inklusif).
     */
    public function durasiHari(): int
    {
        return (int) $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }

    // ==========================================
    // HELPER — TIMEZONE
    // ==========================================

    public function timezoneOrDefault(): string
    {
        return $this->timezone ?: Timezone::DEFAULT;
    }

    /**
     * Apakah trip sedang berjalan hari ini (berdasarkan timezone trip)?
     *
     * Cek: `tanggal_mulai <= today <= tanggal_selesai`.
     * Kondisi ini sudah mencakup edge case "hari pertama" dan
     * "hari terakhir" trip.
     */
    public function isToday(): bool
    {
        $today = Timezone::today($this->timezoneOrDefault());

        return $this->tanggal_mulai->lessThanOrEqualTo($today)
            && $this->tanggal_selesai->greaterThanOrEqualTo($today);
    }

    // ==========================================
    // HELPER — SNAPSHOT
    // ==========================================

    /**
     * Snapshot package sebagai array dengan fallback aman.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->snapshot_package ?? [];
    }

    /**
     * Ambil nama package dari snapshot (dengan fallback ke relasi package).
     */
    public function snapshotNama(): string
    {
        return $this->snapshot()['nama']
            ?? $this->package?->nama
            ?? 'Paket tidak diketahui';
    }

    /**
     * Ambil durasi hari dari snapshot (dengan fallback ke `durasiHari()`).
     */
    public function snapshotDurasiHari(): int
    {
        $dariSnapshot = $this->snapshot()['durasi_hari'] ?? null;

        if ($dariSnapshot !== null) {
            return (int) $dariSnapshot;
        }

        return $this->durasiHari();
    }

    /**
     * Ambil days dari snapshot (array).
     *
     * @return array<int, array<string, mixed>>
     */
    public function snapshotDays(): array
    {
        $days = $this->snapshot()['days'] ?? [];

        return is_array($days) ? $days : [];
    }

    /**
     * Ambil facilities dari snapshot (grouped).
     *
     * @return array{include: array<int, string>, exclude: array<int, string>}
     */
    public function snapshotFacilities(): array
    {
        $facilities = $this->snapshot()['facilities'] ?? [];

        return [
            'include' => is_array($facilities['include'] ?? null) ? $facilities['include'] : [],
            'exclude' => is_array($facilities['exclude'] ?? null) ? $facilities['exclude'] : [],
        ];
    }
}