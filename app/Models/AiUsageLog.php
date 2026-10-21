<?php

namespace App\Models;

use App\Enums\AiUsageKind;
use App\Enums\AiUsageOutcome;
use Database\Factories\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI call: who, which model, token counts, latency and outcome. Never holds the message text.
 * Kept for {@see self::RETENTION_MONTHS} months; weekly/monthly totals live on in AiUsageSummary.
 */
class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use HasFactory, MassPrunable;

    public const RETENTION_MONTHS = 3;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AiUsageKind::class,
            'outcome' => AiUsageOutcome::class,
            'cost_usd' => 'decimal:6',
            'generated_file_types' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }
}
