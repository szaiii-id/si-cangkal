<?php

namespace Database\Seeders;

use App\Enums\PackageKategori;
use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\BookingParticipant;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\PackageFacility;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder untuk testing — idempotent (aman dijalankan berkali-kali).
 *
 * Pemakaian:
 *   $this->seed(TestSeeder::class);
 */
class TestSeeder extends Seeder
{
    public function run(): void
    {
        // === IDEMPOTENT: Truncate dulu (testing only) ===
        if (app()->environment('testing')) {
            $tables = [
                'e_tickets', 'refunds', 'payments',
                'daily_report_media', 'daily_reports',
                'trip_days', 'trips',
                'booking_participants', 'bookings',
                'package_facilities', 'package_days',
                'packages', 'mitra_bank_accounts',
                'profiles', 'users',
            ];

            foreach ($tables as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        }

        // ==========================================
        // 1. USERS
        // ==========================================

        /** @var User $admin */
        $admin = User::factory()->admin()->create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'username' => 'admintest',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);

        /** @var User $mitra */
        $mitra = User::factory()->mitra()->create([
            'name' => 'Mitra Test',
            'email' => 'mitra@test.com',
            'username' => 'mitratest',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);

        /** @var User $mitra2 */
        $mitra2 = User::factory()->mitra()->create([
            'name' => 'Mitra Dua',
            'email' => 'mitra2@test.com',
            'username' => 'mitradua',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);

        /** @var User $client */
        $client = User::factory()->client()->create([
            'name' => 'Client Test',
            'email' => 'client@test.com',
            'username' => 'clienttest',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);

        // ==========================================
        // 2. PROFILES
        // ==========================================

        Profile::factory()->forAdmin()->create([
            'user_id' => $admin->id,
            'nama_penyedia_usaha' => 'PT Admin Test',
            'telepon' => '021-1234567',
            'alamat' => 'Jl. Admin No. 1',
        ]);

        Profile::factory()->forMitra()->create([
            'user_id' => $mitra->id,
            'nama_penyedia_usaha' => 'PT Mitra Test',
            'telepon' => '08123456789',
            'alamat' => 'Jl. Mitra No. 2',
            'provinsi' => 'Sulawesi Selatan',
            'kota' => 'Makassar',
            'kode_pos' => '90111',
        ]);

        Profile::factory()->forMitra()->create([
            'user_id' => $mitra2->id,
            'nama_penyedia_usaha' => 'PT Mitra Dua',
            'telepon' => '08123456790',
        ]);

        // ==========================================
        // 3. BANK ACCOUNTS
        // ==========================================

        MitraBankAccount::create([
            'user_id' => $mitra->id,
            'tipe' => 'bank',
            'nama_penyedia' => 'BCA',
            'nomor_rekening' => '1234567890',
            'nama_pemilik' => 'Mitra Test',
            'is_primary' => true,
        ]);

        MitraBankAccount::create([
            'user_id' => $mitra->id,
            'tipe' => 'ewallet',
            'nama_penyedia' => 'DANA',
            'nomor_rekening' => '08123456789',
            'nama_pemilik' => 'Mitra Test',
            'is_primary' => false,
        ]);

        // ==========================================
        // 4. PACKAGES
        // ==========================================

        /** @var Package $package1 */
        $package1 = Package::factory()->forMitra($mitra)->aktif()->create([
            'kode' => 'PKG-TEST-001',
            'nama' => 'Paket Test Per Orang',
            'slug' => 'paket-test-per-orang',
            'kategori' => PackageKategori::WISATA_ALAM,
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'harga_per_orang' => '500000.00',
            'harga_per_rombongan' => null,
            'max_peserta' => null,
            'timezone' => 'Asia/Makassar',
        ]);

        PackageDay::factory()->create([
            'package_id' => $package1->id,
            'hari_ke' => 1,
            'judul' => 'Hari 1 — Penjemputan & Wisata',
            'deskripsi' => 'Penjemputan di bandara, wisata pantai',
        ]);

        PackageDay::factory()->create([
            'package_id' => $package1->id,
            'hari_ke' => 2,
            'judul' => 'Hari 2 — Kuliner & Kepulangan',
            'deskripsi' => 'Wisata kuliner, antar ke bandara',
        ]);

        PackageFacility::factory()->create([
            'package_id' => $package1->id,
            'tipe' => 'include',
            'nama' => 'Transportasi PP',
            'urutan' => 1,
        ]);

        PackageFacility::factory()->create([
            'package_id' => $package1->id,
            'tipe' => 'include',
            'nama' => 'Pemandu Wisata',
            'urutan' => 2,
        ]);

        PackageFacility::factory()->create([
            'package_id' => $package1->id,
            'tipe' => 'exclude',
            'nama' => 'Tiket Pesawat',
            'urutan' => 1,
        ]);

        // Paket rombongan
        /** @var Package $package2 */
        $package2 = Package::factory()->forMitra($mitra)->aktif()->create([
            'kode' => 'PKG-TEST-002',
            'nama' => 'Paket Test Rombongan',
            'slug' => 'paket-test-rombongan',
            'kategori' => PackageKategori::BUDAYA,
            'durasi_hari' => 3,
            'durasi_malam' => 2,
            'harga_per_orang' => '0.00',
            'harga_per_rombongan' => '5000000.00',
            'max_peserta' => 20,
            'timezone' => 'Asia/Makassar',
        ]);

        // ==========================================
        // 5. BOOKINGS
        // ==========================================

        /** @var Booking $booking1 */
        $booking1 = Booking::factory()
            ->forPackage($package1)
            ->forClient($client)
            ->pendingPayment()
            ->create([
                'kode' => 'BK-TEST-001',
                'tanggal_keberangkatan' => now()->addDays(7)->format('Y-m-d'),
                'jumlah_peserta' => 2,
                'total_harga' => '1000000.00',
                'expired_at' => now()->addHours(24),
                'mitra_id' => $mitra->id,
            ]);

        /** @var Booking $booking2 */
        $booking2 = Booking::factory()
            ->forPackage($package1)
            ->forClient($client)
            ->confirmed()
            ->create([
                'kode' => 'BK-TEST-002',
                'tanggal_keberangkatan' => now()->addDays(14)->format('Y-m-d'),
                'jumlah_peserta' => 2,
                'total_harga' => '1000000.00',
                'expired_at' => null,
                'mitra_id' => $mitra->id,
            ]);

        BookingParticipant::factory()
            ->count(2)
            ->create(['booking_id' => $booking2->id]);

        // ==========================================
        // 6. TRIPS
        // ==========================================

        /** @var Trip $trip1 */
        $trip1 = Trip::factory()
            ->forBooking($booking2)
            ->scheduled()
            ->create([
                'kode' => 'TRP-TEST-001',
                'tanggal_mulai' => now()->addDays(14)->format('Y-m-d'),
                'tanggal_selesai' => now()->addDays(15)->format('Y-m-d'),
                'status' => TripStatus::SCHEDULED,
                'timezone' => 'Asia/Makassar',
            ]);

        TripDay::factory()->create([
            'trip_id' => $trip1->id,
            'hari_ke' => 1,
            'tanggal' => now()->addDays(14)->format('Y-m-d'),
            'judul' => 'Hari 1',
            'status' => TripDay::STATUS_PENDING,
        ]);

        TripDay::factory()->create([
            'trip_id' => $trip1->id,
            'hari_ke' => 2,
            'tanggal' => now()->addDays(15)->format('Y-m-d'),
            'judul' => 'Hari 2',
            'status' => TripDay::STATUS_PENDING,
        ]);

        // ==========================================
        // 7. RINGKASAN
        // ==========================================

        $this->command->info(' TestSeeder selesai:');
        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Users', User::count()],
                ['Profiles', Profile::count()],
                ['Bank Accounts', MitraBankAccount::count()],
                ['Packages', Package::count()],
                ['Package Days', PackageDay::count()],
                ['Package Facilities', PackageFacility::count()],
                ['Bookings', Booking::count()],
                ['Booking Participants', BookingParticipant::count()],
                ['Trips', Trip::count()],
                ['Trip Days', TripDay::count()],
            ],
        );
    }
}
