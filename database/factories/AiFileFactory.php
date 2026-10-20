<?php

namespace Database\Factories;

use App\Models\AiFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiFile>
 */
class AiFileFactory extends Factory
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
            'ai_message_id' => null,
            'remote_file_id' => 'file_'.Str::random(32),
            'origin' => AiFile::ORIGIN_UPLOAD,
            'name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1_000, 500_000),
        ];
    }

    public function generated(): static
    {
        return $this->state(fn () => [
            'origin' => AiFile::ORIGIN_GENERATED,
            'name' => 'laporan.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
