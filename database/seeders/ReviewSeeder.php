<?php

namespace Database\Seeders;

use App\Enums\TripStatus;
use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Database\Seeder;

/**
 * Contoh review client untuk data demo.
 *
 * Urutan pemakaian (manual):
 *   UserSeeder → ClientSeeder → PackageSeeder → BookingSeeder →
 *   TripSeeder → ReviewSeeder → NotificationSeeder
 *
 * Aturan yang dipakai seeder ini SAMA dengan ReviewService:
 * - hanya booking yang trip-nya benar-benar COMPLETED,
 * - pemilik booking harus ber-role client,
 * - satu booking hanya satu review (unique booking_id),
 * - notifikasi review_baru dikirim ke mitra pemilik booking.
 *
 * Idempotent: aman dijalankan berkali-kali.
 */
class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $contoh = [
            // booking kode => [rating, komentar]
            'BKG-'.now()->year.'-008' => [5, 'Pemandu sangat ramah dan itinerary sesuai jadwal. Perjalanan yang menyenangkan!'],
            'BKG-'.now()->year.'-009' => [4, 'Paketnya bagus, hanya saja waktu istirahat bisa diperpanjang sedikit.'],
            'BK-TEST-003' => [5, 'Trip Test selesai dengan lancar. Pelayanan mitra sangat memuaskan.'],
        ];

        $dibuat = 0;

        foreach ($contoh as $kode => [$rating, $komentar]) {
            $booking = Booking::where('kode', $kode)
                ->whereHas('trip', fn ($query) => $query
                    ->where('status', TripStatus::COMPLETED->value))
                ->first();

            if ($booking === null) {
                continue;
            }

            $client = $booking->client;
            if ($client === null || ! $client->isClient()) {
                continue;
            }

            // Satu booking = satu review (konsisten dengan constraint DB).
            $review = Review::firstOrCreate(
                ['booking_id' => $booking->id],
                [
                    'package_id' => $booking->package_id,
                    'user_id' => $booking->user_id,
                    'rating' => $rating,
                    'komentar' => $komentar,
                ],
            );

            if ($review->wasRecentlyCreated) {
                $dibuat++;
            }

            // Mitra menerima notifikasi review_baru (cocok dengan ReviewService).
            if ($booking->mitra !== null) {
                $sudahAda = AppNotification::where('user_id', $booking->mitra_id)
                    ->where('tipe', 'review_baru')
                    ->whereJsonContains('data->booking_id', $booking->id)
                    ->exists();

                if (! $sudahAda) {
                    AppNotification::create([
                        'user_id' => $booking->mitra_id,
                        'tipe' => 'review_baru',
                        'judul' => 'Review baru untuk paket Anda',
                        'pesan' => "Paket {$booking->package?->nama} mendapat review {$rating} bintang.",
                        'data' => [
                            'review_id' => $review->id,
                            'booking_id' => $booking->id,
                            'url' => "/mitra/bookings/{$booking->id}",
                        ],
                    ]);
                }
            }
        }

        $this->command->info(" ReviewSeeder selesai ({$dibuat} review baru).");
    }
}
