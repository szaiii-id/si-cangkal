<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileXenditTransactionsJob;
use Illuminate\Console\Command;

/**
 * Menyelaraskan payout/refund Xendit yang webhook-nya tidak sampai.
 *
 * Dijalankan scheduler tiap 5 menit (routes/console.php).
 */
final class ReconcileXenditCommand extends Command
{
    protected $signature = 'payment:reconcile-xendit';

    protected $description = 'Sinkronkan status payout/refund Xendit yang belum diterima via webhook';

    public function handle(): int
    {
        ReconcileXenditTransactionsJob::dispatch();

        $this->info('Reconciliation Xendit diantrekan.');

        return self::SUCCESS;
    }
}
