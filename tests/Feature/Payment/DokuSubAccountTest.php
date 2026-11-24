<?php

use App\Jobs\RegisterDokuSubAccountJob;
use App\Models\MitraBankAccount;
use App\Models\User;
use App\Services\Payment\Doku\Contracts\DokuB2bTokenServiceInterface;
use App\Services\Payment\Doku\DokuSubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|==========================================================================
| Sub Account V2 — otomatisasi rekening mitra
|==========================================================================
|
| Satu sub-account per mitra, split rule 100% ke DOKU_MERCHANT_IDR mitra.
| Semua request butuh token B2B + HMAC_SHA512.
|
*/

beforeEach(function () {
    config([
        'payment.doku.client_id' => 'MCH-SANDBOX-001',
        'payment.doku.secret_key' => 'secret-sandbox',
        'payment.doku.base_url' => 'https://api-sandbox.doku.com',
        'payment.doku.private_key_path' => 'dummy-key.pem',
    ]);

    Http::preventStrayRequests();

    $this->token = Mockery::mock(DokuB2bTokenServiceInterface::class);
    $this->token->shouldReceive('token')->andReturn('token-b2b');
    $this->app->instance(DokuB2bTokenServiceInterface::class, $this->token);

    $this->mitra = User::factory()->mitra()->create([
        'name' => 'Mitra Travel',
        'email' => 'mitra-sac@test.com',
        'must_change_password' => false,
    ]);

    $this->bankAccount = MitraBankAccount::create([
        'user_id' => $this->mitra->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BCA',
        'nomor_rekening' => '1234567890',
        'nama_pemilik' => 'Mitra Travel',
        'is_primary' => true,
    ]);
});

function fakeDokuSubAccount(): void
{
    Http::fake([
        'api-sandbox.doku.com/sub-account/v2.0/register' => Http::response([
            'responseCode' => '2000200',
            'responseMessage' => 'Successful',
            'profileId' => 'PF-123',
            'accounts' => [
                [
                    'type' => 'DOKU_MERCHANT_IDR',
                    'currency' => 'IDR',
                    'accountNo' => '9001234567',
                ],
            ],
        ]),
        'api-sandbox.doku.com/sub-account/v2.0/split-rules' => Http::response([
            'responseCode' => '2000200',
            'responseMessage' => 'Successful',
            'splitRuleId' => 'SR-456',
            'transactionType' => 'PAYMENT',
            'rules' => [
                ['type' => 'PERCENTAGE', 'value' => 100, 'accountNumber' => 9001234567],
            ],
        ]),
    ]);
}

describe('DokuSubAccountService', function () {
    it('mendaftarkan sub-account dan split rule 100% ke mitra', function () {
        fakeDokuSubAccount();

        app(DokuSubAccountService::class)->register($this->bankAccount);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api-sandbox.doku.com/sub-account/v2.0/register') {
                return false;
            }

            return $request->method() === 'POST'
                && $request->hasHeader('X-PARTNER-ID', 'MCH-SANDBOX-001')
                && $request->hasHeader('Authorization', 'Bearer token-b2b')
                && $request->hasHeader('X-EXTERNAL-ID')
                && str_starts_with((string) $request->header('X-Signature')[0], '')
                && $request->data()['partnerReferenceNo'] === 'mitra-'.$this->mitra->id
                && $request->data()['type'] === 'DEFAULT';
        });

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api-sandbox.doku.com/sub-account/v2.0/split-rules') {
                return false;
            }

            $rules = $request->data()['rules'] ?? [];

            return $request->data()['transactionType'] === 'PAYMENT'
                && count($rules) === 1
                && $rules[0]['type'] === 'PERCENTAGE'
                && (int) $rules[0]['value'] === 100
                && (int) $rules[0]['accountNumber'] === 9001234567;
        });

        $this->bankAccount->refresh();

        expect($this->bankAccount->doku_profile_id)->toBe('PF-123')
            ->and($this->bankAccount->doku_account_no)->toBe('9001234567')
            ->and($this->bankAccount->doku_split_rule_id)->toBe('SR-456')
            ->and($this->bankAccount->doku_registered_at)->not->toBeNull();
    });

    it('job hanya mendaftarkan rekening yang belum terdaftar', function () {
        fakeDokuSubAccount();

        (new RegisterDokuSubAccountJob($this->bankAccount->id))
            ->handle(app(DokuSubAccountService::class));

        (new RegisterDokuSubAccountJob($this->bankAccount->id))
            ->handle(app(DokuSubAccountService::class));

        Http::assertSentCount(2); // register + split rule, tidak diulang
    });
});
