<?php

namespace Database\Seeders;

use App\Models\MitraBankAccount;
use App\Models\User;
use Illuminate\Database\Seeder;

class MitraBankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $mitraPTABC = User::where('email', 'budi@ptabc.com')->first();
        $mitraAni = User::where('email', 'ani@gmail.com')->first();

        if (! $mitraPTABC || ! $mitraAni) {
            $this->command->warn('⚠️ Mitra tidak ditemukan. Jalankan UserSeeder dulu.');
            return;
        }

        // PT ABC Travel — 2 rekening
        MitraBankAccount::updateOrCreate(
            ['user_id' => $mitraPTABC->id, 'nomor_rekening' => '1234567890'],
            [
                'tipe' => 'bank',
                'nama_penyedia' => 'BCA',
                'nama_pemilik' => 'Budi Santoso',
                'is_primary' => true,
            ]
        );

        MitraBankAccount::updateOrCreate(
            ['user_id' => $mitraPTABC->id, 'nomor_rekening' => '9876543210'],
            [
                'tipe' => 'bank',
                'nama_penyedia' => 'BRI',
                'nama_pemilik' => 'Budi Santoso',
                'is_primary' => false,
            ]
        );

        // Ani Tour — 1 rekening
        MitraBankAccount::updateOrCreate(
            ['user_id' => $mitraAni->id, 'nomor_rekening' => '0987654321'],
            [
                'tipe' => 'bank',
                'nama_penyedia' => 'Mandiri',
                'nama_pemilik' => 'Ani Wijaya',
                'is_primary' => true,
            ]
        );

        $this->command->info('✅ MitraBankAccountSeeder selesai (3 rekening).');
    }
}