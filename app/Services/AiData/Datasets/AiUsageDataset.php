<?php

namespace App\Services\AiData\Datasets;

use App\Enums\AiMessageRole;
use App\Services\AiData\Dataset;
use App\Services\AiData\SqlMonth;
use App\Services\AiData\UserDepartment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Questions sent to the AI Center, by month and/or department. Counts only: message content is never read.
 * Deleted conversations still count, like the daily quota does.
 */
class AiUsageDataset implements Dataset
{
    public function rows(CarbonImmutable $from, CarbonImmutable $to, array $groupBy, ?array $departmentIds, array $filters = []): Collection
    {
        $expressions = [
            'month' => SqlMonth::of('ai_messages.created_at'),
            'department' => "COALESCE(departments.name, 'Tanpa departemen')",
        ];

        $query = UserDepartment::join(DB::table('ai_messages')
            ->join('ai_conversations', 'ai_conversations.id', '=', 'ai_messages.ai_conversation_id')
            ->join('users', 'users.id', '=', 'ai_conversations.user_id'))
            ->where('ai_messages.role', AiMessageRole::User->value)
            ->whereBetween('ai_messages.created_at', [$from, $to])
            ->when($departmentIds !== null, fn ($query) => $query->whereIn(DB::raw(UserDepartment::ID), $departmentIds))
            ->selectRaw('COUNT(*) AS questions, COUNT(DISTINCT ai_conversations.user_id) AS active_users');

        foreach ($groupBy as $dimension) {
            $query->selectRaw("{$expressions[$dimension]} AS {$dimension}")->groupByRaw($expressions[$dimension]);
        }

        return $query->get()->map(fn (object $row): array => [
            ...collect($groupBy)->mapWithKeys(fn (string $dimension) => [$dimension => (string) $row->{$dimension}])->all(),
            'questions' => (int) $row->questions,
            'active_users' => (int) $row->active_users,
            'group_size' => (int) $row->active_users,
        ]);
    }

    public function availableFrom(): ?CarbonImmutable
    {
        $first = DB::table('ai_messages')->where('role', AiMessageRole::User->value)->min('created_at');

        return $first ? CarbonImmutable::parse($first)->startOfMonth() : null;
    }
}
