<?php

namespace App\Data\Xendit;

/**
 * Event webhook Xendit yang sudah terverifikasi tokennya.
 *
 * @param  array<string, mixed>  $payload
 */
final readonly class XenditWebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $event,
        public ?string $paymentId,
        public ?string $payoutId,
        public ?string $referenceId,
        public float $amount,
        public string $status,
        public array $payload,
    ) {}
}
