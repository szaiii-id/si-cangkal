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
 *   PENTING — Klausa ESCAPE:
 * Laravel `where('col', 'like', $pattern)` TIDAK otomatis menambahkan
 * klausa `ESCAPE '\'`. Tanpa klausa tersebut, karakter `\` yang kita
 * sisipkan lewat `escape()` akan dianggap sebagai backslash literal,
 * BUKAN escape character — sehingga `\%` justru mencari string literal
 * `\%`, bukan `%`. Akibatnya escape TIDAK BERFUNGSI.
 *
 * Karena itu, ketika memakai helper ini, WAJIB gunakan `whereRaw`
 * dengan klausa ESCAPE, misalnya:
 *
 *     $pattern = LikeEscaper::contains($input);
 *     $operator = LikeEscaper::operator(DB::connection());
 *     $escape = LikeEscaper::ESCAPE_CHAR;
 *
 *     $query->whereRaw(
 *         "nama {$operator} ? ESCAPE '{$escape}'",
 *         [$pattern]
 *     );
 *
 * Catatan portabilitas:
 * - PostgreSQL: membutuhkan `ESCAPE '\\'` eksplisit (tidak ada default).
 * - MySQL: default escape adalah `\`, tetapi eksplisit tetap aman.
 * - SQLite: membutuhkan `ESCAPE '\\'` eksplisit (tidak ada default).
 */
final class LikeEscaper
{
    /**
     * Karakter escape untuk klausa LIKE (standar SQL).
     *
     * Wajib disisipkan via `ESCAPE '<char>'` agar `\` di dalam pola
     * benar-benar berfungsi sebagai escape character.
     */
    public const ESCAPE_CHAR = '\\';

    /**
     * Escape karakter khusus LIKE pada nilai input.
     *
     * Urutan replace PENTING: `\` harus di-escape PERTAMA, agar
     * backslash yang kita sisipkan untuk `%` dan `_` tidak
     * ikut ter-escape dua kali.
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
