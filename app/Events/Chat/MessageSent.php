<?php

namespace App\Events\Chat;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("conversation.{$this->message->conversation_id}")];

        $recipientId = $this->message->conversation->otherParticipant($this->message->user)?->id;

        if ($recipientId) {
            $channels[] = new PrivateChannel("chat.{$recipientId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->user_id,
        ];
    }
}
