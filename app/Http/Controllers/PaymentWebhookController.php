<?php

namespace App\Http\Controllers;

use App\Data\Doku\DokuNotification;
use App\Jobs\ProcessDokuNotificationJob;
use App\Models\WebhookEvent;
use App\Services\Payment\Contracts\PaymentGatewayServiceInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint webhook publik DOKU.
 *
 * Handler sengaja tipis: verifikasi signature, simpan event untuk
 * idempotensi, balas 2xx segera, lalu serahkan sisanya ke queue.
 */
final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayServiceInterface $gateway,
    ) {}

    public function store(Request $request): Response|JsonResponse
    {
        $rawBody = $request->getContent();

        $headers = [
            'Client-Id' => (string) $request->header('Client-Id'),
            'Request-Id' => (string) $request->header('Request-Id'),
            'Request-Timestamp' => (string) $request->header('Request-Timestamp'),
            'Signature' => (string) $request->header('Signature'),
        ];

        $targetPath = '/'.ltrim($request->path(), '/');

        try {
            $notification = $this->gateway->verifyNotification(
                $rawBody,
                $headers,
                $targetPath,
            );
        } catch (ValidationException) {
            return response()->json(['message' => 'Signature tidak valid.'], 401);
        }

        if (! $this->storeEvent($notification)) {
            return response()->noContent();
        }

        ProcessDokuNotificationJob::dispatch($notification);

        return response()->noContent();
    }

    /**
     * Ack untuk notifikasi Sub Account V2.
     *
     * DOKU mengharapkan HTTP 200 dengan responseCode 2000000. Detail event
     * disimpan untuk rekonsiliasi; pemrosesan lanjutan menyusul.
     */
    public function subAccount(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $referenceNo = (string) data_get($payload, 'referenceNo', '');

        if ($referenceNo !== '') {
            WebhookEvent::query()->firstOrCreate(
                [
                    'provider' => 'doku-sub-account',
                    'request_id' => $referenceNo,
                ],
                ['payload' => $payload],
            );
        }

        return response()->json([
            'responseCode' => '2000000',
            'responseMessage' => 'Successful',
        ]);
    }

    private function storeEvent(DokuNotification $notification): bool
    {
        // Cek dulu agar duplicate sequential tidak mengabort transaksi test
        // (RefreshDatabase membungkus tiap test dalam satu transaksi).
        // Unique index tetap menjadi garda terakhir untuk race condition.
        if (WebhookEvent::query()
            ->where('provider', 'doku')
            ->where('request_id', $notification->requestId)
            ->exists()) {
            return false;
        }

        try {
            WebhookEvent::query()->create([
                'provider' => 'doku',
                'request_id' => $notification->requestId,
                'payload' => $notification->payload,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Webhook yang sama sudah diterima sebelumnya. Balas 2xx agar
            // DOKU berhenti retry, tapi jangan antrekan job kedua kalinya.
            return false;
        }
    }
}
