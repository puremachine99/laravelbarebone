<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function regencies()
    {
        return $this->hasMany(Regency::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
