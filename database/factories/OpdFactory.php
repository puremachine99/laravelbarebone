<?php

namespace Database\Factories;

use App\Models\Opd;
use App\Models\Regency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opd>
 */
class OpdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'regency_id' => Regency::factory(),
            'name' => 'Dinas '.fake()->unique()->jobTitle(),
        ];
    }
}
