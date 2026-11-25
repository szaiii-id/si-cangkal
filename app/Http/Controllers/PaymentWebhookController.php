<?php

namespace App\Http\Controllers;

use App\Data\Xendit\XenditWebhookEvent;
use App\Jobs\ProcessXenditNotificationJob;
use App\Models\WebhookEvent;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint webhook publik Xendit.
 *
 * Handler sengaja tipis: verifikasi callback token, simpan event untuk
 * idempotensi, balas 200 segera, lalu serahkan sisanya ke queue.
 */
final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly XenditPaymentServiceInterface $gateway,
    ) {}

    public function store(Request $request): JsonResponse
    {
        try {
            $notification = $this->gateway->verifyWebhook(
                $request->getContent(),
                $request->header('X-Callback-Token'),
            );
        } catch (ValidationException) {
            return response()->json(['message' => 'Callback token tidak valid.'], 401);
        }

        if (! $this->storeEvent($notification)) {
            return response()->json(['status' => 'ok']);
        }

        ProcessXenditNotificationJob::dispatch($notification);

        return response()->json(['status' => 'ok']);
    }

    private function storeEvent(XenditWebhookEvent $notification): bool
    {
        $requestId = $notification->paymentId
            ?? $notification->payoutId
            ?? $notification->event;

        if ($requestId === '') {
            return false;
        }

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
                'payload' => $notification->payload,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
