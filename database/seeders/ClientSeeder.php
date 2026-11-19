<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder untuk 8 client dummy.
 *
 * Perubahan dari versi lama:
 * - Password di-generate acak via `Str::password(16)` (S-04), bukan
 *   `password123`.
 * - Semua client dibuat dengan `must_change_password = true`.
 * - Password plaintext hanya di `AuditLogger` (channel `audit`),
 *   tidak ditampilkan di console.
 * - `createClientIfMissing` menggantikan `updateOrCreate` untuk
 *   mencegah reset password client yang sudah aktif.
 */
final class ClientSeeder extends Seeder
{
    /**
     * @var list<array{name: string, username: string, email: string}>
     */
    private const CLIENTS = [
        ['name' => 'Rina Kartika Sari', 'username' => 'rinakartika', 'email' => 'rina.kartika@gmail.com'],
        ['name' => 'Andi Pratama', 'username' => 'andipratama', 'email' => 'andi.pratama@yahoo.com'],
        ['name' => 'Siti Nurhaliza', 'username' => 'sitinurhaliza', 'email' => 'siti.nurhaliza@gmail.com'],
        ['name' => 'Budi Hartono', 'username' => 'budihartono', 'email' => 'budi.hartono@gmail.com'],
        ['name' => 'Dewi Lestari', 'username' => 'dewilestari', 'email' => 'dewi.lestari@gmail.com'],
        ['name' => 'Reza Aditya', 'username' => 'rezaaditya', 'email' => 'reza.aditya@outlook.com'],
        ['name' => 'Maya Anggraini', 'username' => 'mayaanggraini', 'email' => 'maya.anggraini@gmail.com'],
        ['name' => 'Fajar Nugroho', 'username' => 'fajarnugroho', 'email' => 'fajar.nugroho@gmail.com'],
    ];

    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        foreach (self::CLIENTS as $data) {
            $user = $this->createClientIfMissing($data);

            if ($user === null) {
                $skipped++;

                continue;
            }

            $created++;
        }

        $this->command->info("ClientSeeder selesai: {$created} dibuat, {$skipped} dilewati.");
        $this->command->info('Password plaintext hanya tersedia di channel audit (storage/logs/audit-*.log).');
    }

    /**
     * Buat client baru bila belum ada.
     *
     * Bila user dengan email tersebut sudah ada, method ini mengembalikan
     * `null` (tidak mengubah apa pun). Caller menghitung sebagai "dilewati".
     *
     * @param  array{name: string, username: string, email: string}  $data
     */
    private function createClientIfMissing(array $data): ?User
    {
        $existing = User::where('email', $data['email'])->first();

        if ($existing !== null) {
            $this->command->warn("  Client {$data['email']} sudah ada, dilewati.");

            return null;
        }

        $plainPassword = Str::password(16, symbols: true);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $plainPassword,
            'role' => 'client',
            'timezone' => User::DEFAULT_TIMEZONE,
            'must_change_password' => true,
        ]);

        Profile::create([
            'user_id' => $user->id,
            'tipe' => 'client',
            'meta' => [],
        ]);

        AuditLogger::log('seeder.client_created', [
            'actor_id' => null,
            'actor_role' => 'system',
            'user_id' => $user->id,
            'email' => $user->email,
            'plain_password' => $plainPassword,
        ]);

        $this->command->info("  Client {$data['email']} dibuat (password di channel audit).");

        return $user;
    }
}
