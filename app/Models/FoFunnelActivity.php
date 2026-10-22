<?php

namespace App\Models;

use App\Services\FoTargetMetrics;
use Database\Factories\FoFunnelActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FoFunnelActivity extends Model
{
    /** @use HasFactory<FoFunnelActivityFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Any change invalidates the cached month-level FO dashboard data (heatmap, leaderboard).
     */
    protected static function booted(): void
    {
        static::saved(fn () => FoTargetMetrics::flushMonthlyCache());
        static::deleted(fn () => FoTargetMetrics::flushMonthlyCache());
        static::restored(fn () => FoTargetMetrics::flushMonthlyCache());
    }

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
