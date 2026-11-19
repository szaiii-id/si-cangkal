<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingParticipant;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        // ========================================
        // 11 BOOKING REALISTIS
        // ========================================
        $bookings = [
            // ---- PENDING PAYMENT (belum bayar) ----
            [
                'kode' => 'BKG-'.now()->year.'-001',
                'client_email' => 'rina.kartika@gmail.com',
                'package_kode' => 'PKG-'.now()->year.'-001',
                'tanggal_keberangkatan' => now()->addDays(21)->format('Y-m-d'),
                'status' => BookingStatus::PENDING_PAYMENT,
                'expired_at' => now()->addHours(24),
                'participants' => [
                    ['nama' => 'Rina Kartika Sari', 'umur' => 35, 'no_hp' => '081234567001', 'catatan' => null],
                    ['nama' => 'Dimas Saputra', 'umur' => 38, 'no_hp' => '081234567002', 'catatan' => 'Suami, alergi seafood'],
                ],
                'catatan' => 'Mohon dijemput pagi karena flight pagi.',
            ],
            [
                'kode' => 'BKG-'.now()->year.'-002',
                'client_email' => 'andi.pratama@yahoo.com',
                'package_kode' => 'PKG-'.now()->year.'-004',
                'tanggal_keberangkatan' => now()->addDays(18)->format('Y-m-d'),
                'status' => BookingStatus::PENDING_PAYMENT,
                'expired_at' => now()->addHours(24),
                'participants' => [
                    ['nama' => 'Andi Pratama', 'umur' => 32, 'no_hp' => '081234567003', 'catatan' => null],
                    ['nama' => 'Lina Marlina', 'umur' => 30, 'no_hp' => '081234567004', 'catatan' => 'Istri'],
                    ['nama' => 'Raka Pratama', 'umur' => 8, 'no_hp' => null, 'catatan' => 'Anak, tidak kuat dingin'],
                    ['nama' => 'Sinta Pratama', 'umur' => 6, 'no_hp' => null, 'catatan' => 'Anak'],
                ],
                'catatan' => 'Keluarga dengan 2 anak kecil, butuh kursi tambahan.',
            ],

            // ---- PENDING (sudah upload bukti, menunggu verifikasi) ----
            [
                'kode' => 'BKG-'.now()->year.'-003',
                'client_email' => 'siti.nurhaliza@gmail.com',
                'package_kode' => 'PKG-'.now()->year.'-006',
                'tanggal_keberangkatan' => now()->addDays(14)->format('Y-m-d'),
                'status' => BookingStatus::PENDING_PAYMENT,
                'expired_at' => now()->addHours(20),
                'payment_proof_path' => 'payment_proofs/dummy-proof-001.jpg',
                'participants' => [
                    ['nama' => 'Siti Nurhaliza', 'umur' => 28, 'no_hp' => '081234567005', 'catatan' => null],
                    ['nama' => 'Ahmad Fauzi', 'umur' => 30, 'no_hp' => '081234567006', 'catatan' => 'Suami, vegetarian'],
                ],
            ],

            // ---- PAYMENT VERIFIED ----
            [
                'kode' => 'BKG-'.now()->year.'-004',
                'client_email' => 'budi.hartono@gmail.com',
                'package_kode' => 'PKG-'.now()->year.'-002',
                'tanggal_keberangkatan' => now()->addDays(10)->format('Y-m-d'),
                'status' => BookingStatus::PAYMENT_VERIFIED,
                'participants' => [
                    ['nama' => 'Budi Hartono', 'umur' => 45, 'no_hp' => '081234567007', 'catatan' => 'Memiliki riwayat asma'],
                    ['nama' => 'Ratna Sari', 'umur' => 42, 'no_hp' => '081234567008', 'catatan' => null],
                    ['nama' => 'Kevin Hartono', 'umur' => 15, 'no_hp' => null, 'catatan' => 'Remaja'],
                ],
            ],

            // ---- CONFIRMED ----
            [
                'kode' => 'BKG-'.now()->year.'-005',
                'client_email' => 'dewi.lestari@gmail.com',
                'package_kode' => 'PKG-'.now()->year.'-005',
                'tanggal_keberangkatan' => now()->addDays(7)->format('Y-m-d'),
                'status' => BookingStatus::CONFIRMED,
                'participants' => [
                    ['nama' => 'Dewi Lestari', 'umur' => 29, 'no_hp' => '081234567009', 'catatan' => null],
                    ['nama' => 'Bagas Saputra', 'umur' => 31, 'no_hp' => '081234567010', 'catatan' => null],
                    ['nama' => 'Citra Dewi', 'umur' => 27, 'no_hp' => '081234567011', 'catatan' => 'Teman, alergi kacang'],
                    ['nama' => 'Dian Sastro', 'umur' => 33, 'no_hp' => '081234567012', 'catatan' => null],
                ],
                'catatan' => 'Rombongan kantor, mohon invoice terpisah.',
            ],
            [
                'kode' => 'BKG-'.now()->year.'-006',
                'client_email' => 'reza.aditya@outlook.com',
                'package_kode' => 'PKG-'.now()->year.'-003',
                'tanggal_keberangkatan' => now()->addDays(5)->format('Y-m-d'),
                'status' => BookingStatus::CONFIRMED,
                'participants' => [
                    ['nama' => 'Reza Aditya', 'umur' => 26, 'no_hp' => '081234567013', 'catatan' => null],
                    ['nama' => 'Maya Sari', 'umur' => 25, 'no_hp' => '081234567014', 'catatan' => 'Pacar'],
                ],
            ],

            // ---- IN PROGRESS (sedang berjalan) ----
            [
                'kode' => 'BKG-'.now()->year.'-007',
                'client_email' => 'maya.anggraini@gmail.com',
                'package_kode' => 'PKG-'.now()->year.'-006',
                'tanggal_keberangkatan' => now()->subDay()->format('Y-m-d'),
                'status' => BookingStatus::IN_PROGRESS,
                'participants' => [
                    ['nama' => 'Maya Anggraini', 'umur' => 34, 'no_hp' => '081234567015', 'catatan' => null],
                    ['nama' => 'Fajar Nugroho', 'umur' => 36, 'no_hp' => '081234567016', 'catatan' => null],
                    ['nama' => 'Aisyah Putri', 'umur' => 7, 'no_hp' => null, 'catatan' => 'Anak, alergi susu'],
                    ['nama' => 'Rizky Pratama', 'umur' => 5, 'no_hp' => null, 'catatan' => 'Anak'],
                ],
            ],

            // ---- COMPLETED (selesai) ----
            [
                'kode' => 'BKG-'.now()->year.'-008',
                'client_email' => 'fajar.nugroho@gmail.com',
                'package_kode' => 'PKG-'.now()->year.'-001',
                'tanggal_keberangkatan' => now()->subDays(7)->format('Y-m-d'),
                'status' => BookingStatus::COMPLETED,
                'participants' => [
                    ['nama' => 'Fajar Nugroho', 'umur' => 36, 'no_hp' => '081234567017', 'catatan' => null],
                    ['nama' => 'Sri Wahyuni', 'umur' => 34, 'no_hp' => '081234567018', 'catatan' => 'Istri'],
                    ['nama' => 'Bayu Nugroho', 'umur' => 10, 'no_hp' => null, 'catatan' => 'Anak'],
                ],
            ],
            [
                'kode' => 'BKG-'.now()->year.'-009',
                'client_email' => 'rina.kartika@gmail.com',
                'package_kode' => 'PKG-'.now()->year.'-002',
                'tanggal_keberangkatan' => now()->subDays(14)->format('Y-m-d'),
                'status' => BookingStatus::COMPLETED,
                'participants' => [
                    ['nama' => 'Rina Kartika Sari', 'umur' => 35, 'no_hp' => '081234567001', 'catatan' => null],
                    ['nama' => 'Dimas Saputra', 'umur' => 38, 'no_hp' => '081234567002', 'catatan' => null],
                ],
            ],

            // ---- CANCELLED ----
            [
                'kode' => 'BKG-'.now()->year.'-010',
                'client_email' => 'andi.pratama@yahoo.com',
                'package_kode' => 'PKG-'.now()->year.'-003',
                'tanggal_keberangkatan' => now()->addDay()->format('Y-m-d'),
                'status' => BookingStatus::CANCELLED,
                'participants' => [
                    ['nama' => 'Andi Pratama', 'umur' => 32, 'no_hp' => '081234567003', 'catatan' => null],
                    ['nama' => 'Lina Marlina', 'umur' => 30, 'no_hp' => '081234567004', 'catatan' => null],
                ],
                'catatan' => 'Dibatalkan karena ada urusan mendadak. Mohon refund.',
            ],
            [
                'kode' => 'BKG-'.now()->year.'-011',
                'client_email' => 'siti.nurhaliza@gmail.com',
                'package_kode' => 'PKG-'.now()->year.'-004',
                'tanggal_keberangkatan' => now()->addDays(4)->format('Y-m-d'),
                'status' => BookingStatus::CANCELLED,
                'participants' => [
                    ['nama' => 'Siti Nurhaliza', 'umur' => 28, 'no_hp' => '081234567005', 'catatan' => null],
                    ['nama' => 'Ahmad Fauzi', 'umur' => 30, 'no_hp' => '081234567006', 'catatan' => null],
                ],
                'catatan' => 'Auto-cancel: melebihi batas waktu pembayaran 24 jam.',
            ],
        ];

        foreach ($bookings as $data) {
            $client = User::where('email', $data['client_email'])->first();
            $package = Package::where('kode', $data['package_kode'])->first();

            if (! $client instanceof User || ! $package instanceof Package) {
                $this->command->warn("  Skip {$data['kode']}: client/package tidak ditemukan.");

                continue;
            }

            $jumlahPeserta = count($data['participants']);
            $totalHarga = $package->calculateTotalHarga($jumlahPeserta);

            $booking = Booking::updateOrCreate(
                ['kode' => $data['kode']],
                [
                    'package_id' => $package->id,
                    'user_id' => $client->id,
                    'mitra_id' => $package->mitra_id,
                    'tanggal_keberangkatan' => $data['tanggal_keberangkatan'],
                    'jumlah_peserta' => $jumlahPeserta,
                    'total_harga' => $totalHarga,
                    'status' => $data['status']->value,
                    'payment_proof_path' => $data['payment_proof_path'] ?? null,
                    'payment_verified_at' => in_array($data['status'], [BookingStatus::CONFIRMED, BookingStatus::IN_PROGRESS, BookingStatus::COMPLETED], true) ? now() : null,
                    'expired_at' => $data['expired_at'] ?? null,
                    'catatan' => $data['catatan'] ?? null,
                ]
            );

            // Hapus peserta lama, buat ulang
            BookingParticipant::where('booking_id', $booking->id)->delete();
            foreach ($data['participants'] as $p) {
                BookingParticipant::create([
                    'booking_id' => $booking->id,
                    'nama' => $p['nama'],
                    'umur' => $p['umur'],
                    'no_hp' => $p['no_hp'],
                    'catatan' => $p['catatan'],
                ]);
            }
        }

        $this->command->info('BookingSeeder selesai (11 booking, berbagai status).');
    }
}
