<?php

namespace App\Enums;

enum PaymentMetode: string
{
    case TRANSFER_MANUAL = 'transfer_manual';
    case XENDIT = 'xendit';

    public function label(): string
    {
        return match ($this) {
            self::TRANSFER_MANUAL => 'Transfer Manual',
            self::XENDIT => 'Xendit',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::TRANSFER_MANUAL => 'bg-blue/10 text-blue-dark border border-blue/20',
            self::XENDIT => 'bg-purple/10 text-purple-dark border border-purple/20',
        };
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
