<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            // İlişkili müşteri yoksa fabrika kendisi üretir.
            'customer_id' => Customer::factory(),
            'title' => fake()->randomElement(['Ev', 'İş', 'Diğer']),
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('05#########'),
            'city' => fake()->randomElement(['İstanbul', 'Ankara', 'İzmir', 'Bursa']),
            'district' => fake()->randomElement(['Kadıköy', 'Çankaya', 'Konak', 'Nilüfer']),
            'neighborhood' => fake()->streetName(),
            'line1' => fake()->streetAddress(),
            'postal_code' => fake()->numerify('#####'),
        ];
    }
}
