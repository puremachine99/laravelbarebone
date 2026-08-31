<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return Conversation::query()
        ->whereKey($conversationId)
        ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
        ->exists();
});

Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Presence channel every authenticated user joins to broadcast "who's online" for the chat
 * feature's online/offline indicator. Membership itself is the payload — anyone logged in
 * may join, since knowing a colleague is online isn't sensitive.
 */
Broadcast::channel('online', function ($user) {
    return ['id' => $user->id, 'name' => $user->name, 'status' => $user->presence_status->value];
});
