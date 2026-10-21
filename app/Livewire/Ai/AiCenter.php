<?php

namespace App\Livewire\Ai;

use App\Models\AiConversation;
use App\Services\AiCenter\AiGateway;
use App\Services\AiCenter\DailyQuota;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Nakama AI Center chat page. The list/rename/delete/feedback actions live here; sending a message and streaming
 * the answer go through StreamMessageController (driven by the `aiCenterChat` Alpine component in app.js).
 */
class AiCenter extends Component
{
    public const TAB_CHAT = 'chat';

    public const TAB_MENTORING = 'pendampingan';

    /**
     * Top-right tabs: the free chat (default) or the guided "Pendampingan" desk.
     */
    #[Url(except: self::TAB_CHAT)]
    public string $tab = self::TAB_CHAT;

    public ?int $selectedConversationId = null;

    /**
     * Mobile only: show the chat pane for a brand-new conversation that has no id yet.
     */
    public bool $isComposingNew = false;

    public string $search = '';

    public bool $showRenameModal = false;

    public string $renameTitle = '';

    public bool $showDeleteModal = false;

    /**
     * My conversations, most recent activity first, with the latest message for the preview line.
     */
    #[Computed]
    public function conversations(): Collection
    {
        return auth()->user()->aiConversations()
            ->with('latestMessage')
            ->when($this->search, fn ($query) => $query->whereLike('title', "%{$this->search}%"))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function selectedConversation(): ?AiConversation
    {
        return $this->selectedConversationId
            ? auth()->user()->aiConversations()
                ->with(['messages' => fn ($query) => $query->oldest('id')->with('files')])
                ->find($this->selectedConversationId)
            : null;
    }

    #[Computed]
    public function quotaUsed(): int
    {
        return app(DailyQuota::class)->used(auth()->user());
    }

    #[Computed]
    public function quotaLimit(): int
    {
        return app(DailyQuota::class)->limit();
    }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab === self::TAB_MENTORING ? self::TAB_MENTORING : self::TAB_CHAT;
    }

    public function newConversation(): void
    {
        $this->selectedConversationId = null;
        $this->isComposingNew = true;
    }

    public function selectConversation(int $conversationId): void
    {
        $this->selectedConversationId = auth()->user()->aiConversations()->findOrFail($conversationId)->id;
        $this->isComposingNew = false;

        unset($this->selectedConversation);
    }

    public function closeConversation(): void
    {
        $this->selectedConversationId = null;
        $this->isComposingNew = false;
    }

    /**
     * Called by the browser once a streamed answer has finished, so the thread re-renders from the database
     * (with the reply's final Markdown) and a freshly created conversation becomes the selected one.
     */
    public function streamFinished(int $conversationId): void
    {
        $this->selectConversation($conversationId);

        unset($this->conversations, $this->quotaUsed);
    }

    public function openRenameModal(): void
    {
        $this->renameTitle = (string) $this->selectedConversation?->title;
        $this->resetErrorBag('renameTitle');
        $this->showRenameModal = true;
    }

    public function renameConversation(): void
    {
        $this->validate(['renameTitle' => ['required', 'string', 'max:150']]);

        $this->ownedSelectedConversation()->update(['title' => trim($this->renameTitle)]);

        $this->showRenameModal = false;

        unset($this->conversations, $this->selectedConversation);
    }

    public function deleteConversation(): void
    {
        $conversation = $this->ownedSelectedConversation();

        if ($conversation->remote_conversation_id) {
            app(AiGateway::class)->forget($conversation->remote_conversation_id);
        }

        $conversation->delete();

        $this->showDeleteModal = false;
        $this->closeConversation();

        unset($this->conversations);
    }

    /**
     * 👍 = 1, 👎 = -1; clicking the same thumb again clears it.
     */
    public function rateMessage(int $messageId, int $value): void
    {
        if (! in_array($value, [1, -1], true)) {
            return;
        }

        $message = $this->ownedSelectedConversation()->messages()->findOrFail($messageId);

        if (! $message->isFromAssistant()) {
            return;
        }

        $message->update(['feedback' => $message->feedback === $value ? null : $value]);

        unset($this->selectedConversation);
    }

    /**
     * Sidebar time label: time of day if today, otherwise a short date (same as Nakama Chat).
     */
    public function lastMessageLabel(?CarbonInterface $at): ?string
    {
        if (! $at) {
            return null;
        }

        return $at->isToday() ? $at->format('H:i') : $at->format('d/m/y');
    }

    public function render()
    {
        return view('livewire.ai.ai-center', [
            'quickActions' => config('ai_center.quick_actions'),
            'maxInputChars' => config('ai_center.limits.max_input_chars'),
        ]);
    }

    private function ownedSelectedConversation(): AiConversation
    {
        return auth()->user()->aiConversations()->findOrFail($this->selectedConversationId);
    }
}
