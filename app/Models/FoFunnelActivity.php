<?php

namespace App\Models;

use Database\Factories\FoFunnelActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoFunnelActivity extends Model
{
    /** @use HasFactory<FoFunnelActivityFactory> */
    use HasFactory;

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
}
