<?php

namespace App\Events\Chat;

use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('online')];
    }

    public function broadcastAs(): string
    {
        return 'UserStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->user->id,
            'status' => $this->user->presence_status->value,
        ];
    }
}
