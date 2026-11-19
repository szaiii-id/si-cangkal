<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Trip;
use App\Services\ETicket\ETicketService;
use App\Services\Trip\TripService;
use Illuminate\Database\Seeder;

class TripSeeder extends Seeder
{
    public function __construct(
        protected TripService $tripService,
        protected ETicketService $eTicketService,
    ) {}

    public function run(): void
    {
        // Ambil booking yang sudah CONFIRMED / IN_PROGRESS / COMPLETED
        $bookings = Booking::whereIn('status', [
            BookingStatus::CONFIRMED->value,
            BookingStatus::IN_PROGRESS->value,
            BookingStatus::COMPLETED->value,
        ])->get();

        foreach ($bookings as $booking) {
            // Skip jika sudah ada trip
            if (Trip::where('booking_id', $booking->id)->exists()) {
                $this->command->line("⏭️  Trip untuk {$booking->kode} sudah ada.");

                continue;
            }

            try {
                // 1. Buat Payment verified (karena CONFIRMED = sudah bayar)
                Payment::updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'jumlah' => $booking->total_harga,
                        'metode' => 'transfer_manual',
                        'verified_by' => $booking->mitra_id,
                        'verified_at' => now()->subDays(3),
                        'catatan' => 'Pembayaran terverifikasi (seeder).',
                    ]
                );

                // 2. Buat Trip via Service
                // Ubah status jadi CONFIRMED dulu agar lolos guard di service
                $originalStatus = $booking->status;
                $booking->update(['status' => BookingStatus::CONFIRMED->value]);

                $trip = $this->tripService->createFromBooking($booking);

                // 3. Set status Trip sesuai kebutuhan
                if ($originalStatus === BookingStatus::IN_PROGRESS) {
                    $trip->update(['status' => TripStatus::ACTIVE->value]);
                    // Set TripDays jadi in_progress
                    $trip->days()->where('hari_ke', 1)->update(['status' => 'in_progress']);
                } elseif ($originalStatus === BookingStatus::COMPLETED) {
                    $trip->update(['status' => TripStatus::COMPLETED->value]);
                    // Set semua TripDays jadi completed
                    $trip->days()->update(['status' => 'completed']);
                } else {
                    // CONFIRMED → kembalikan ke status asli (booking tetap CONFIRMED)
                    $trip->update(['status' => TripStatus::SCHEDULED->value]);
                }

                // 4. Generate E-Ticket
                try {
                    $this->eTicketService->generateForTrip($trip);
                    $this->command->line(" Trip + E-Ticket dibuat: {$trip->kode} (dari {$booking->kode})");
                } catch (\Throwable $e) {
                    $this->command->warn("  E-Ticket gagal untuk {$trip->kode}: {$e->getMessage()}");
                }

            } catch (\Throwable $e) {
                $this->command->error("   Gagal buat trip untuk {$booking->kode}: {$e->getMessage()}");
            }
        }

        $this->command->info(' TripSeeder selesai.');
    }
}
