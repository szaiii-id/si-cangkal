<?php

namespace App\Support;

/**
 * Penyamaran data personal untuk halaman publik.
 */
final class PiiMask
{
    /**
     * Samarkan nama lengkap: "Budi Santoso" -> "Budi S.".
     */
    public static function name(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '-';
        }

        $parts = preg_split('/\s+/', $name) ?: [];

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0].' '.mb_substr((string) end($parts), 0, 1).'.';
    }
}
