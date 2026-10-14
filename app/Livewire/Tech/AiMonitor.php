<?php

namespace App\Livewire\Tech;

use App\Enums\AiUsageKind;
use App\Enums\AiUsageOutcome;
use App\Models\AiUsageLog;
use App\Models\AiUsageSummary;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * AI Center monitoring for the Technology team: a terminal-style feed of the AI usage log plus the weekly/monthly
 * summaries. Shows token counts, latency and outcomes only — message text is never selected.
 */
#[Title('AI Monitor')]
class AiMonitor extends Component
{
    /**
     * How many log lines the terminal shows at most.
     */
    public const LINE_LIMIT = 300;

    /**
     * Columns the page may read from the log; deliberately an allow-list.
     *
     * @var array<int, string>
     */
    private const LOG_COLUMNS = [
        'id', 'created_at', 'user_name', 'department', 'kind', 'outcome', 'error_code', 'model', 'finish_reason',
        'prompt_tokens', 'output_tokens', 'total_tokens', 'first_token_ms', 'duration_ms', 'cost_usd',
    ];

    #[Url]
    public string $tab = 'log';

    #[Url]
    public string $outcome = '';

    #[Url]
    public string $kind = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $date = '';

    #[Url]
    public string $period = AiUsageSummary::WEEKLY;

    /**
     * Refresh the terminal every few seconds.
     */
    public bool $live = true;

    /**
     * Matching log lines, oldest first (newest at the bottom, like a terminal).
     *
     * @return Collection<int, AiUsageLog>
     */
    #[Computed]
    public function lines(): Collection
    {
        return AiUsageLog::query()
            ->select(self::LOG_COLUMNS)
            ->when(AiUsageOutcome::tryFrom($this->outcome), fn ($query, AiUsageOutcome $outcome) => $query->where('outcome', $outcome))
            ->when(AiUsageKind::tryFrom($this->kind), fn ($query, AiUsageKind $kind) => $query->where('kind', $kind))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('user_name', '%'.trim($this->search).'%')
                ->orWhereLike('department', '%'.trim($this->search).'%')))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1, fn ($query) => $query->whereDate('created_at', $this->date))
            ->latest('id')
            ->limit(self::LINE_LIMIT)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Today's and this month's totals for the cards above the terminal.
     *
     * @return array{today_requests: int, today_tokens: int, today_errors: int, today_users: int, month_tokens: int, month_cost: float|null, budget: float|null}
     */
    #[Computed]
    public function stats(): array
    {
        $today = AiUsageLog::query()->where('created_at', '>=', today())
            ->selectRaw('COUNT(*) AS requests, COALESCE(SUM(total_tokens), 0) AS tokens, COUNT(DISTINCT user_id) AS users')
            ->selectRaw('SUM(CASE WHEN outcome = ? THEN 1 ELSE 0 END) AS errors', [AiUsageOutcome::Error->value])
            ->toBase()->first();

        $month = AiUsageLog::query()->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(total_tokens), 0) AS tokens, SUM(cost_usd) AS cost')
            ->toBase()->first();

        $budget = config('ai_center.monthly_budget_usd');

        return [
            'today_requests' => (int) $today->requests,
            'today_tokens' => (int) $today->tokens,
            'today_errors' => (int) $today->errors,
            'today_users' => (int) $today->users,
            'month_tokens' => (int) $month->tokens,
            'month_cost' => $month->cost === null ? null : (float) $month->cost,
            'budget' => is_numeric($budget) ? (float) $budget : null,
        ];
    }

    /**
     * @return Collection<int, AiUsageSummary>
     */
    #[Computed]
    public function summaries(): Collection
    {
        return AiUsageSummary::query()
            ->where('period', $this->period === AiUsageSummary::MONTHLY ? AiUsageSummary::MONTHLY : AiUsageSummary::WEEKLY)
            ->latest('period_start')
            ->get();
    }

    public function resetFilters(): void
    {
        $this->reset('outcome', 'kind', 'search', 'date');
    }

    public function render()
    {
        return view('livewire.tech.ai-monitor');
    }
}
