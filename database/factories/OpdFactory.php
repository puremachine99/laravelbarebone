<?php

namespace Database\Factories;

use App\Enums\OpdJenis;
use App\Models\Opd;
use App\Models\Regency;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opd>
 */
class OpdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'regency_id' => Regency::factory(),
            'name' => 'Dinas '.fake()->unique()->jobTitle(),
        ];
    }

    public function opd(): static
    {
        return $this->state(fn () => ['jenis' => OpdJenis::Opd->value]);
    }

    public function kementerian(): static
    {
        return $this->state(fn () => [
            'jenis' => OpdJenis::Kementerian->value,
            'name' => 'Kementerian '.fake()->unique()->word(),
            'regency_id' => null,
            'region_id' => Region::factory(),
        ]);
    }

    public function unitKesehatan(): static
    {
        return $this->state(fn () => ['jenis' => OpdJenis::UnitKesehatan->value, 'name' => 'RSUD '.fake()->unique()->city()]);
    }

    public function pendidikan(): static
    {
        return $this->state(fn () => ['jenis' => OpdJenis::Pendidikan->value, 'name' => 'Sekolah '.fake()->unique()->city()]);
    }
}
