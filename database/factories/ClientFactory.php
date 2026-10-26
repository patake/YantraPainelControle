<?php

namespace Database\Factories;

use App\Models\Client;
use App\Support\Cnpj;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Hospital '.fake()->unique()->lastName();

        return [
            'cnpj' => Cnpj::generate(),
            'legal_name' => $name.' Ltda.',
            'trade_name' => $name,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => '11'.fake()->numerify('9########'),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
