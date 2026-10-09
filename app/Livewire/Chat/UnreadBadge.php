<?php

namespace App\Livewire\Chat;

use App\Models\Message;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class UnreadBadge extends Component
{
    /**
     * Total messages from other people that I have not read yet, across all my conversations.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return Message::query()
            ->join('conversation_participants as cp', function ($join) {
                $join->on('cp.conversation_id', '=', 'messages.conversation_id')
                    ->where('cp.user_id', auth()->id());
            })
            ->where('messages.user_id', '!=', auth()->id())
            ->where(fn ($query) => $query
                ->whereNull('cp.last_read_at')
                ->orWhereColumn('messages.created_at', '>', 'cp.last_read_at'))
            ->count();
    }

    #[On('chat-read')]
    public function refreshUnread(): void
    {
        unset($this->unreadCount);
    }

    public function render(): View
    {
        return view('livewire.chat.unread-badge');
    }
}
