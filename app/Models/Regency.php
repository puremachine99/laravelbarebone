<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Regency extends Model
{
    protected $guarded = ['id'];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function opds()
    {
        return $this->hasMany(Opd::class);
    }
}
