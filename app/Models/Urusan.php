<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Urusan extends Model
{
    protected $fillable = ['code', 'urusan'];

    public function opds()
    {
        return $this->belongsToMany(Opd::class, 'opd_urusan', 'urusan_id', 'opd_id');
    }
}
