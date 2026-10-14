<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Weekly or monthly totals of the AI usage log, built by `ai-usage:summarize`. Outlives the raw log: kept for
 * {@see self::RETENTION_MONTHS} months.
 */
class AiUsageSummary extends Model
{
    use MassPrunable;

    public const RETENTION_MONTHS = 12;

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'cost_usd' => 'decimal:6',
            'breakdown' => 'array',
        ];
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('period_end', '<', now()->subMonths(self::RETENTION_MONTHS)->toDateString());
    }
}
