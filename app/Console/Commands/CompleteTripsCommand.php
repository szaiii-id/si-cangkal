<?php

namespace App\Console\Commands;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\Trip\TripService;
use App\Support\Timezone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CompleteTripsCommand extends Command
{
    protected $signature = 'trips:complete
                            {--dry-run : Tampilkan trip yang akan diselesaikan tanpa eksekusi}';

    protected $description = 'Auto-complete trip ACTIVE yang sudah lewat tanggal selesai';

    public function handle(TripService $tripService): int
    {
        // Ambil kandidat UTC dulu, lalu periksa ulang memakai timezone trip.
        $candidates = Trip::query()
            ->where('status', TripStatus::ACTIVE->value)
            ->where('tanggal_selesai', '<=', now()->subDay()->format('Y-m-d'))
            ->orderBy('tanggal_selesai')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Tidak ada trip yang perlu diselesaikan.');

            return self::SUCCESS;
        }

        $completed = 0;
        $skipped = 0;

        foreach ($candidates as $trip) {
            // Bandingkan sebagai tanggal (Y-m-d) agar tidak terkena
            // perbedaan offset UTC vs IANA.
            $batas = Timezone::today($trip->timezoneOrDefault())
                ->subDay()
                ->toDateString();

            if ($trip->tanggal_selesai->toDateString() > $batas) {
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("<fg=green>{$trip->kode}</> - {$trip->package->nama} (dry-run)");
                $completed++;

                continue;
            }

            try {
                $tripService->complete($trip);
                $completed++;
                $this->line("<fg=green>{$trip->kode}</> - selesai");
            } catch (Throwable $e) {
                $skipped++;
                Log::warning('CompleteTripsCommand: gagal menyelesaikan trip', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage(),
                ]);
                $this->line("<fg=red>{$trip->kode}</> - gagal: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("{$completed} trip diselesaikan, {$skipped} dilewati.");

        return self::SUCCESS;
    }
}
