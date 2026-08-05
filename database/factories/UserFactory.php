<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Marka PERSONELİ fabrikası (müşteri değil — o CustomerFactory).
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'sifre1234',
            'is_owner' => false,
        ];
    }

    /**
     * Markanın sahibi. is_owner $fillable dışında olduğu için ancak
     * fabrika/seeder gibi güvenilir yerlerden atanabiliyor — istekten gelemez.
     */
    public function sahip(): static
    {
        return $this->state(fn () => ['is_owner' => true]);
    }
}
