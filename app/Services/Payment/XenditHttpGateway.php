<?php

namespace App\Services\Payment;

use App\Repositories\Contracts\PaymentGatewayInterface;
use App\Models\Booking;
use App\Models\Payout;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class XenditHttpGateway implements PaymentGatewayInterface
{
    private const BASE_URL = 'https://api.xendit.co';

    private string $secretKey;

    public function __construct()
    {
        $secretKey = config('xendit.secret_key');

        if (empty($secretKey)) {
            throw new RuntimeException('XENDIT_SECRET_KEY belum di-set di file .env');
        }

        $this->secretKey = (string) $secretKey;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    /**
     * BUAT INVOICE (REDIRECT URL) - UNTUK ALUR REDIRECT
     */
    public function createInvoice(Booking $booking): array
    {
        try {
            $booking->loadMissing(['client', 'package']);

            if ($booking->client === null) {
                throw new RuntimeException("Client tidak ditemukan untuk booking {$booking->kode}");
            }

            if ($booking->package === null) {
                throw new RuntimeException("Package tidak ditemukan untuk booking {$booking->kode}");
            }

            $response = $this->client()->post('/v2/invoices', [
                'external_id'          => $booking->kode,
                'amount'               => (float) $booking->total_harga,
                'payer_email'          => $booking->client->email,
                'description'          => "Pembayaran Booking {$booking->kode} - {$booking->package->nama}",
                'invoice_duration'     => 86400,
                'success_redirect_url' => route('client.bookings.show', $booking->id),
                'failure_redirect_url' => route('client.bookings.show', $booking->id),
                'currency'             => 'IDR',
                'should_send_email'    => true,
            ]);

            if ($response->failed()) {
                Log::error('Xendit: Invoice gagal dibuat', [
                    'booking_kode' => $booking->kode,
                    'status'       => $response->status(),
                    'body'         => $response->json(),
                ]);

                throw new RuntimeException(
                    'Xendit Invoice gagal: '.($response->json('message') ?? $response->body())
                );
            }

            /** @var array<string, mixed> $invoice */
            $invoice = $response->json();

            Log::info('Xendit: Invoice berhasil dibuat', [
                'booking_kode' => $booking->kode,
                'invoice_id'   => $invoice['id'] ?? null,
                'invoice_url'  => $invoice['invoice_url'] ?? null,
            ]);

            return $invoice;

        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Xendit: Gagal membuat invoice', [
                'booking_kode' => $booking->kode,
                'error'        => $e->getMessage(),
            ]);

            throw new RuntimeException('Gagal membuat Invoice Xendit: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * (TIDAK DIGUNAKAN LAGI)
     */
    public function createPaymentSession(Booking $booking): array
    {
        throw new RuntimeException('Method createPaymentSession sudah tidak digunakan. Gunakan createInvoice.');
    }

    /**
     * Kirim Payout ke Rekening Mitra via Xendit Payouts v2.
     *
     * Endpoint: POST /v2/payouts
     *
     * Format terbukti berhasil (HTTP 200):
     *  - Header: idempotency-key (lowercase)
     *  - Body: channel_code + channel_properties + recipient (tanpa account_details)
     *
     * @return array<string, mixed>
     */
    public function createPayout(Payout $payout): array
    {
        try {
            $payout->loadMissing('mitra');

            if ($payout->mitra === null) {
                throw new RuntimeException("Mitra tidak ditemukan untuk payout {$payout->kode}");
            }

            // Mapping bank lokal -> channel_code Xendit (ID_BCA, ID_BNI, dst)
            $channelCode = $this->mapBankToChannelCode((string) $payout->bank_tujuan);

            // reference_id harus unik per request
            $referenceId = $payout->kode.'-'.now()->format('YmdHis');

            // idempotency-key wajib: UUID v4 unik per request
            $idempotencyKey = (string) Str::uuid();

            $response = Http::withHeaders([
                'Authorization'   => 'Basic '.base64_encode($this->secretKey.':'),
                'Content-Type'    => 'application/json',
                'idempotency-key' => $idempotencyKey,
            ])->post(self::BASE_URL.'/v2/payouts', [
                'reference_id' => $referenceId,
                'currency'     => 'IDR',
                'amount'       => (float) $payout->jumlah,
                'description'  => "Pencairan Honor {$payout->mitra->name}",

                'channel_code' => $channelCode,
                'channel_properties' => [
                    'account_holder_name' => $payout->nama_pemilik,
                    'account_number'      => $payout->no_rekening,
                ],

                'recipient' => [
                    'type'        => 'INDIVIDUAL',
                    'given_names' => $payout->nama_pemilik,
                ],
            ]);

            if ($response->failed()) {
                $body   = $response->json();
                $errors = $response->json('errors');

                Log::error('Xendit: Payout gagal dibuat', [
                    'payout_kode'     => $payout->kode,
                    'reference_id'    => $referenceId,
                    'idempotency_key' => $idempotencyKey,
                    'channel_code'    => $channelCode,
                    'status'          => $response->status(),
                    'body'            => $body,
                    'errors'          => $errors,
                ]);

                $detail = is_array($errors) ? json_encode($errors, JSON_UNESCAPED_SLASHES) : '';

                throw new RuntimeException(
                    'Xendit Payout gagal: '
                    .($response->json('message') ?? $response->body())
                    .($detail ? ' | Details: '.$detail : '')
                );
            }

            /** @var array<string, mixed> $result */
            $result = $response->json();

            Log::info('Xendit: Payout berhasil dibuat', [
                'payout_kode'      => $payout->kode,
                'reference_id'     => $referenceId,
                'xendit_payout_id' => $result['id'] ?? null,
                'status'           => $result['status'] ?? null,
            ]);

            return $result;

        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Xendit: Gagal membuat payout', [
                'payout_kode' => $payout->kode,
                'error'       => $e->getMessage(),
            ]);

            throw new RuntimeException('Gagal mengirim Payout Xendit: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Mapping kode bank lokal -> channel_code Xendit (ID_BCA, ID_BNI, dst).
     */
    private function mapBankToChannelCode(string $bank): string
    {
        $bank = strtoupper(trim($bank));

        if (str_starts_with($bank, 'ID_')) {
            return $bank;
        }

        return match ($bank) {
            'BCA', 'BANK_CENTRAL_ASIA'       => 'ID_BCA',
            'BNI', 'BANK_NEGARA_INDONESIA'   => 'ID_BNI',
            'BRI', 'BANK_RAKYAT_INDONESIA'   => 'ID_BRI',
            'MANDIRI', 'BANK_MANDIRI'        => 'ID_MANDIRI',
            'PERMATA', 'BANK_PERMATA'        => 'ID_PERMATA',
            'CIMB', 'CIMB_NIAGA'             => 'ID_CIMB',
            'DANAMON', 'BANK_DANAMON'        => 'ID_DANAMON',
            'BSI', 'BANK_SYARIAH_INDONESIA'  => 'ID_BSI',
            'BTN', 'BANK_TABUNGAN_NEGARA'    => 'ID_BTN',
            'BJB', 'BANK_BJB'                => 'ID_BJB',
            'MEGA', 'BANK_MEGA'              => 'ID_MEGA',
            'OCBC', 'OCBC_NISP'              => 'ID_OCBC',
            'PANIN', 'PANIN_BANK'            => 'ID_PANIN',
            'UOB', 'BANK_UOB'                => 'ID_UOB',
            default                          => 'ID_'.$bank,
        };
    }
}