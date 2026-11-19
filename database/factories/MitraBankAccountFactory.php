<?php

namespace Database\Factories;

use App\Models\MitraBankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MitraBankAccount>
 */
class MitraBankAccountFactory extends Factory
{
    protected $model = MitraBankAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->mitra(),
            'tipe' => fake()->randomElement(['bank', 'ewallet']),
            'nama_penyedia' => fake()->randomElement([
                'BCA', 'BRI', 'BNI', 'Mandiri', 'BSI',
                'DANA', 'OVO', 'GoPay', 'ShopeePay',
            ]),
            'nomor_rekening' => fake()->numerify('##########'),
            'nama_pemilik' => fake()->name(),
            'is_primary' => false,
        ];
    }

    /**
     * State: rekening utama (is_primary = true).
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * State: rekening bank.
     */
    public function bank(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipe' => 'bank',
            'nama_penyedia' => fake()->randomElement(['BCA', 'BRI', 'BNI', 'Mandiri', 'BSI']),
        ]);
    }

    /**
     * State: rekening e-wallet.
     */
    public function ewallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipe' => 'ewallet',
            'nama_penyedia' => fake()->randomElement(['DANA', 'OVO', 'GoPay', 'ShopeePay']),
        ]);
    }
}
