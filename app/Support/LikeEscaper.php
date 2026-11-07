<?php

namespace App\Support;

use Illuminate\Database\Connection;

/**
 * Helper untuk meng-escape karakter khusus pada query LIKE.
 *
 * Mencegah SQL wildcard injection (S-06, OWASP A03: Injection).
 *
 * Karakter yang di-escape:
 * - backslash (\) — escape character default di LIKE.
 * - percent (%) — wildcard "nol atau lebih karakter".
 * - underscore (_) — wildcard "tepat satu karakter".
 *
 * Penggunaan:
 *
 *     $term = LikeEscaper::escape($request->input('search', ''));
 *     $operator = LikeEscaper::operator($connection);
 *     $query->where('nama', $operator, "%{$term}%");
 *
 * Catatan:
 * - Fungsi ini hanya menyiapkan nilai untuk di-escape; parameter binding
 *   tetap dilakukan oleh query builder Laravel sehingga nilai tidak
 *   pernah diinterpolasi langsung ke SQL string.
 * - Operator `ilike` dipakai pada PostgreSQL agar case-insensitive.
 *   Driver lain memakai `like` (case-insensitive secara default pada
 *   MySQL dengan collation *_ci dan SQLite dengan `case_sensitive_like = off`).
 */
final class LikeEscaper
{
    /**
     * Escape karakter khusus LIKE pada nilai input.
     *
     * @param  string  $value  Nilai mentah dari input pengguna.
     * @return string Nilai yang aman untuk dijadikan pola LIKE.
     */
    public static function escape(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value,
        );
    }

    /**
     * Tentukan operator LIKE yang sesuai dengan driver koneksi database.
     *
     * - PostgreSQL: `ilike` (case-insensitive).
     * - Lainnya: `like`.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @return string
     */
    public static function operator(Connection $connection): string
    {
        return $connection->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Bungkus nilai yang sudah di-escape menjadi pola "contains".
     *
     * @param  string  $value  Nilai mentah dari input pengguna.
     * @return string Pola `%<escaped>%` siap dipakai pada query LIKE.
     */
    public static function contains(string $value): string
    {
        return '%'.self::escape($value).'%';
    }
}