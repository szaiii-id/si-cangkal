<?php

namespace App\Services\Payment\Doku;

/**
 * Tanda tangan request dan verifikasi HTTP Notification DOKU.
 *
 * DOKU memakai HMAC-SHA256 dengan Secret Key untuk skema simetris yang
 * dipakai Checkout dan HTTP Notification. Komponen string-to-sign harus
 * dibangun dari RAW body—jangan parse JSON sebelum menghitung Digest.
 */
final class DokuSignature
{
    private const PREFIX = 'HMACSHA256=';

    /**
     * Bangun header Signature untuk request keluar ke DOKU.
     */
    public static function forRequest(
        string $targetPath,
        string $body,
        string $requestId,
        string $timestamp,
    ): string {
        $stringToSign = self::stringToSign(
            clientId: (string) config('payment.doku.client_id'),
            requestId: $requestId,
            timestamp: $timestamp,
            targetPath: $targetPath,
            digest: self::digest($body),
        );

        return self::PREFIX.base64_encode(
            hash_hmac('sha256', $stringToSign, self::secret(), true),
        );
    }

    /**
     * Verifikasi HTTP Notification dari DOKU.
     *
     * @param  array<string, mixed>  $headers
     */
    public static function verifyNotification(
        string $rawBody,
        array $headers,
        string $targetPath,
    ): bool {
        $signature = $headers['Signature'] ?? null;
        $clientId = $headers['Client-Id'] ?? null;
        $requestId = $headers['Request-Id'] ?? null;
        $timestamp = $headers['Request-Timestamp'] ?? null;

        if (! is_string($signature) || ! is_string($clientId)
            || ! is_string($requestId) || ! is_string($timestamp)) {
            return false;
        }

        if (! str_starts_with($signature, self::PREFIX)) {
            return false;
        }

        // Tolak notifikasi yang mengaku dari Client-Id lain sebelum
        // membandingkan tanda tangan.
        if (! hash_equals((string) config('payment.doku.client_id'), $clientId)) {
            return false;
        }

        $expected = self::forRequest(
            targetPath: $targetPath,
            body: $rawBody,
            requestId: $requestId,
            timestamp: $timestamp,
        );

        return hash_equals($expected, $signature);
    }

    private static function digest(string $body): string
    {
        return base64_encode(hash('sha256', $body, true));
    }

    private static function stringToSign(
        string $clientId,
        string $requestId,
        string $timestamp,
        string $targetPath,
        string $digest,
    ): string {
        return implode("\n", [
            'Client-Id:'.$clientId,
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$timestamp,
            'Request-Target:'.$targetPath,
            'Digest:'.$digest,
        ]);
    }

    private static function secret(): string
    {
        return (string) config('payment.doku.secret_key');
    }
}
