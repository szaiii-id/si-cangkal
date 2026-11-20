<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class AdminProductionSeeder extends Seeder
{
    public function run(): void
    {
        /** @var string $email */
        $email = config('app.admin_prod.email');

        /** @var string $name */
        $name = config('app.admin_prod.name');

        /** @var string|null $configuredPassword */
        $configuredPassword = config('app.admin_prod.password');
        $plainPassword = filled($configuredPassword) ? $configuredPassword : Str::password(16);

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($plainPassword),
                'role' => 'admin',
                'email_verified_at' => now(),
                'must_change_password' => true,
            ],
        );

        Log::channel('audit')->info('Admin production seeded', [
            'user_id' => $admin->id,
            'email' => $admin->email,
            'plain_password' => $plainPassword,
        ]);

        $this->command->info("Akun Admin Production siap: {$admin->email}");
    }
}
