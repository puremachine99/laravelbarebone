<?php

namespace Database\Factories;

use App\Models\FoTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoTarget>
 */
class FoTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => now()->year,
            'metric_key' => 'leads',
            'yearly_target' => fake()->numberBetween(50, 200),
        ];
    }
}
