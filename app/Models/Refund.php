<?php

namespace App\Models;

use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model Refund — rekam jejak refund manual.
 *
 * @property int $id
 * @property int $booking_id
 * @property int|null $mitra_id
 * @property numeric-string $amount
 * @property string $currency
 * @property RefundReason $reason
 * @property RefundStatus $status
 * @property string|null $failure_code
 * @property numeric-string|null $refund_fee_amount
 * @property int|null $initiated_by_mitra
 * @property string|null $catatan
 * @property string|null $gateway_refund_id
 * @property string|null $gateway_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking|null $booking
 * @property-read User|null $mitra
 * @property-read User|null $mitraInitiator
 *
 * @use HasFactory<RefundFactory>
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'mitra_id',
        'amount',
        'currency',
        'reason',
        'status',
        'failure_code',
        'refund_fee_amount',
        'initiated_by_mitra',
        'catatan',
        'gateway_refund_id',
        'gateway_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reason' => RefundReason::class,
            'status' => RefundStatus::class,
            'refund_fee_amount' => 'decimal:2',
            'gateway_status' => 'string',
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
     * Mitra pemilik transaksi ini (denormalisasi).
     *
     * @return BelongsTo<User, $this>
     */
    public function mitra(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mitra_id');
    }

    /**
     * Mitra yang men-trigger refund.
     *
     * @return BelongsTo<User, $this>
     */
    public function mitraInitiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_mitra');
    }

    // ==========================================
    // SCOPE
    // ==========================================

    /**
     * @param  Builder<Refund>  $query
     * @return Builder<Refund>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RefundStatus::PENDING->value);
    }

    /**
     * @param  Builder<Refund>  $query
     * @return Builder<Refund>
     */
    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', RefundStatus::SUCCEEDED->value);
    }

    /**
     * @param  Builder<Refund>  $query
     * @return Builder<Refund>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', RefundStatus::FAILED->value);
    }

    /**
     * @param  Builder<Refund>  $query
     * @return Builder<Refund>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RefundStatus::PENDING->value,
            RefundStatus::SUCCEEDED->value,
        ]);
    }

    // ==========================================
    // HELPER — STATUS
    // ==========================================

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isSucceeded(): bool
    {
        return $this->status->isSucceeded();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    public function canRetry(): bool
    {
        return $this->status->canRetry();
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }

    // ==========================================
    // HELPER — ALASAN
    // ==========================================

    public function reasonLabel(): string
    {
        return $this->reason->label();
    }

    // ==========================================
    // HELPER — NOMINAL
    // ==========================================

    public function amountRupiah(): string
    {
        return 'Rp '.number_format((float) $this->amount, 0, ',', '.');
    }

    public function refundFeeRupiah(): string
    {
        if ($this->refund_fee_amount === null || (float) $this->refund_fee_amount <= 0) {
            return '-';
        }

        return 'Rp '.number_format((float) $this->refund_fee_amount, 0, ',', '.');
    }
}
