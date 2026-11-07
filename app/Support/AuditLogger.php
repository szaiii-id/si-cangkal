<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Helper audit log terpusat untuk aksi penting pada sistem.
 *
 * Tujuan (S-16, OWASP A09: Security Logging and Monitoring Failures):
 * - Merekam siapa melakukan apa, kapan, dan terhadap entitas mana.
 * - Memisahkan audit log dari log aplikasi biasa (channel `audit`).
 * - Menjaga format konsisten sehingga mudah diparse oleh SIEM.
 *
 * Prinsip:
 * - Setiap pemanggilan HARUS menyertakan `$action` berupa string stabil
 *   berformat `domain.event` (contoh: `booking.verified`).
 * - `$context` WAJIB berisi `actor_id` (id user yang melakukan aksi)
 *   atau `null` bila aksi berasal dari sistem/scheduler.
 * - Nilai sensitif (password, token, nomor rekening penuh, isi file)
 *   DILARANG dimasukkan ke context.
 *
 * Pemakaian:
 *
 *     AuditLogger::log('booking.verified', [
 *         'actor_id' => $mitra->id,
 *         'booking_id' => $booking->id,
 *         'booking_kode' => $booking->kode,
 *     ]);
 *
 *     AuditLogger::system('booking.expired', [
 *         'booking_id' => $booking->id,
 *     ]);
 */
final class AuditLogger
{
    /**
     * Nama channel log untuk audit.
     *
     * Harus sinkron dengan `config/logging.php` → `channels.audit`.
     */
    public const CHANNEL = 'audit';

    /**
     * Catat aksi yang dilakukan oleh user (actor eksplisit).
     *
     * @param  string  $action  Nama aksi stabil berformat `domain.event`.
     * @param  array<string, mixed>  $context  Data konteks yang aman untuk dilog.
     * @return void
     */
    public static function log(string $action, array $context = []): void
    {
        self::writer()->info($action, self::normalize($context));
    }

    /**
     * Catat aksi yang dilakukan oleh sistem (scheduler, job, event).
     *
     * Menambahkan penanda `actor_type = system` agar dapat dibedakan
     * dari aksi user pada saat query log.
     *
     * @param  string  $action
     * @param  array<string, mixed>  $context
     * @return void
     */
    public static function system(string $action, array $context = []): void
    {
        $context['actor_type'] = 'system';
        $context['actor_id'] = null;

        self::writer()->info($action, self::normalize($context));
    }

    /**
     * Ambil logger untuk channel audit.
     *
     * Bila channel `audit` belum terdaftar di `config/logging.php`
     * (mis. saat migrasi bertahap), fallback ke channel default agar
     * tidak ada log yang hilang.
     *
     * @return \Psr\Log\LoggerInterface
     */
    private static function writer(): LoggerInterface
    {
        $channels = config('logging.channels', []);

        if (is_array($channels) && array_key_exists(self::CHANNEL, $channels)) {
            return Log::channel(self::CHANNEL);
        }

        return Log::driver();
    }

    /**
     * Normalisasi context agar konsisten dan aman.
     *
     * - `actor_id` default `null` bila tidak disediakan.
     * - Menambahkan `occurred_at` (ISO 8601, UTC) bila belum ada.
     * - Membuang key yang tidak boleh masuk log (`password`, `token`, dll).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function normalize(array $context): array
    {
        if (! array_key_exists('actor_id', $context)) {
            $context['actor_id'] = null;
        }

        if (! array_key_exists('occurred_at', $context)) {
            $context['occurred_at'] = now()->toIso8601String();
        }

        foreach (self::forbiddenKeys() as $key) {
            unset($context[$key]);
        }

        return $context;
    }

    /**
     * Daftar key yang tidak boleh masuk audit log.
     *
     * @return list<string>
     */
    private static function forbiddenKeys(): array
    {
        return [
            'password',
            'password_confirmation',
            'old_password',
            'new_password',
            'token',
            'remember_token',
            'api_key',
            'secret',
            'authorization',
        ];
    }
}