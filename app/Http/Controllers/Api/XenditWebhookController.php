<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payout;
use App\Services\Booking\BookingService;
use App\Services\Wallet\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected PayoutService $payoutService,
    ) {}

    public function handle(Request $request): Response
    {
        // 1. Validasi Callback Token
        $callbackToken = $request->header('x-callback-token');
        $expectedToken = config('xendit.callback_token');

        if (empty($expectedToken) || $callbackToken !== $expectedToken) {
            Log::warning('Xendit Webhook: Token tidak valid', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        // 2. Ambil Event & Payload
        $payload = $request->all();
        $event = $payload['event'] ?? null;
        $status = $payload['status'] ?? ($payload['data']['status'] ?? null);

        // Fallback: Jika event null tapi status PAID (format Invoice lama)
        if ($event === null && $status === 'PAID') {
            $event = 'invoice.paid';
        }

        Log::info('Xendit Webhook Received', [
            'event' => $event,
            'id' => $payload['id'] ?? ($payload['data']['payment_id'] ?? $payload['data']['payout_id'] ?? null),
            'status' => $status,
        ]);

        // 3. Handle Event
        try {
            match ($event) {
                // Event Invoices
                'invoice.paid', 'invoice.status' => $this->handleInvoicePaid($payload),
                
                // Event Payment API (VA, E-Wallet)
                'payment.capture', 'payment.status', 'payment.verified' => $this->handlePaymentApi($payload),
                
                // ✅ Event Payout (v2 & v3)
                'v3_payout.succeeded', 'payout.succeeded' => $this->handlePayoutSucceeded($payload),
                'v3_payout.failed', 'payout.failed' => $this->handlePayoutFailed($payload),
                
                default => Log::info("Xendit Webhook: Event '{$event}' diabaikan."),
            };

            return response('OK', 200);

        } catch (\Throwable $e) {
            Log::error('Xendit Webhook: Gagal memproses event', [
                'event' => $event,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return response('Internal Server Error', 500);
        }
    }

    protected function handleInvoicePaid(array $payload): void
    {
        $invoiceId = $payload['id'] ?? null;
        $externalId = $payload['external_id'] ?? null;

        if (empty($invoiceId) || empty($externalId)) {
            return;
        }

        $booking = Booking::where('kode', $externalId)->first();

        if ($booking === null) {
            Log::warning('Xendit Webhook: Booking tidak ditemukan (Invoice)', ['external_id' => $externalId]);
            return;
        }

        $this->bookingService->verifyPaymentViaWebhook($booking, $invoiceId);
    }

    protected function handlePaymentApi(array $payload): void
    {
        $data = $payload['data'] ?? null;
        
        if (!$data) return;

        $paymentId = $data['payment_id'] ?? null;
        $referenceId = $data['reference_id'] ?? null;
        $status = $data['status'] ?? null;

        if (empty($paymentId) || empty($referenceId) || $status !== 'SUCCEEDED') {
            return;
        }

        $booking = Booking::where('kode', $referenceId)->first();

        if ($booking === null) {
            Log::warning('Xendit Webhook: Booking tidak ditemukan (Payment API)', ['reference_id' => $referenceId]);
            return;
        }

        $this->bookingService->verifyPaymentViaWebhook($booking, $paymentId);
    }

    protected function handlePayoutSucceeded(array $payload): void
    {
        $data = $payload['data'] ?? $payload;
        // ✅ V3 menggunakan "payout_id", bukan "id"
        $payoutId = $data['payout_id'] ?? $data['id'] ?? null;
        
        if (empty($payoutId)) return;

        $payout = Payout::where('xendit_payout_id', $payoutId)->first();
        if ($payout === null) {
            Log::warning('Xendit Webhook: Payout tidak ditemukan', ['xendit_payout_id' => $payoutId]);
            return;
        }

        $this->payoutService->updateFromWebhook($payout, 'SUCCEEDED');
    }

    protected function handlePayoutFailed(array $payload): void
    {
        $data = $payload['data'] ?? $payload;
        $payoutId = $data['payout_id'] ?? $data['id'] ?? null;
        $failureReason = $data['failure_reason'] ?? 'Unknown error';
        
        if (empty($payoutId)) return;

        $payout = Payout::where('xendit_payout_id', $payoutId)->first();
        if ($payout === null) {
            Log::warning('Xendit Webhook: Payout tidak ditemukan', ['xendit_payout_id' => $payoutId]);
            return;
        }

        $this->payoutService->updateFromWebhook($payout, 'FAILED');

        Log::error('Xendit Webhook: Payout gagal ditransfer', [
            'payout_kode' => $payout->kode,
            'reason' => $failureReason,
        ]);
    }
}