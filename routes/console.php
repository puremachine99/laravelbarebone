<?php

use App\Models\AiUsageLog;
use App\Models\AiUsageSummary;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// AI usage log: weekly & monthly summaries (kept 1 year), raw log rows pruned after 3 months.
Schedule::command('ai-usage:summarize weekly')->weeklyOn(1, '00:30');
Schedule::command('ai-usage:summarize monthly')->monthlyOn(1, '00:45');
Schedule::command('model:prune', ['--model' => [AiUsageLog::class, AiUsageSummary::class]])->dailyAt('01:30');
