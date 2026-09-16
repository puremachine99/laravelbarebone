<?php

namespace Database\Factories;

use App\Models\InvestorDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestorDocument>
 */
class InvestorDocumentFactory extends Factory
{
    protected $model = InvestorDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'title' => $title,
            'description' => fake()->optional()->sentence(),
            'file_path' => 'investor-docs/'.app()->environment().'/'.fake()->uuid().'.pdf',
            'file_name' => $title.'.pdf',
            'original_file_name' => fake()->word().'.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'uploaded_by' => User::factory(),
        ];
    }
}
