<?php

namespace App\Services\AiCenter;

use App\Enums\AiErrorCode;
use App\Enums\AiUsageKind;
use App\Enums\AiUsageOutcome;
use App\Models\AiUsageLog;
use App\Models\User;
use Throwable;

/**
 * Writes one row to the AI usage log (tokens, model, latency, outcome — never the message text). A failure here is
 * reported but never breaks the chat.
 */
class AiUsageRecorder
{
    public function record(
        User $user,
        AiUsageKind $kind,
        AiUsageOutcome $outcome,
        ?AiStreamEvent $done = null,
        ?AiErrorCode $errorCode = null,
        ?int $firstTokenMs = null,
        ?int $durationMs = null,
    ): ?AiUsageLog {
        $promptTokens = (int) ($done?->usage['prompt_tokens'] ?? 0);
        $outputTokens = (int) ($done?->usage['output_tokens'] ?? 0);

        try {
            return AiUsageLog::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'department' => $user->department?->name,
                'kind' => $kind,
                'outcome' => $outcome,
                'error_code' => $errorCode?->value,
                'model' => $done?->model,
                'finish_reason' => $done?->finishReason,
                'prompt_tokens' => $promptTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => (int) ($done?->usage['total_tokens'] ?? $promptTokens + $outputTokens),
                'first_token_ms' => $firstTokenMs,
                'duration_ms' => $durationMs,
                'cost_usd' => $this->cost($promptTokens, $outputTokens),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Milliseconds since an hrtime(true) reading.
     */
    public static function elapsedMs(int|float $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    /**
     * Estimated USD cost from config('ai_center.pricing'), or null when no price is set.
     */
    private function cost(int $promptTokens, int $outputTokens): ?float
    {
        $inputPrice = config('ai_center.pricing.input_per_1m');
        $outputPrice = config('ai_center.pricing.output_per_1m');

        if (! is_numeric($inputPrice) && ! is_numeric($outputPrice)) {
            return null;
        }

        return round(($promptTokens * (float) $inputPrice + $outputTokens * (float) $outputPrice) / 1_000_000, 6);
    }
}
