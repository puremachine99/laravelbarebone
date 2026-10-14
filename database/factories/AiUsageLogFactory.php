<?php

namespace Database\Factories;

use App\Enums\AiUsageKind;
use App\Enums\AiUsageOutcome;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $promptTokens = fake()->numberBetween(100, 3000);
        $outputTokens = fake()->numberBetween(50, 1000);

        return [
            'user_id' => User::factory(),
            'user_name' => fake()->name(),
            'department' => fake()->randomElement(['Finance', 'Technology', 'Partnership']),
            'kind' => AiUsageKind::Chat,
            'outcome' => AiUsageOutcome::Ok,
            'model' => 'gemini-3.7-flash',
            'finish_reason' => 'STOP',
            'prompt_tokens' => $promptTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $promptTokens + $outputTokens,
            'first_token_ms' => fake()->numberBetween(300, 2000),
            'duration_ms' => fake()->numberBetween(2000, 15000),
            'cost_usd' => null,
        ];
    }

    public function failed(string $errorCode = 'upstream_error'): static
    {
        return $this->state(fn () => [
            'outcome' => AiUsageOutcome::Error,
            'error_code' => $errorCode,
            'finish_reason' => null,
            'prompt_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'first_token_ms' => null,
        ]);
    }
}
