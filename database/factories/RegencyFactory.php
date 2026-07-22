<?php

namespace Database\Factories;

use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Regency>
 */
class RegencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'province_id' => Province::factory(),
            'region_id' => Region::factory(),
            'name' => fake()->unique()->city(),
            'code' => fake()->unique()->numerify('####'),
            'type' => fake()->randomElement(['Kabupaten', 'Kota']),
        ];
    }
}
