<?php

namespace App\Services\AiCenter;

use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;

/**
 * How many messages a user has sent to the AI today (midnight WIB reset). Deleted conversations still count,
 * otherwise deleting a chat would hand the quota back.
 */
class DailyQuota
{
    public function limit(): int
    {
        return config('ai_center.limits.daily_messages');
    }

    public function used(User $user): int
    {
        return AiMessage::query()
            ->where('role', AiMessageRole::User)
            ->where('created_at', '>=', now(config('ai_center.timezone'))->startOfDay())
            ->whereIn('ai_conversation_id', AiConversation::withTrashed()->where('user_id', $user->id)->select('id'))
            ->count();
    }

    public function exceeded(User $user): bool
    {
        return $this->used($user) >= $this->limit();
    }
}
