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
 *
 * Bila job gagal permanen, penanda idempotensi **tidak dihapus**: Xendit
 * tidak menjamin pengiriman ulang, sehingga menghapus marker hanya bisa
 * menyebabkan event hilang. Job ditandai `failed_at` + `attempts` supaya
 * reconciliation (T12) atau operator bisa menindaklanjuti.
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
            ->update([
                'attempts' => $this->attempts(),
                'failed_at' => now(),
            ]);
    }
}
