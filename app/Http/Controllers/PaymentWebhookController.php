<?php

namespace App\Http\Controllers;

use App\Data\Xendit\XenditWebhookEvent;
use App\Jobs\ProcessXenditNotificationJob;
use App\Models\WebhookEvent;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Endpoint webhook publik Xendit.
 *
 * Handler sengaja tipis: verifikasi callback token, simpan event untuk
 * idempotensi, balas 200 segera, lalu serahkan sisanya ke queue.
 *
 * Kunci idempotensi memasukkan event + status + id. Tanpa event/status,
 * transisi seperti `refund.failed` setelah `refund.succeeded` akan dibuang
 * sebagai duplikat karena keduanya membawa payment_id yang sama.
 */
final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly XenditPaymentServiceInterface $gateway,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        try {
            $notification = $this->gateway->verifyWebhook(
                $rawBody,
                $request->header('X-Callback-Token'),
            );
        } catch (ValidationException) {
            return response()->json(['message' => 'Callback token tidak valid.'], 401);
        }

        $eventKey = $this->eventKey($notification, $rawBody);

        if ($eventKey === '' || ! $this->storeEvent($notification, $eventKey)) {
            return response()->json(['status' => 'ok']);
        }

        try {
            ProcessXenditNotificationJob::dispatch($notification, $eventKey);
        } catch (Throwable $e) {
            // Queue push gagal: buang marker supaya pengiriman ulang Xendit
            // tidak terdedupe, lalu balas 5xx agar memang benar dikirim ulang.
            WebhookEvent::query()
                ->where('provider', 'xendit')
                ->where('request_id', $eventKey)
                ->delete();

            Log::warning('PaymentWebhookController: gagal mengantrekan webhook', [
                'event' => $notification->event,
                'request_id' => $eventKey,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'queue_unavailable'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Kunci dedupe = hash(provider | event | status | id).
     *
     * ID dipilih berdasarkan jenis event:
     * - refund: reference_id (`refund-{id}`) lebih spesifik daripada payment_id
     * - payout: payout_id
     * - payment: payment_id
     * - tanpa ID: hash dari body (event expiry biasanya seperti ini)
     */
    private function eventKey(XenditWebhookEvent $notification, string $rawBody): string
    {
        $id = $this->resolveId($notification, $rawBody);

        return hash('sha256', implode('|', [
            'xendit',
            $notification->event,
            $notification->status,
            $id,
        ]));
    }

    private function resolveId(XenditWebhookEvent $notification, string $rawBody): string
    {
        if (str_starts_with($notification->event, 'refund.')) {
            return $notification->referenceId
                ?? $notification->paymentId
                ?? $notification->payoutId
                ?? $this->bodyId($rawBody);
        }

        if (str_starts_with($notification->event, 'payout.')
            || str_starts_with($notification->event, 'v3_payout.')) {
            return $notification->payoutId
                ?? $notification->referenceId
                ?? $this->bodyId($rawBody);
        }

        return $notification->paymentId
            ?? $notification->referenceId
            ?? $notification->payoutId
            ?? $this->bodyId($rawBody);
    }

    private function bodyId(string $rawBody): string
    {
        return 'body-'.hash('sha256', $rawBody);
    }

    private function storeEvent(XenditWebhookEvent $notification, string $requestId): bool
    {
        // Cek dulu agar duplicate sequential tidak mengabort transaksi test.
        if (WebhookEvent::query()
            ->where('provider', 'xendit')
            ->where('request_id', $requestId)
            ->exists()) {
            return false;
        }

        try {
            WebhookEvent::query()->create([
                'provider' => 'xendit',
                'request_id' => $requestId,
                'event' => $notification->event,
                'status' => $notification->status,
                'payload' => $notification->payload,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
