<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder untuk user dasar (admin, mitra, client).
 *
 * Perubahan dari versi lama:
 * - Password di-generate acak via `Str::password(16)` (S-04), bukan
 *   `password123`.
 * - Semua user dibuat dengan `must_change_password = true`, kecuali
 *   admin utama (lihat catatan di method `seedAdmin`).
 * - Password plaintext TIDAK ditampilkan di console; hanya di
 *   `AuditLogger` (channel `audit`) agar tidak bocor ke terminal
 *   history atau CI log.
 * - Menggunakan `updateOrCreate` dengan `firstOrCreate` yang lebih
 *   konservatif: bila user sudah ada, password TIDAK direset.
 */
final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedMitraPTABC();
        $this->seedMitraAni();
        $this->seedClient();

        $this->command->info('UserSeeder selesai. Password plaintext hanya tersedia di channel audit (storage/logs/audit-*.log).');
    }

    /**
     * Admin utama.
     *
     * CATATAN KEAMANAN:
     * - Admin utama di-seed dengan `must_change_password = false` karena
     *   ini akun bootstrapping untuk mengakses aplikasi pertama kali.
     *   Bila Anda ingin admin utama juga wajib ganti password, ubah
     *   flag di bawah menjadi `true`.
     * - Password tetap di-generate acak; TIDAK ditampilkan di console.
     */
    private function seedAdmin(): void
    {
        $this->createUserIfMissing(
            email: 'admin@sicangkal.test',
            attributes: [
                'name' => 'Administrator Utama',
                'username' => 'admin',
                'role' => 'admin',
                'timezone' => User::DEFAULT_TIMEZONE,
                'must_change_password' => false,
            ],
            profileAttributes: ['tipe' => 'admin', 'meta' => []],
        );
    }

    private function seedMitraPTABC(): void
    {
        $user = $this->createUserIfMissing(
            email: 'budi@ptabc.com',
            attributes: [
                'name' => 'Budi Santoso',
                'username' => 'ptabc',
                'role' => 'mitra',
                'timezone' => User::DEFAULT_TIMEZONE,
                'must_change_password' => true,
            ],
            profileAttributes: [
                'tipe' => 'mitra',
                'nama_penyedia_usaha' => 'PT ABC Travel',
                'nomor_identitas' => '12.345.678.9-012.000',
                'alamat' => 'Jl. Merdeka No. 123',
                'telepon' => '+62 811 2233 4455',
                'provinsi' => 'Kalimantan Selatan',
                'kota' => 'Banjarmasin',
                'kode_pos' => '70111',
                'deskripsi' => 'Travel agent terpercaya sejak 2010.',
                'meta' => [],
            ],
        );

        unset($user);
    }

    private function seedMitraAni(): void
    {
        $user = $this->createUserIfMissing(
            email: 'ani@gmail.com',
            attributes: [
                'name' => 'Ani Wijaya',
                'username' => 'aniwijaya',
                'role' => 'mitra',
                'timezone' => User::DEFAULT_TIMEZONE,
                'must_change_password' => true,
            ],
            profileAttributes: [
                'tipe' => 'mitra',
                'nama_penyedia_usaha' => 'Ani Tour & Travel',
                'nomor_identitas' => '1234567890123456',
                'alamat' => 'Jl. Sunset Road No. 45',
                'telepon' => '+62 812 3456 7890',
                'provinsi' => 'Bali',
                'kota' => 'Denpasar',
                'kode_pos' => '80119',
                'deskripsi' => 'Tour guide freelance spesialis Bali.',
                'meta' => [],
            ],
        );

        unset($user);
    }

    private function seedClient(): void
    {
        $user = $this->createUserIfMissing(
            email: 'client@sicangkal.test',
            attributes: [
                'name' => 'Budi Client',
                'username' => 'budiclient',
                'role' => 'client',
                'timezone' => User::DEFAULT_TIMEZONE,
                'must_change_password' => true,
            ],
            profileAttributes: ['tipe' => 'client', 'meta' => []],
        );

        unset($user);
    }

    /**
     * Buat user (beserta profile) bila belum ada.
     *
     * Bila user sudah ada, method ini TIDAK mengubah apa pun
     * (termasuk password). Ini mencegah re-seed mereset password
     * user yang sudah aktif.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $profileAttributes
     */
    private function createUserIfMissing(
        string $email,
        array $attributes,
        array $profileAttributes,
    ): User {
        $existing = User::where('email', $email)->first();

        if ($existing !== null) {
            $this->command->warn("  User {$email} sudah ada, dilewati.");

            return $existing;
        }

        $plainPassword = Str::password(16, symbols: true);

        $user = User::create([
            'name' => $attributes['name'],
            'username' => $attributes['username'],
            'email' => $email,
            'password' => $plainPassword,
            'role' => $attributes['role'],
            'timezone' => $attributes['timezone'],
            'must_change_password' => $attributes['must_change_password'],
        ]);

        Profile::create([
            'user_id' => $user->id,
            ...$profileAttributes,
        ]);

        AuditLogger::log('seeder.user_created', [
            'actor_id' => null,
            'actor_role' => 'system',
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'plain_password' => $plainPassword,
        ]);

        $this->command->info("  User {$email} dibuat (password di channel audit).");

        return $user;
    }
}
