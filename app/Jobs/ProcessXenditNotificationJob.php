<?php

namespace App\Jobs;

use App\Data\Xendit\XenditWebhookEvent;
use App\Models\WebhookEvent;
use App\Services\Payment\XenditNotificationProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job pemroses notifikasi Xendit yang sudah terverifikasi tokennya.
 *
 * Dijalankan setelah controller membalas 200 agar Xendit tidak timeout.
 * Bila job gagal, penanda idempotensi dihapus supaya webhook yang dikirim
 * ulang Xendit tetap bisa diproses.
 */
final class ProcessXenditNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly XenditWebhookEvent $notification,
        public readonly string $eventKey,
    ) {}

    public function handle(XenditNotificationProcessor $processor): void
    {
        $processor->process($this->notification);
    }

    public function failed(): void
    {
        WebhookEvent::query()
            ->where('provider', 'xendit')
            ->where('request_id', $this->eventKey)
            ->delete();
    }
}
