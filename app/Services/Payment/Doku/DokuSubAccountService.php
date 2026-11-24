<?php

namespace App\Services\Payment\Doku;

use App\Models\MitraBankAccount;
use App\Services\Payment\Doku\Contracts\DokuB2bTokenServiceInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mendaftarkan sub-account DOKU untuk mitra dan membuat split rule 100%.
 *
 * Semua response harus divalidasi: DOKU tidak mengembalikan error bila
 * account.id / split_rule_id salah—dana hanya akan mengendap tanpa split.
 */
final class DokuSubAccountService
{
    private const ACCOUNT_TYPE = 'DOKU_MERCHANT_IDR';

    public function __construct(
        private readonly DokuB2bTokenServiceInterface $tokenService,
    ) {}

    public function register(MitraBankAccount $bankAccount): void
    {
        $mitra = $bankAccount->user;

        if ($mitra === null) {
            throw new RuntimeException('Rekening mitra tidak memiliki user.');
        }

        $token = $this->tokenService->token();
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $phone = $mitra->profile?->telepon;

        $registerPath = '/sub-account/v2.0/register';
        $registerBody = [
            'partnerReferenceNo' => 'mitra-'.$mitra->id,
            'type' => 'DEFAULT',
            'name' => (string) $mitra->name,
            'email' => (string) $mitra->email,
            'phoneNo' => is_string($phone) && $phone !== '' ? $phone : '081234567890',
            'countryCode' => 'ID',
        ];

        $registered = $this->post(
            $registerPath,
            $registerBody,
            $token,
            $timestamp,
            $this->externalId($mitra->id, 1),
        );

        $profileId = (string) data_get($registered, 'profileId', '');

        if ($profileId === '') {
            throw new RuntimeException('DOKU tidak mengembalikan profileId sub-account.');
        }

        $accountNo = '';

        foreach (data_get($registered, 'accounts', []) as $account) {
            if (data_get($account, 'type') === self::ACCOUNT_TYPE) {
                $accountNo = (string) data_get($account, 'accountNo', '');
                break;
            }
        }

        if ($accountNo === '') {
            throw new RuntimeException('DOKU tidak mengembalikan account number IDR.');
        }

        $splitPath = '/sub-account/v2.0/split-rules';
        $splitBody = [
            'transactionType' => 'PAYMENT',
            'rules' => [[
                'type' => 'PERCENTAGE',
                'value' => 100,
                'accountNumber' => (int) $accountNo,
            ]],
        ];

        $split = $this->post(
            $splitPath,
            $splitBody,
            $token,
            $timestamp,
            $this->externalId($mitra->id, 2),
        );

        $splitRuleId = (string) data_get($split, 'splitRuleId', '');

        if ($splitRuleId === '') {
            throw new RuntimeException('DOKU tidak mengembalikan splitRuleId.');
        }

        $bankAccount->update([
            'doku_profile_id' => $profileId,
            'doku_account_no' => $accountNo,
            'doku_split_rule_id' => $splitRuleId,
            'doku_registered_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(
        string $path,
        array $body,
        string $token,
        string $timestamp,
        string $externalId,
    ): array {
        $bodyJson = json_encode($body, JSON_THROW_ON_ERROR);
        $url = rtrim((string) config('payment.doku.base_url'), '/').$path;

        $response = Http::withHeaders([
            'X-PARTNER-ID' => (string) config('payment.doku.client_id'),
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => DokuSnapSignature::forRequest(
                method: 'POST',
                targetPath: $path,
                body: $bodyJson,
                timestamp: $timestamp,
                accessToken: $token,
            ),
            'X-EXTERNAL-ID' => $externalId,
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ])->withBody($bodyJson, 'application/json')->post($url);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gagal memanggil '.$path.': HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $result */
        $result = $response->json() ?? [];

        if (! str_starts_with((string) data_get($result, 'responseCode', ''), '2')) {
            throw new RuntimeException('DOKU menolak '.$path.': '.(string) data_get($result, 'responseMessage', ''));
        }

        return $result;
    }

    private function externalId(int $mitraId, int $sequence): string
    {
        return now()->format('ymd')
            .str_pad((string) $mitraId, 6, '0', STR_PAD_LEFT)
            .str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }
}
