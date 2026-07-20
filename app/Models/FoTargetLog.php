<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoTargetLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'old_value' => 'decimal:2',
        'new_value' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
