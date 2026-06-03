<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Region extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    public function provinces()
    {
        return $this->hasMany(Province::class);
    }
}
