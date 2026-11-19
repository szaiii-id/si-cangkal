<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Memulai seeding database SI-CANGKAL.');
        $this->command->newLine();

        $this->call([
            UserSeeder::class,
            MitraBankAccountSeeder::class,
            ClientSeeder::class,
            PackageSeeder::class,
            BookingSeeder::class,
            TripSeeder::class,
            RefundSeeder::class,
            NotificationSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('Semua seeder selesai.');
        $this->command->newLine();

        $this->command->info('Ringkasan data:');
        $this->command->info('   - 1 Admin, 2 Mitra, 9 Client');
        $this->command->info('   - 6 Paket Wisata (semua AKTIF)');
        $this->command->info('   - 11 Booking (pending, verified, confirmed, in_progress, completed, cancelled)');
        $this->command->info('   - 5 Trip + E-Ticket (3 scheduled, 1 active, 1 completed)');
        $this->command->info('   - 2 Refund (1 pending, 1 succeeded)');
        $this->command->info('   - 8 Notifikasi');
        $this->command->newLine();

        $this->command->warn('Kredensial login:');
        $this->command->warn('   Password di-generate acak (16 karakter).');
        $this->command->warn('   Plaintext tersedia di: storage/logs/audit-YYYY-MM-DD.log');
        $this->command->warn('   Cari baris dengan key "plain_password" untuk email yang dibutuhkan.');
        $this->command->newLine();

        $this->command->info('Contoh mengambil password:');
        $this->command->info('   grep "admin@titiktemu.test" storage/logs/audit-*.log');
        $this->command->info('   grep "budi@ptabc.com" storage/logs/audit-*.log');
        $this->command->info('   grep "rina.kartika@gmail.com" storage/logs/audit-*.log');
    }
}
