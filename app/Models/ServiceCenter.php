<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCenter extends Model
{
    protected $fillable = ['division_id', 'code', 'full_code', 'name'];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function outputs()
    {
        return $this->hasMany(Output::class);
    }
}
