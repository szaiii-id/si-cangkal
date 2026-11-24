<?php

namespace App\Services\Payment\Doku;

/**
 * Tanda tangan SNAP DOKU dengan access token (HMAC_SHA512).
 *
 * stringToSign = METHOD + ':' + path + ':' + accessToken + ':'
 *              + lowercase(hexEncode(SHA256(body))) + ':' + timestamp
 */
final class DokuSnapSignature
{
    public static function forRequest(
        string $method,
        string $targetPath,
        string $body,
        string $timestamp,
        string $accessToken,
    ): string {
        $digest = strtolower(bin2hex(hash('sha256', $body, true)));

        $stringToSign = strtoupper($method).':'
            .$targetPath.':'
            .$accessToken.':'
            .$digest.':'
            .$timestamp;

        return base64_encode(hash_hmac(
            'sha512',
            $stringToSign,
            (string) config('payment.doku.secret_key'),
            true,
        ));
    }
}
