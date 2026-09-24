<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'pipeline_permissions' => 'array',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'birth_date' => 'date',
        'status_date' => 'date',
        'is_cltp' => 'boolean',
        'hide_kpi_heatmap' => 'boolean',
        'status' => EmployeeStatus::class,
    ];

    public function isActive(): bool
    {
        return $this->status === EmployeeStatus::Active;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function positionStruktural()
    {
        return $this->belongsTo(Position::class, 'position_struktural_id');
    }

    public function positionFungsional()
    {
        return $this->belongsTo(Position::class, 'position_fungsional_id');
    }
}
