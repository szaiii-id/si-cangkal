<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model Payment — rekam jejak pembayaran manual.
 *
 * @property int $id
 * @property int $booking_id
 * @property numeric-string $jumlah
 * @property PaymentMetode $metode
 * @property string|null $channel_code
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $catatan
 * @property PaymentGateway $gateway
 * @property string|null $gateway_order_id
 * @property string|null $gateway_request_id
 * @property string|null $gateway_status
 * @property array<string, mixed>|null $gateway_payload
 * @property Carbon|null $paid_at
 * @property Carbon|null $settled_at
 * @property Carbon|null $released_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking|null $booking
 * @property-read User|null $verifier
 *
 * @use HasFactory<PaymentFactory>
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'jumlah',
        'metode',
        'channel_code',
        'verified_by',
        'verified_at',
        'catatan',
        'gateway',
        'gateway_order_id',
        'gateway_request_id',
        'gateway_status',
        'gateway_payload',
        'paid_at',
        'settled_at',
        'released_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metode' => PaymentMetode::class,
            'jumlah' => 'decimal:2',
            'verified_at' => 'datetime',
            'gateway' => PaymentGateway::class,
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
            'settled_at' => 'datetime',
            'released_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ==========================================
    // SCOPE
    // ==========================================

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeBelumDiverifikasi(Builder $query): Builder
    {
        return $query->whereNull('verified_at');
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeSudahDiverifikasi(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    // ==========================================
    // HELPER
    // ==========================================

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function jumlahRupiah(): string
    {
        return 'Rp '.number_format((float) $this->jumlah, 0, ',', '.');
    }

    /**
     * Label metode pembayaran (Bahasa Indonesia).
     */
    public function metodeLabel(): string
    {
        return $this->metode->label();
    }
}
