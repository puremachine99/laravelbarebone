<?php

namespace App\Livewire\Chat;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class ChatIndex extends Component
{
    use WithFileUploads;

    public string $search = '';

    public ?int $selectedUserId = null;

    public bool $showProfile = false;

    public string $messageInput = '';

    public $attachment = null;

    /**
     * Dummy in-memory conversations keyed by the other user's id, seeded lazily on first open.
     *
     * @var array<int, array<int, array{from: string, text: ?string, time: string, attachment: ?array}>>
     */
    public array $conversations = [];

    /** @var array<int, int> unix timestamp of the last message per conversation, drives contact-list ordering */
    public array $lastMessageAt = [];

    /** @var array<int, int> unread message count per conversation, cleared when the thread is opened */
    public array $unreadCounts = [];

    public function mount(): void
    {
        $demoUsers = User::where('id', '!=', auth()->id())->orderBy('id')->limit(3)->get();

        // Seed a couple of contacts with recent, unread activity so the priority/badge
        // sort order is visible immediately, without requiring the user to send anything first.
        $demoSeeds = [
            ['minutesAgo' => 4, 'unread' => 2],
            ['minutesAgo' => 55, 'unread' => 1],
            ['minutesAgo' => 60 * 26, 'unread' => 0],
        ];

        foreach ($demoUsers as $index => $user) {
            $seed = $demoSeeds[$index] ?? null;

            if (! $seed) {
                continue;
            }

            $this->conversations[$user->id] = $this->seedDummyMessages($user, endsUnread: $seed['unread'] > 0);
            $this->lastMessageAt[$user->id] = now()->subMinutes($seed['minutesAgo'])->timestamp;
            $this->unreadCounts[$user->id] = $seed['unread'];
        }
    }

    #[Computed]
    public function users(): Collection
    {
        return User::query()
            ->where('id', '!=', auth()->id())
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get()
            ->sortByDesc(fn (User $user) => $this->lastMessageAt[$user->id] ?? -1)
            ->values();
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        return $this->selectedUserId
            ? User::with('employee.department', 'employee.positionStruktural', 'employee.positionFungsional')->find($this->selectedUserId)
            : null;
    }

    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->messageInput = '';
        $this->showProfile = false;
        $this->unreadCounts[$userId] = 0;
        $this->removeAttachment();

        if (! isset($this->conversations[$userId])) {
            $this->conversations[$userId] = $this->seedDummyMessages(User::findOrFail($userId));
            $this->lastMessageAt[$userId] = now()->timestamp;
        }
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

        if (! $this->selectedUserId) {
            return;
        }

        $text = trim($this->messageInput);

        if ($text === '' && ! $this->attachment) {
            $this->addError('messageInput', __('Tulis pesan atau lampirkan file.'));

            return;
        }

        $this->conversations[$this->selectedUserId][] = [
            'from' => 'me',
            'text' => $text !== '' ? $text : null,
            'time' => now()->format('H:i'),
            'attachment' => $this->attachment ? [
                'url' => $this->attachment->temporaryUrl(),
                'name' => $this->attachment->getClientOriginalName(),
                'is_image' => str_starts_with((string) $this->attachment->getMimeType(), 'image/'),
            ] : null,
        ];

        $this->lastMessageAt[$this->selectedUserId] = now()->timestamp;

        $this->messageInput = '';
        $this->attachment = null;
    }

    /**
     * Human label for a contact row's last-activity time, WhatsApp-style: time-of-day if today, otherwise a date.
     */
    public function lastMessageLabel(int $userId): ?string
    {
        if (! isset($this->lastMessageAt[$userId])) {
            return null;
        }

        $at = Carbon::createFromTimestamp($this->lastMessageAt[$userId]);

        return $at->isToday() ? $at->format('H:i') : $at->format('d/m/y');
    }

    /**
     * @return array<int, array{from: string, text: ?string, time: string, attachment: ?array}>
     */
    protected function seedDummyMessages(User $user, bool $endsUnread = false): array
    {
        $firstName = explode(' ', $user->name)[0];

        $messages = [
            ['from' => 'them', 'text' => "Halo, ini {$firstName}!", 'time' => '09:12', 'attachment' => null],
            ['from' => 'me', 'text' => 'Halo juga, ada yang bisa dibantu?', 'time' => '09:13', 'attachment' => null],
            ['from' => 'them', 'text' => 'Mau tanya progress laporan minggu ini sudah sampai mana ya.', 'time' => '09:14', 'attachment' => null],
        ];

        if ($endsUnread) {
            $messages[] = ['from' => 'them', 'text' => 'Halo, masih di sana? Ditunggu ya kabarnya 🙏', 'time' => '09:16', 'attachment' => null];
        } else {
            $messages[] = ['from' => 'me', 'text' => 'Lagi saya cek, nanti saya update di sini.', 'time' => '09:15', 'attachment' => null];
        }

        return $messages;
    }

    public function render()
    {
        return view('livewire.chat.chat-index');
    }
}
