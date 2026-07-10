<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Urusan extends Model
{
    protected $table = 'pmo_urusans';

    protected $fillable = ['code', 'urusan'];

    public function opds()
    {
        return $this->belongsToMany(Opd::class, 'pmo_opd_urusan', 'urusan_id', 'opd_id');
    }
}
