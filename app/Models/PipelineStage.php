<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineStage extends Model
{
    protected $table = 'pmo_pipeline_stages';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'order_index' => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PipelineProfile::class, 'pipeline_profile_id');
    }
}
