<?php

namespace Database\Factories;

use App\Models\AiMentoringCancellation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiMentoringCancellation>
 */
class AiMentoringCancellationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'remote_session_id' => 'map_'.Str::lower(Str::random(32)),
            'unit_key' => 'finance',
        ];
    }
}
