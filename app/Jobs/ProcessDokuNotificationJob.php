<?php

namespace App\Jobs;

use App\Data\Doku\DokuNotification;
use App\Services\Payment\DokuNotificationProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job pemroses notifikasi DOKU yang sudah terverifikasi signature-nya.
 *
 * Dijalankan setelah controller membalas 2xx agar DOKU tidak timeout.
 */
final class ProcessDokuNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly DokuNotification $notification,
    ) {}

    public function handle(DokuNotificationProcessor $processor): void
    {
        $processor->process($this->notification);
    }
}
