<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\TripRepositoryInterface;
use App\Services\Trip\TripService;
use App\Support\Timezone;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ActivateTripsCommand extends Command
{
    /**
     * Nama dan signature command.
     *
     * @var string
     */
    protected $signature = 'trips:activate
                            {--dry-run : Tampilkan trip yang akan diaktifkan tanpa eksekusi}';

    /**
     * Deskripsi command.
     *
     * @var string
     */
    protected $description = 'Auto-activate trip yang sudah waktunya (tanggal_mulai <= today, punya mitra)';

    /**
     * Execute the console command.
     */
    public function handle(TripService $tripService, TripRepositoryInterface $tripRepository): int
    {
        $this->info('Mencari trip yang siap diaktifkan...');

        if ($this->option('dry-run')) {
            return $this->dryRun($tripRepository);
        }

        $result = $tripService->activateDueTrips();

        if ($result['activated'] === 0 && $result['skipped_no_mitra'] === 0) {
            $this->info('Tidak ada trip yang perlu diaktifkan.');

            return self::SUCCESS;
        }

        if ($result['activated'] > 0) {
            $this->info("{$result['activated']} trip berhasil diaktifkan.");
        }

        if ($result['skipped_no_mitra'] > 0) {
            $this->warn("{$result['skipped_no_mitra']} trip dilewati karena belum punya mitra.");
        }

        return self::SUCCESS;
    }

    /**
     * Dry-run: tampilkan trip yang akan diaktifkan tanpa eksekusi.
     */
    protected function dryRun(TripRepositoryInterface $tripRepository): int
    {
        $candidates = $tripRepository->findDueScheduled();

        $willActivate = 0;
        $skippedNoMitra = 0;

        foreach ($candidates as $trip) {
            $tripToday = Timezone::today($trip->timezoneOrDefault());
            $tanggalMulai = Carbon::parse($trip->tanggal_mulai);

            if ($tanggalMulai->greaterThan($tripToday)) {
                continue;
            }

            if (! $trip->hasMitra()) {
                $skippedNoMitra++;
                $this->line("<fg=yellow>{$trip->kode}</> - belum punya mitra ({$trip->package->nama})");

                continue;
            }

            $willActivate++;
            $mitraName = $trip->mitra->name;
            $this->line("<fg=green>{$trip->kode}</> - {$trip->package->nama} (mitra: {$mitraName})");
        }

        $this->newLine();
        $this->warn("DRY RUN: {$willActivate} akan diaktifkan, {$skippedNoMitra} dilewati (tidak dieksekusi).");

        return self::SUCCESS;
    }
}
