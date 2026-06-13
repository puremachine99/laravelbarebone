<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'pitching_date' => 'date',
        'archived_at' => 'datetime',
        'manager_verified' => 'boolean',
        'history_logs' => 'array',
        'follow_ups' => 'array',
    ];

    public function stage()
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function addHistoryLog(string $action, string $details): void
    {
        $logs = $this->history_logs ?? [];
        $logs[] = [
            'timestamp' => now()->toIso8601String(),
            'user' => auth()->user()?->name ?? 'System',
            'action' => $action,
            'details' => $details,
        ];
        $this->update(['history_logs' => $logs]);
    }

    public function scopeForPitching($query)
    {
        return $query->whereNull('archived_at')
            ->where(function ($q) {
                $q->whereNotIn('status', ['won', 'lost'])
                    ->orWhere('updated_at', '>=', now()->subDays(30));
            });
    }

    public function scopeForRealisasi($query)
    {
        return $query->whereHas('stage', function ($q) {
            $q->where('stage_name', 'Terlaksana');
        })->whereIn('status', ['won', 'hot']);
    }
}
