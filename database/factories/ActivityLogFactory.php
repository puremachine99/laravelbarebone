<?php

namespace Database\Factories;

use App\Enums\ActivityLogAction;
use App\Models\ActivityLog;
use App\Models\FoFunnel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $funnel = FoFunnel::factory()->create();

        return [
            'loggable_type' => $funnel->getMorphClass(),
            'loggable_id' => $funnel->id,
            'causer_id' => User::factory(),
            'action' => ActivityLogAction::StageMoved,
            'changes' => [
                'before' => ['stage' => 'leads'],
                'after' => ['stage' => 'pitching'],
            ],
        ];
    }
}
