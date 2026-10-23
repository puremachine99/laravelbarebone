<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'birth_date' => 'date',
        'status_date' => 'date',
        'is_cltp' => 'boolean',
        'status' => EmployeeStatus::class,
    ];

    public function isActive(): bool
    {
        return $this->status === EmployeeStatus::Active;
    }

    /**
     * Only employees who are still working; the default for pickers where new work is assigned.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EmployeeStatus::Active);
    }

    /**
     * Name for dropdowns/tables, flagged when the employee is no longer active so past work stays attributable.
     */
    public function optionName(): ?string
    {
        $name = $this->user?->name;

        return $name && ! $this->isActive() ? "{$name} ({$this->status->label()})" : $name;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function positionStruktural()
    {
        return $this->belongsTo(Position::class, 'position_struktural_id');
    }

    public function positionFungsional()
    {
        return $this->belongsTo(Position::class, 'position_fungsional_id');
    }

    public function positionTeknis()
    {
        return $this->belongsTo(Position::class, 'position_teknis_id');
    }
}
