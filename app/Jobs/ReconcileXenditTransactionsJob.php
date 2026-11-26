<?php

namespace App\Jobs;

use App\Data\Xendit\XenditWebhookEvent;
use App\Enums\PaymentGateway;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;
use App\Services\Payment\XenditNotificationProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menyelaraskan status payout/refund yang webhook-nya tidak sampai.
 *
 * Job ini memanggil Get Payout / Get Refund lalu memproses hasilnya lewat
 * XenditNotificationProcessor yang sama dengan jalur webhook, supaya tidak
 * ada logika duplikat.
 */
final class ReconcileXenditTransactionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function handle(
        XenditPaymentServiceInterface $gateway,
        XenditNotificationProcessor $processor,
    ): void {
        $this->reconcilePayouts($gateway, $processor);
        $this->reconcileRefunds($gateway, $processor);
    }

    private function reconcilePayouts(
        XenditPaymentServiceInterface $gateway,
        XenditNotificationProcessor $processor,
    ): void {
        $payments = Payment::query()
            ->where('gateway', PaymentGateway::XENDIT)
            ->where('gateway_status', 'PAYOUT_REQUESTED')
            ->whereNotNull('released_at')
            ->whereNull('settled_at')
            ->get();

        foreach ($payments as $payment) {
            $payoutId = (string) data_get($payment->gateway_payload, 'payout_id', '');

            if ($payoutId === '') {
                continue;
            }

            try {
                $result = $gateway->getPayout($payoutId);
            } catch (Throwable $e) {
                Log::warning('ReconcileXenditTransactionsJob: gagal cek payout', [
                    'payment_id' => $payment->id,
                    'payout_id' => $payoutId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $eventName = match ($result->status) {
                'SUCCEEDED', 'COMPLETED' => 'v3_payout.succeeded',
                'FAILED', 'REVERSED', 'REJECTED' => 'v3_payout.failed',
                default => null,
            };

            if ($eventName === null) {
                continue;
            }

            $processor->process(new XenditWebhookEvent(
                event: $eventName,
                paymentId: null,
                payoutId: $payoutId,
                referenceId: null,
                amount: 0.0,
                status: $result->status,
                payload: [
                    'event' => $eventName,
                    'data' => [
                        'payout_id' => $payoutId,
                        'status' => $result->status,
                        'failure_code' => data_get($result->payload, 'failure_code', ''),
                    ],
                ],
            ));
        }
    }

    private function reconcileRefunds(
        XenditPaymentServiceInterface $gateway,
        XenditNotificationProcessor $processor,
    ): void {
        $refunds = Refund::query()
            ->where('status', RefundStatus::PENDING)
            ->whereIn('gateway_status', ['INTENT', 'PENDING'])
            ->whereNotNull('gateway_refund_id')
            ->get();

        foreach ($refunds as $refund) {
            $refundId = (string) $refund->gateway_refund_id;

            try {
                $result = $gateway->getRefund($refundId);
            } catch (Throwable $e) {
                Log::warning('ReconcileXenditTransactionsJob: gagal cek refund', [
                    'refund_id' => $refund->id,
                    'gateway_refund_id' => $refundId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $eventName = match ($result->status) {
                'SUCCEEDED' => 'refund.succeeded',
                'FAILED' => 'refund.failed',
                default => null,
            };

            if ($eventName === null) {
                continue;
            }

            $referenceId = (string) data_get($result->payload, 'reference_id', '');

            if ($referenceId === '') {
                $referenceId = 'refund-'.$refund->id;
            }

            $processor->process(new XenditWebhookEvent(
                event: $eventName,
                paymentId: null,
                payoutId: null,
                referenceId: $referenceId,
                amount: (float) $refund->amount,
                status: $result->status,
                payload: [
                    'event' => $eventName,
                    'data' => [
                        'refund_id' => $refundId,
                        'reference_id' => $referenceId,
                        'status' => $result->status,
                        'failure_code' => data_get($result->payload, 'failure_code', ''),
                    ],
                ],
            ));
        }
    }
}
