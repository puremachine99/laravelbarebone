<?php

namespace Database\Factories;

use App\Enums\AiErrorCode;
use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMessage>
 */
class AiMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'role' => AiMessageRole::User,
            'content' => fake()->paragraph(),
            'status' => AiMessageStatus::Complete,
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn () => ['role' => AiMessageRole::Assistant]);
    }

    public function failed(AiErrorCode $code = AiErrorCode::UpstreamError): static
    {
        return $this->state(fn () => [
            'role' => AiMessageRole::Assistant,
            'content' => '',
            'status' => AiMessageStatus::Error,
            'error_code' => $code,
        ]);
    }
}
