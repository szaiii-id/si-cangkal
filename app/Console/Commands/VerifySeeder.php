<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class VerifySeeder extends Command
{
    protected $signature = 'e2e:verify-seeder';

    protected $description = 'Verify seeded users exist (for E2E)';

    public function handle(): int
    {
        $expected = [
            'admin@test.com' => 'admin',
            'mitra@test.com' => 'mitra',
            'client@test.com' => 'client',
        ];

        $missing = [];

        foreach ($expected as $email => $role) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $missing[] = "{$email} (tidak ada)";
            } elseif ($user->role !== $role) {
                $missing[] = "{$email} (role: {$user->role}, expected: {$role})";
            }
        }

        if (! empty($missing)) {
            $this->error('MISSING: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $this->info('OK');

        return self::SUCCESS;
    }
}
