<?php

namespace Database\Seeders;

use App\Models\PipelineProfile;
use App\Models\PipelineStage;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $profile = PipelineProfile::create([
            'name' => 'Default Pitching Flow',
            'pipeline_name' => 'Pitching',
            'is_active' => true,
        ]);

        $stages = [
            ['stage_name' => 'Rencana', 'order_index' => 1],
            ['stage_name' => 'Terlaksana', 'order_index' => 2],
            ['stage_name' => 'Tidak Terlaksana', 'order_index' => 3],
        ];

        foreach ($stages as $stage) {
            PipelineStage::create([
                'pipeline_profile_id' => $profile->id,
                'stage_name' => $stage['stage_name'],
                'order_index' => $stage['order_index'],
                'is_active' => true,
            ]);
        }
    }
}
