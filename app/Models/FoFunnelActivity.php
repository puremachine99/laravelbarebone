<?php

namespace App\Models;

use Database\Factories\FoFunnelActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FoFunnelActivity extends Model
{
    /** @use HasFactory<FoFunnelActivityFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function funnel()
    {
        return $this->belongsTo(FoFunnel::class, 'fo_funnel_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function logs()
    {
        return $this->hasMany(FoFunnelActivityLog::class)->latest('id');
    }
}
