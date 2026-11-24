<?php

use App\Services\Payment\Doku\DokuB2bTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Token B2B DOKU memakai tanda tangan asimetris SHA256withRSA dengan
| private key merchant. Public key harus didaftarkan di dashboard DOKU.
|
*/

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'payment.doku.client_id' => 'MCH-SANDBOX-001',
        'payment.doku.base_url' => 'https://api-sandbox.doku.com',
        'payment.doku.b2b_token_path' => '/authorization/v1/access-token/b2b',
    ]);

    Cache::store('array')->flush();

    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($resource, $this->privateKey);
    $this->publicKey = openssl_pkey_get_details($resource)['key'];

    $this->keyPath = tempnam(sys_get_temp_dir(), 'doku-key');
    file_put_contents($this->keyPath, $this->privateKey);

    config(['payment.doku.private_key_path' => $this->keyPath]);
});

afterEach(function () {
    if (is_file($this->keyPath)) {
        unlink($this->keyPath);
    }
});

function fakeDokuToken(int $expiresIn = 900): void
{
    Http::fake([
        'api-sandbox.doku.com/authorization/v1/access-token/b2b' => Http::response([
            'responseCode' => '2007300',
            'responseMessage' => 'Successful',
            'accessToken' => 'token-abc',
            'tokenType' => 'Bearer',
            'expiresIn' => $expiresIn,
        ]),
    ]);
}

describe('DokuB2bTokenService', function () {
    it('mengambil token B2B dengan signature SHA256withRSA yang valid', function () {
        fakeDokuToken();

        $token = app(DokuB2bTokenService::class)->token();

        expect($token)->toBe('token-abc');

        Http::assertSent(function ($request): bool {
            $timestamp = (string) $request->header('X-Timestamp')[0];
            $signature = (string) $request->header('X-Signature')[0];

            return $request->url() === 'https://api-sandbox.doku.com/authorization/v1/access-token/b2b'
                && $request->method() === 'POST'
                && $request->hasHeader('X-Client-Key', 'MCH-SANDBOX-001')
                && $request->data()['grantType'] === 'client_credentials'
                && openssl_verify(
                    'MCH-SANDBOX-001|'.$timestamp,
                    base64_decode($signature),
                    $this->publicKey,
                    OPENSSL_ALGO_SHA256,
                ) === 1;
        });
    });

    it('meng-cache token sampai sebelum expired', function () {
        fakeDokuToken();

        $service = app(DokuB2bTokenService::class);

        expect($service->token())->toBe('token-abc')
            ->and($service->token())->toBe('token-abc');

        Http::assertSentCount(1);
    });

    it('melempar exception saat DOKU menolak permintaan token', function () {
        Http::fake([
            'api-sandbox.doku.com/authorization/v1/access-token/b2b' => Http::response([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized. Signature',
            ], 401),
        ]);

        expect(fn () => app(DokuB2bTokenService::class)->token())
            ->toThrow(RuntimeException::class);
    });

    it('melempar exception saat private key tidak tersedia', function () {
        config(['payment.doku.private_key_path' => '/tidak/ada/key.pem']);
        fakeDokuToken();

        expect(fn () => app(DokuB2bTokenService::class)->token())
            ->toThrow(RuntimeException::class);
    });
});
