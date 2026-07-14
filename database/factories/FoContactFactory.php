<?php

namespace Database\Factories;

use App\Models\FoContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoContact>
 */
class FoContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'profesi' => fake()->jobTitle(),
            'no_telepon' => fake()->numerify('08##########'),
            'catatan' => fake()->optional()->sentence(),
        ];
    }
}
