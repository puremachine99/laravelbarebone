<?php

namespace Database\Factories;

use App\Models\ApiConsumer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiConsumer>
 */
class ApiConsumerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = ApiConsumer::TOKEN_PREFIX.fake()->unique()->regexify('[A-Za-z0-9]{40}');

        return [
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'allowed_origins' => ['app.example.com'],
            'allow_server_requests' => false,
            'rate_limit_per_minute' => 120,
            'token_prefix' => substr($token, 0, 12),
            'token_hash' => ApiConsumer::hashToken($token),
        ];
    }

    public function serverToServer(): static
    {
        return $this->state(fn () => ['allow_server_requests' => true]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
