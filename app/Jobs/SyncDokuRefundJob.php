<?php

namespace App\Jobs;

use App\Models\Refund;
use App\Models\User;
use App\Services\Refund\RefundApprovalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Retry refund gateway yang gagal padaattempt pertama.
 *
 * Menjalankan ulang approval service memastikan negative Payment, status
 * booking, audit, dan notifikasi tetap konsisten dengan alur normal.
 */
final class SyncDokuRefundJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $refundId,
    ) {}

    public function handle(RefundApprovalService $approvalService): void
    {
        $refund = Refund::query()->find($this->refundId);

        if ($refund === null || $refund->status->value !== 'PENDING') {
            return;
        }

        $mitra = User::query()->find($refund->initiated_by_mitra);

        if ($mitra === null) {
            return;
        }

        $approvalService->approve($refund, $mitra, 'Retry otomatis refund gateway');
    }
}
