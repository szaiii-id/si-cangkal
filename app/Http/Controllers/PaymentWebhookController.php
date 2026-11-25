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

        // Event expiry tidak selalu membawa payment_id/payout_id. Pakai
        // reference_id bila ada; untuk payload tanpa ID, hash body membuat
        // tiap event unik tetapi retry dengan body sama tetap terdedupe.
        $requestId = $notification->paymentId
            ?? $notification->payoutId
            ?? $notification->referenceId
            ?? 'event-'.hash('sha256', $request->getContent());

        if ($requestId === '' || ! $this->storeEvent($notification, $requestId)) {
            return response()->json(['status' => 'ok']);
        }

        ProcessXenditNotificationJob::dispatch($notification, $requestId);

        return response()->json(['status' => 'ok']);
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
                'payload' => $notification->payload,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
