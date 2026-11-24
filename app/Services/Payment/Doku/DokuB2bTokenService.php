<?php

namespace App\Services\Payment\Doku;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Token B2B untuk API SNAP / Sub Account DOKU.
 *
 * Berbeda dengan Checkout yang memakai HMAC simetris, API SNAP mensyaratkan
 * access token OAuth2 dengan tanda tangan SHA256withRSA. Token disimpan
 * di cache sampai 60 detik sebelum kedaluwarsa.
 */
final class DokuB2bTokenService
{
    public function token(): string
    {
        $clientId = (string) config('payment.doku.client_id');
        $cacheKey = 'doku:b2b-token:'.$clientId;

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $path = (string) config('payment.doku.b2b_token_path');
        $url = rtrim((string) config('payment.doku.base_url'), '/').$path;

        $response = Http::withHeaders([
            'X-TIMESTAMP' => $timestamp,
            'X-CLIENT-KEY' => $clientId,
            'X-SIGNATURE' => $this->signature($clientId, $timestamp),
            'Content-Type' => 'application/json',
        ])->post($url, ['grantType' => 'client_credentials']);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gagal mendapatkan token B2B DOKU: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $token = (string) data_get($body, 'accessToken', '');

        if ($token === '') {
            throw new RuntimeException('DOKU tidak mengembalikan accessToken.');
        }

        $expiresIn = (int) data_get($body, 'expiresIn', 900);

        Cache::put(
            $cacheKey,
            $token,
            now()->addSeconds(max(60, $expiresIn - 60)),
        );

        return $token;
    }

    private function signature(string $clientId, string $timestamp): string
    {
        $path = (string) config('payment.doku.private_key_path');
        $privateKey = $path !== '' ? @file_get_contents($path) : false;

        if ($privateKey === false || $privateKey === '') {
            throw new RuntimeException(
                'Private key DOKU tidak tersedia. Jalankan: php artisan doku:generate-keypair',
            );
        }

        $signature = '';

        if (! openssl_sign("{$clientId}|{$timestamp}", $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Gagal menandatangani permintaan token B2B DOKU.');
        }

        return base64_encode($signature);
    }
}
