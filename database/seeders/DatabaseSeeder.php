<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Memulai seeding database production SI-CANGKAL.');
        $this->command->newLine();

        $this->call([
            AdminProductionSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('Seeding admin production selesai.');
        $this->command->warn('Segera login menggunakan akun admin dan lakukan pergantian password wajib.');
    }
}
