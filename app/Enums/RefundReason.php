<?php

namespace App\Enums;

/**
 * Alasan refund (Transfer Manual).
 */
enum RefundReason: string
{
    case CANCELLATION = 'CANCELLATION';
    case REQUESTED_BY_CUSTOMER = 'REQUESTED_BY_CUSTOMER';
    case DUPLICATE = 'DUPLICATE';
    case FRAUDULENT = 'FRAUDULENT';
    case OTHERS = 'OTHERS';

    public function label(): string
    {
        return match ($this) {
            self::CANCELLATION => 'Pembatalan oleh Mitra',
            self::REQUESTED_BY_CUSTOMER => 'Permintaan Client',
            self::DUPLICATE => 'Duplikasi Transaksi',
            self::FRAUDULENT => 'Terindikasi Fraud',
            self::OTHERS => 'Lainnya',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CANCELLATION => 'Booking dibatalkan oleh mitra (trip batal, force majeure, dll).',
            self::REQUESTED_BY_CUSTOMER => 'Client mengajukan pembatalan dan disetujui mitra.',
            self::DUPLICATE => 'Pembayaran terjadi lebih dari sekali untuk booking yang sama.',
            self::FRAUDULENT => 'Transaksi terindikasi penipuan atau penyalahgunaan.',
            self::OTHERS => 'Alasan lain - wajib isi catatan internal untuk audit.',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::CANCELLATION => 'bg-blue/10 text-blue-dark border border-blue/20',
            self::REQUESTED_BY_CUSTOMER => 'bg-green/10 text-green-dark border border-green/20',
            self::DUPLICATE => 'bg-sun/10 text-sun-dark border border-sun/20',
            self::FRAUDULENT => 'bg-red-50 text-red-700 border border-red-200',
            self::OTHERS => 'bg-gray-100 text-gray-700 border border-gray-200',
        };
    }

    public function requiresCatatan(): bool
    {
        return $this === self::OTHERS;
    }

    public function isFlagged(): bool
    {
        return in_array($this, [self::FRAUDULENT, self::DUPLICATE], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
