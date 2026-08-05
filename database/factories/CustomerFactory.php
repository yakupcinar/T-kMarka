<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test verisi üreticisi. Testlerde 10 satır elle veri doldurmak yerine
 * `Customer::factory()->create()` yeterli olsun diye.
 *
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'sifre1234',
            'phone' => fake()->numerify('05#########'),
            'accepts_marketing' => false,
        ];
    }

    /**
     * MİSAFİR müşteri — e-postası ve parolası yok (domain-model §6).
     * Sipariş akışı testlerinde bu durum sık kullanılacak.
     */
    public function misafir(): static
    {
        return $this->state(fn () => [
            'email' => null,
            'password' => null,
        ]);
    }

    /** Pazarlama iznini açık kullanıcı — KVKK akışı testleri için. */
    public function pazarlamaIzinli(): static
    {
        return $this->state(fn () => ['accepts_marketing' => true]);
    }
}
