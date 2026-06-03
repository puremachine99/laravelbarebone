<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Department extends Model
{
    use HasUuids;

    protected $guarded = ['id'];
    
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
