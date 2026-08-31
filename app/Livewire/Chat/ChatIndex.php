<?php

namespace App\Livewire\Chat;

use App\Enums\ChatPresenceStatus;
use App\Events\Chat\MessageSent;
use App\Events\Chat\UserStatusChanged;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ImagePreviewGenerator;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class ChatIndex extends Component
{
    use WithFileUploads;

    public string $search = '';

    public bool $showNewChatModal = false;

    public string $newChatSearch = '';

    public ?int $selectedConversationId = null;

    public bool $showProfile = false;

    public string $messageInput = '';

    public $attachment = null;

    /**
     * My conversations, newest activity first, annotated with `unread_count` (an in-memory
     * attribute, not a column) and the eager-loaded `latestMessage`/`participants` (the other
     * participant only, since `participants` is constrained to exclude me).
     */
    #[Computed]
    public function conversations(): Collection
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('users.id', auth()->id()))
            ->with([
                'participants' => fn ($query) => $query
                    ->where('users.id', '!=', auth()->id())
                    ->with('employee.department', 'employee.positionStruktural', 'employee.positionFungsional'),
                'latestMessage',
            ])
            ->when($this->search, fn ($query) => $query->whereHas(
                'participants',
                fn ($q) => $q->where('users.id', '!=', auth()->id())->where('name', 'ilike', "%{$this->search}%")
            ))
            ->orderByDesc('last_message_at')
            ->get();

        if ($conversations->isEmpty()) {
            return $conversations;
        }

        $conversationIds = $conversations->pluck('id');

        $myReadAt = DB::table('conversation_participants')
            ->where('user_id', auth()->id())
            ->whereIn('conversation_id', $conversationIds)
            ->pluck('last_read_at', 'conversation_id');

        $unreadCounts = Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('user_id', '!=', auth()->id())
            ->get(['conversation_id', 'created_at'])
            ->groupBy('conversation_id')
            ->map(function (Collection $messages, $conversationId) use ($myReadAt) {
                $readAt = $myReadAt[$conversationId] ?? null;

                return $readAt
                    ? $messages->where('created_at', '>', Carbon::parse($readAt))->count()
                    : $messages->count();
            });

        return $conversations->each(function (Conversation $conversation) use ($unreadCounts) {
            $conversation->unread_count = $unreadCounts[$conversation->id] ?? 0;
        });
    }

    /**
     * Full directory used by the new-chat picker modal.
     */
    #[Computed]
    public function allUsers(): Collection
    {
        return User::query()
            ->with('employee.department', 'employee.positionStruktural', 'employee.positionFungsional')
            ->where('id', '!=', auth()->id())
            ->when($this->newChatSearch, fn ($query) => $query->where('name', 'ilike', "%{$this->newChatSearch}%"))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedConversation(): ?Conversation
    {
        return $this->selectedConversationId
            ? Conversation::with([
                'messages' => fn ($query) => $query->oldest(),
                'participants' => fn ($query) => $query
                    ->where('users.id', '!=', auth()->id())
                    ->with('employee.department', 'employee.positionStruktural', 'employee.positionFungsional'),
            ])->find($this->selectedConversationId)
            : null;
    }

    #[Computed]
    public function selectedOtherUser(): ?User
    {
        return $this->selectedConversation?->participants->first();
    }

    public function updateMyStatus(string $status): void
    {
        $user = auth()->user();
        $user->update(['presence_status' => ChatPresenceStatus::from($status)]);

        UserStatusChanged::dispatch($user);
    }

    public function openNewChatModal(): void
    {
        $this->newChatSearch = '';
        $this->showNewChatModal = true;
    }

    public function startNewChat(int $userId): void
    {
        $otherUser = User::findOrFail($userId);
        $conversation = Conversation::findOrCreateDirect(auth()->user(), $otherUser);

        $this->showNewChatModal = false;
        $this->newChatSearch = '';

        $this->selectConversation($conversation->id);
    }

    public function selectConversation(int $conversationId): void
    {
        $conversation = Conversation::findOrFail($conversationId);
        $this->authorize('view', $conversation);

        $this->selectedConversationId = $conversationId;
        $this->messageInput = '';
        $this->showProfile = false;
        $this->removeAttachment();

        $conversation->participants()->updateExistingPivot(auth()->id(), ['last_read_at' => now()]);

        unset($this->conversations);

        $this->dispatch('message-sent');
        $this->dispatch('conversation-selected', conversationId: $conversationId);
    }

    public function closeConversation(): void
    {
        $this->selectedConversationId = null;
        $this->dispatch('conversation-selected', conversationId: null);
    }

    public function handleBroadcastMessage(int $conversationId): void
    {
        unset($this->conversations);

        if ($this->selectedConversationId !== $conversationId) {
            return;
        }

        $conversation = $this->selectedConversation;
        $conversation?->participants()->updateExistingPivot(auth()->id(), ['last_read_at' => now()]);

        unset($this->selectedConversation);

        $this->dispatch('message-sent');
    }

    public function removeAttachment(): void
    {
        $this->attachment = null;
        $this->resetErrorBag('attachment');
    }

    public function sendMessage(): void
    {
        $this->validate([
            'messageInput' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $conversation = $this->selectedConversation;

        if (! $conversation) {
            return;
        }

        $this->authorize('update', $conversation);

        $text = trim($this->messageInput);

        if ($text === '' && ! $this->attachment) {
            $this->addError('messageInput', __('Tulis pesan atau lampirkan file.'));

            return;
        }

        $attachmentPath = $this->attachment?->store('chat-attachments', 's3');
        $isImage = $this->attachment && str_starts_with((string) $this->attachment->getMimeType(), 'image/');
        $previewPath = $isImage ? app(ImagePreviewGenerator::class)->generate($this->attachment) : null;

        $message = $conversation->messages()->create([
            'user_id' => auth()->id(),
            'body' => $text !== '' ? $text : null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $this->attachment?->getClientOriginalName(),
            'attachment_mime' => $this->attachment?->getMimeType(),
            'attachment_preview_path' => $previewPath,
        ]);

        MessageSent::dispatch($message);

        $conversation->update(['last_message_at' => now()]);
        $conversation->participants()->updateExistingPivot(auth()->id(), ['last_read_at' => now()]);

        $this->messageInput = '';
        $this->attachment = null;

        unset($this->conversations, $this->selectedConversation);

        $this->dispatch('message-sent');
    }

    /**
     * Human label for a contact row's last-activity time, WhatsApp-style: time-of-day if today, otherwise a date.
     */
    public function lastMessageLabel(?CarbonInterface $at): ?string
    {
        if (! $at) {
            return null;
        }

        return $at->isToday() ? $at->format('H:i') : $at->format('d/m/y');
    }

    /**
     * Human label for a date separator between messages, WhatsApp-style.
     */
    public function dateSeparatorLabel(CarbonInterface $at): string
    {
        if ($at->isToday()) {
            return __('Hari Ini');
        }

        if ($at->isYesterday()) {
            return __('Kemarin');
        }

        return $at->translatedFormat('l, d F Y');
    }

    public function render()
    {
        return view('livewire.chat.chat-index');
    }
}
