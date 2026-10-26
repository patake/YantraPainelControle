<?php

namespace Database\Factories;

use App\Enums\SystemFeature;
use App\Models\System;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<System>
 */
class SystemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'T'.fake()->unique()->numerify('###').'_PRT',
            'name' => 'Yantra '.fake()->word().' Printer',
            'feature' => SystemFeature::Printer,
            'branding' => ['color' => '#b8323a'],
        ];
    }
}
