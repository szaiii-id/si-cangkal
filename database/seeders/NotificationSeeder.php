<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $mitraABC = User::where('email', 'budi@ptabc.com')->first();
        $mitraAni = User::where('email', 'ani@gmail.com')->first();
        $clientRina = User::where('email', 'rina.kartika@gmail.com')->first();
        $clientAndi = User::where('email', 'andi.pratama@yahoo.com')->first();
        $clientSiti = User::where('email', 'siti.nurhaliza@gmail.com')->first();
        $clientDewi = User::where('email', 'dewi.lestari@gmail.com')->first();
        $clientMaya = User::where('email', 'maya.anggraini@gmail.com')->first();

        // Notifikasi review_baru diambil dari review yang dibuat ReviewSeeder,
        // bukan ID hardcode — aman kalau data demo dijalankan ulang.
        $review = Review::whereHas(
            'booking',
            fn ($query) => $query->where('kode', 'BKG-'.now()->year.'-008'),
        )->first();
        $mitraReview = $review?->booking?->mitra;

        $notifications = [
            // Untuk Mitra PT ABC
            ['user' => $mitraABC, 'tipe' => 'payment_proof_uploaded', 'judul' => 'Bukti Transfer Baru', 'pesan' => 'Andi Pratama telah mengunggah bukti transfer untuk booking BKG-'.now()->year.'-002. Silakan verifikasi.', 'data' => ['booking_kode' => 'BKG-'.now()->year.'-002'], 'read_at' => null],
            ['user' => $mitraABC, 'tipe' => 'booking_cancelled', 'judul' => 'Booking Dibatalkan', 'pesan' => 'Andi Pratama membatalkan booking BKG-'.now()->year.'-010. Alasan: urusan mendadak.', 'data' => ['booking_kode' => 'BKG-'.now()->year.'-010'], 'read_at' => now()->subHours(2)],

            // Untuk Mitra Ani
            ['user' => $mitraAni, 'tipe' => 'payment_proof_uploaded', 'judul' => 'Bukti Transfer Baru', 'pesan' => 'Siti Nurhaliza telah mengunggah bukti transfer untuk booking BKG-'.now()->year.'-003.', 'data' => ['booking_kode' => 'BKG-'.now()->year.'-003'], 'read_at' => null],

            // Review baru (dari ReviewSeeder)
            ['user' => $mitraReview, 'tipe' => 'review_baru', 'judul' => 'Review baru untuk paket Anda', 'pesan' => $review !== null ? "Paket {$review->package->nama} mendapat review {$review->rating} bintang." : '', 'data' => $review !== null ? ['review_id' => $review->id, 'booking_id' => $review->booking_id, 'url' => "/mitra/bookings/{$review->booking_id}"] : [], 'read_at' => null],

            // Untuk Client
            ['user' => $clientDewi, 'tipe' => 'booking_confirmed', 'judul' => 'Booking Dikonfirmasi', 'pesan' => 'Pembayaran Anda untuk booking BKG-'.now()->year.'-005 telah diverifikasi. E-ticket siap diunduh.', 'data' => ['booking_kode' => 'BKG-'.now()->year.'-005'], 'read_at' => null],
            ['user' => $clientMaya, 'tipe' => 'booking_confirmed', 'judul' => 'Trip Sedang Berjalan', 'pesan' => 'Trip Anda untuk booking BKG-'.now()->year.'-007 sudah dimulai. Selamat menikmati perjalanan!', 'data' => ['booking_kode' => 'BKG-'.now()->year.'-007'], 'read_at' => now()->subHours(5)],
            ['user' => $clientRina, 'tipe' => 'booking_confirmed', 'judul' => 'Trip Selesai', 'pesan' => 'Terima kasih telah menggunakan layanan kami untuk BKG-'.now()->year.'-009. Sampai jumpa lagi!', 'data' => ['booking_kode' => 'BKG-'.now()->year.'-009'], 'read_at' => now()->subDays(10)],
            ['user' => $clientAndi, 'tipe' => 'refund_succeeded', 'judul' => 'Refund Berhasil', 'pesan' => 'Dana refund sebesar Rp 1.500.000 untuk booking BKG-'.now()->year.'-011 telah diproses.', 'data' => ['booking_kode' => 'BKG-'.now()->year.'-011', 'amount' => 1500000], 'read_at' => null],
            ['user' => $clientSiti, 'tipe' => 'payment_rejected', 'judul' => 'Bukti Transfer Ditolak', 'pesan' => 'Bukti transfer untuk BKG-'.now()->year.'-011 ditolak. Alasan: nominal tidak sesuai.', 'data' => ['booking_kode' => 'BKG-'.now()->year.'-011'], 'read_at' => now()->subHours(1)],
        ];

        foreach ($notifications as $n) {
            if (! $n['user']) {
                continue;
            }

            AppNotification::create([
                'user_id' => $n['user']->id,
                'tipe' => $n['tipe'],
                'judul' => $n['judul'],
                'pesan' => $n['pesan'],
                'data' => $n['data'],
                'read_at' => $n['read_at'],
            ]);
        }

        $this->command->info(' NotificationSeeder selesai ('.count($notifications).' notifikasi).');
    }
}
