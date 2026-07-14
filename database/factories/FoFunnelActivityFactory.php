<?php

namespace Database\Factories;

use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoFunnelActivity>
 */
class FoFunnelActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fo_funnel_id' => FoFunnel::factory(),
            'stage' => 'pitching',
            'tanggal' => fake()->date(),
            'catatan' => fake()->optional()->sentence(),
        ];
    }
}
