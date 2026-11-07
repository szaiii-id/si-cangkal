<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Booking\BookingCancellationService;
use Illuminate\Console\Command;

/**
 * Auto-cancel booking yang sudah melewati `expired_at` (default 24 jam).
 *
 * Dijalankan oleh scheduler (`routes/console.php`) setiap jam.
 *
 * Perubahan dari versi lama:
 * - Memakai `BookingCancellationService` (hasil pemecahan `BookingService`).
 * - Audit log per booking di-handle di service (S-16), bukan di command.
 * - File bukti transfer dihapus saat cancel (B-02), juga di service.
 */
final class ExpireBookingsCommand extends Command
{
    protected $signature = 'bookings:expire
                            {--dry-run : Tampilkan berapa booking yang akan di-expire tanpa eksekusi}';

    protected $description = 'Auto-cancel booking pending yang sudah melewati expired_at (24 jam)';

    public function handle(BookingCancellationService $cancellationService): int
    {
        $this->info('Mencari booking pending yang expired...');

        if ($this->option('dry-run')) {
            $count = Booking::expired()->count();

            $this->warn("  DRY RUN: {$count} booking akan di-cancel (tidak dieksekusi).");

            return self::SUCCESS;
        }

        $count = $cancellationService->expirePendingBookings();

        if ($count === 0) {
            $this->info('Tidak ada booking yang perlu di-expire.');

            return self::SUCCESS;
        }

        $this->info("{$count} booking berhasil di-expire (status: cancelled).");

        return self::SUCCESS;
    }
}