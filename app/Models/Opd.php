<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opd extends Model
{
    use HasFactory;

    protected $table = 'pmo_opds';

    protected $guarded = ['id'];

    public function regency()
    {
        return $this->belongsTo(Regency::class);
    }

    public function urusans()
    {
        return $this->belongsToMany(Urusan::class, 'pmo_opd_urusan', 'opd_id', 'urusan_id');
    }
}
