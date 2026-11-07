<?php

namespace App\Enums;

/**
 * Status siklus hidup sebuah refund (Transfer Manual).
 */
enum RefundStatus: string
{
    case PENDING = 'PENDING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Sedang Diproses',
            self::SUCCEEDED => 'Berhasil',
            self::FAILED => 'Gagal',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-sun/10 text-sun-dark border border-sun/20',
            self::SUCCEEDED => 'bg-green/10 text-green-dark border border-green/20',
            self::FAILED => 'bg-red-50 text-red-700 border border-red-200',
            self::CANCELLED => 'bg-gray-100 text-gray-700 border border-gray-200',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCEEDED, self::FAILED, self::CANCELLED], true);
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isSucceeded(): bool
    {
        return $this === self::SUCCEEDED;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}