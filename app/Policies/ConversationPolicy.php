<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * A user may view a conversation only if they are one of its participants.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()->where('users.id', $user->id)->exists();
    }

    /**
     * Sending a message / marking a conversation read requires the same participant check.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
