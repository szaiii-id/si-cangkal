<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Booking — pesanan client untuk sebuah paket wisata.
 *
 * @property string $id
 * @property string $kode
 * @property int $package_id
 * @property string $user_id
 * @property string|null $mitra_id
 * @property CarbonImmutable|null $tanggal_keberangkatan
 * @property int $jumlah_peserta
 * @property string $total_harga
 * @property BookingStatus $status
 * @property string|null $payment_proof_path
 * @property CarbonImmutable|null $payment_verified_at
 * @property string|null $payment_reject_reason
 * @property string $sumber
 * @property CarbonImmutable|null $expired_at
 * @property string|null $catatan
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Package|null $package
 * @property-read User|null $client
 * @property-read User|null $mitra
 * @property-read Collection<int, BookingParticipant> $participants
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Refund> $refunds
 * @property-read Trip|null $trip
 *
 * @use HasFactory<BookingFactory>
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sumber' => 'aplikasi',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kode',
        'sumber',
        'package_id',
        'user_id',
        'mitra_id',
        'tanggal_keberangkatan',
        'jumlah_peserta',
        'total_harga',
        'status',
        'payment_proof_path',
        'payment_verified_at',
        'payment_reject_reason',
        'expired_at',
        'catatan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'tanggal_keberangkatan' => 'date',
            'expired_at' => 'datetime',
            'payment_verified_at' => 'datetime',
            'jumlah_peserta' => 'integer',
            'total_harga' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    // ==========================================
    // RELASI
    // ==========================================

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Client yang membuat booking ini.
     *
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mitra pemilik paket/trip ini (denormalisasi).
     *
     * @return BelongsTo<User, $this>
     */
    public function mitra(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mitra_id');
    }

    /**
     * @return HasMany<BookingParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(BookingParticipant::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<BookingPaymentLink, $this>
     */
    public function paymentLinks(): HasMany
    {
        return $this->hasMany(BookingPaymentLink::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return HasOne<Trip, $this>
     */
    public function trip(): HasOne
    {
        return $this->hasOne(Trip::class);
    }

    // ==========================================
    // SCOPE
    // ==========================================

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::PENDING_PAYMENT->value);
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopePaymentVerified(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::PAYMENT_VERIFIED->value);
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::CONFIRMED->value);
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            BookingStatus::COMPLETED->value,
            BookingStatus::CANCELLED->value,
        ]);
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->where('status', BookingStatus::PENDING_PAYMENT->value)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now());
    }

    // ==========================================
    // HELPER — STATUS
    // ==========================================

    public function canBeCancelledByClient(): bool
    {
        return $this->status->canBeCancelledByClient();
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    public function isExpired(): bool
    {
        if ($this->expired_at === null) {
            return false;
        }

        return $this->status === BookingStatus::PENDING_PAYMENT
            && $this->expired_at->isPast();
    }

    // ==========================================
    // HELPER — HARGA
    // ==========================================

    public function calculateTotalHarga(): float
    {
        $package = $this->package;

        if ($package === null) {
            return 0.0;
        }

        return $package->calculateTotalHarga($this->jumlah_peserta);
    }

    public function totalHargaRupiah(): string
    {
        return 'Rp '.number_format((float) $this->total_harga, 0, ',', '.');
    }

    public function hoursUntilExpired(): ?int
    {
        if ($this->expired_at === null || $this->status !== BookingStatus::PENDING_PAYMENT) {
            return null;
        }

        $diff = now()->diffInHours($this->expired_at, false);

        return max(0, (int) $diff);
    }

    // ==========================================
    // HELPER — TIMEZONE
    // ==========================================

    public function tanggalKeberangkatanLocal(): string
    {
        $value = $this->tanggal_keberangkatan;

        return $value?->format('Y-m-d') ?? '';
    }

    public function timezone(): string
    {
        return $this->package?->timezone ?: Timezone::DEFAULT;
    }
}
