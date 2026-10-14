<?php

namespace App\Console\Commands;

use App\Enums\AiUsageOutcome;
use App\Models\AiUsageLog;
use App\Models\AiUsageSummary;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rolls the AI usage log up into one weekly (Monday–Sunday) or monthly summary row. By default it summarizes the last
 * finished week or month; re-running for the same period overwrites that row, so it is safe to repeat.
 */
class SummarizeAiUsage extends Command
{
    protected $signature = 'ai-usage:summarize
        {period : weekly or monthly}
        {--date= : Any date inside the period to summarize (default: the previous one)}';

    protected $description = 'Build the weekly or monthly AI usage summary from the usage log';

    public function handle(): int
    {
        $period = $this->argument('period');

        if (! in_array($period, [AiUsageSummary::WEEKLY, AiUsageSummary::MONTHLY], true)) {
            $this->error('Period must be "weekly" or "monthly".');

            return self::FAILURE;
        }

        [$start, $end] = $this->bounds($period, $this->option('date'));

        $summary = AiUsageSummary::updateOrCreate(
            ['period' => $period, 'period_start' => $start],
            ['period_end' => $end->startOfDay(), ...$this->totals($start, $end)],
        );

        $this->info("AI usage {$period} summary {$start->toDateString()} – {$end->toDateString()}: {$summary->requests} requests, ".number_format($summary->total_tokens).' tokens.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function bounds(string $period, ?string $date): array
    {
        $day = $date ? CarbonImmutable::parse($date) : CarbonImmutable::now();

        if ($period === AiUsageSummary::WEEKLY) {
            $day = $date ? $day : $day->subWeek();

            return [$day->startOfWeek(CarbonImmutable::MONDAY), $day->endOfWeek(CarbonImmutable::SUNDAY)];
        }

        $day = $date ? $day : $day->subMonthNoOverflow();

        return [$day->startOfMonth(), $day->endOfMonth()];
    }

    /**
     * @return array<string, mixed>
     */
    private function totals(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $logs = fn (): Builder => AiUsageLog::query()->whereBetween('created_at', [$start, $end]);

        $totals = $logs()->selectRaw('
            COUNT(*) AS requests,
            COUNT(DISTINCT user_id) AS active_users,
            COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
            COALESCE(SUM(output_tokens), 0) AS output_tokens,
            COALESCE(SUM(total_tokens), 0) AS total_tokens,
            SUM(cost_usd) AS cost_usd,
            AVG(first_token_ms) AS avg_first_token_ms,
            AVG(duration_ms) AS avg_duration_ms
        ')->toBase()->first();

        $outcomes = $logs()->selectRaw('outcome, COUNT(*) AS total')->groupBy('outcome')->toBase()->pluck('total', 'outcome');

        return [
            'requests' => (int) $totals->requests,
            'ok_count' => (int) ($outcomes[AiUsageOutcome::Ok->value] ?? 0),
            'error_count' => (int) ($outcomes[AiUsageOutcome::Error->value] ?? 0),
            'denied_count' => (int) ($outcomes[AiUsageOutcome::Denied->value] ?? 0),
            'cancelled_count' => (int) ($outcomes[AiUsageOutcome::Cancelled->value] ?? 0),
            'active_users' => (int) $totals->active_users,
            'prompt_tokens' => (int) $totals->prompt_tokens,
            'output_tokens' => (int) $totals->output_tokens,
            'total_tokens' => (int) $totals->total_tokens,
            'cost_usd' => $totals->cost_usd,
            'avg_first_token_ms' => $totals->avg_first_token_ms === null ? null : (int) round($totals->avg_first_token_ms),
            'avg_duration_ms' => $totals->avg_duration_ms === null ? null : (int) round($totals->avg_duration_ms),
            'breakdown' => [
                'by_department' => $this->groupedBy($logs(), "COALESCE(department, '-')"),
                'by_model' => $this->groupedBy($logs(), "COALESCE(model, '-')"),
                'by_kind' => $this->groupedBy($logs(), 'kind'),
                'top_users' => $this->groupedBy($logs(), "COALESCE(user_name, '-')", limit: 10),
            ],
        ];
    }

    /**
     * Requests, tokens and cost per value of one column, biggest token users first.
     *
     * @return array<int, array{name: string, requests: int, total_tokens: int, cost_usd: float|null}>
     */
    private function groupedBy(Builder $query, string $expression, ?int $limit = null): array
    {
        return $query
            ->selectRaw("{$expression} AS name, COUNT(*) AS requests, COALESCE(SUM(total_tokens), 0) AS total_tokens, SUM(cost_usd) AS cost_usd")
            ->groupByRaw($expression)
            ->orderByDesc('total_tokens')
            ->when($limit, fn (Builder $query) => $query->limit($limit))
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'requests' => (int) $row->requests,
                'total_tokens' => (int) $row->total_tokens,
                'cost_usd' => $row->cost_usd === null ? null : round((float) $row->cost_usd, 6),
            ])
            ->all();
    }
}
