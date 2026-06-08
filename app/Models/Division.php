<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = ['code', 'name'];

    public function serviceCenters()
    {
        return $this->hasMany(ServiceCenter::class);
    }

    public function outputs()
    {
        return $this->hasMany(Output::class);
    }
}
