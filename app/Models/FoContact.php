<?php

namespace App\Models;

use Database\Factories\FoContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FoContact extends Model
{
    /** @use HasFactory<FoContactFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
