<?php

namespace Database\Factories;

use App\Models\FoContact;
use App\Models\FoFunnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoFunnel>
 */
class FoFunnelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fo_contact_id' => FoContact::factory(),
            'stage' => 'leads',
            'sumber' => fake()->randomElement(['Murni', 'EXPFO', 'Referral', 'SIDS']),
            'tgl_leads' => fake()->date(),
        ];
    }
}
