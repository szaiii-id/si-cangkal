<?php

namespace App\Enums;

/**
 * Metode pembayaran (Full Xendit).
 *
 * - xendit : Pembayaran otomatis via Xendit (Invoice/VA/QRIS/E-Wallet).
 *
 * Catatan:
 * - Metode manual (transfer_bank, tunai, lainnya) sudah dihapus.
 * - Semua pembayaran sekarang full otomatis via Xendit.
 */
enum PaymentMetode: string
{
    case XENDIT = 'xendit';

    /**
     * Label Bahasa Indonesia untuk tampilan UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::XENDIT => 'Xendit (Otomatis)',
        };
    }

    /**
     * Warna badge untuk tampilan UI.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::XENDIT => 'bg-purple/10 text-purple-dark border border-purple/20',
        };
    }

    /**
     * Daftar semua value.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Daftar [value => label] untuk dropdown di form.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}