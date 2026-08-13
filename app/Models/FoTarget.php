<?php

namespace App\Models;

use Database\Factories\FoTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoTarget extends Model
{
    /** @use HasFactory<FoTargetFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'yearly_target' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function logs()
    {
        return $this->morphMany(ActivityLog::class, 'loggable')->latest('id');
    }
}
