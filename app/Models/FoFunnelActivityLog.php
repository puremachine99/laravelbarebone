<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoFunnelActivityLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'old_tanggal' => 'date',
        'new_tanggal' => 'date',
    ];

    public function activity()
    {
        return $this->belongsTo(FoFunnelActivity::class, 'fo_funnel_activity_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
