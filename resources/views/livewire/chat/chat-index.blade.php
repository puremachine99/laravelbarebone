<div
    class="flex h-[calc(100vh-5.5rem)] lg:h-[calc(100vh-7rem)] overflow-hidden rounded-2xl border border-base-300 bg-base-100"
    x-data="{
        activeConversationChannel: null,
        handledMessageIds: new Set(),
        handleIncoming(messageId, conversationId) {
            if (this.handledMessageIds.has(messageId)) {
                return;
            }
            this.handledMessageIds.add(messageId);
            $wire.handleBroadcastMessage(conversationId);
        },
        joinConversation(conversationId) {
            if (this.activeConversationChannel) {
                Echo.leave(this.activeConversationChannel);
                this.activeConversationChannel = null;
            }
            if (!conversationId) {
                return;
            }
            this.activeConversationChannel = 'conversation.' + conversationId;
            Echo.private(this.activeConversationChannel).listen('.MessageSent', (e) => this.handleIncoming(e.message_id, e.conversation_id));
        },
    }"
    x-init="
        Echo.private('chat.{{ auth()->id() }}').listen('.MessageSent', (e) => handleIncoming(e.message_id, e.conversation_id));
        joinConversation({{ $selectedConversationId ?? 'null' }});
    "
    x-on:conversation-selected.window="joinConversation($event.detail.conversationId)">

    {{-- Contact list --}}
    <div
        class="w-full sm:w-72 lg:w-80 shrink-0 border-e border-base-300 flex flex-col min-h-0 {{ $selectedConversationId ? 'hidden sm:flex' : 'flex' }}">
        <div class="p-4 border-b border-base-300 space-y-3 shrink-0">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-base-content">{{ __('Chat') }}</h2>
                <div class="flex items-center gap-2">
                    <div class="dropdown dropdown-end">
                        <div tabindex="0" role="button"
                            class="btn btn-ghost btn-sm gap-1.5 normal-case"
                            title="{{ __('Ubah status') }}">
                            <span class="w-2 h-2 rounded-full {{ auth()->user()->presence_status->dotColorClass() }}"></span>
                            <span class="text-xs text-base-content/70">{{ auth()->user()->presence_status->label() }}</span>
                        </div>
                        <ul tabindex="0"
                            class="dropdown-content menu menu-sm p-2 shadow-xl bg-base-100 border border-base-300 rounded-box w-44 z-50">
                            @foreach (\App\Enums\ChatPresenceStatus::cases() as $status)
                                <li>
                                    <button type="button" wire:click="updateMyStatus('{{ $status->value }}')"
                                        class="flex items-center gap-2 text-sm {{ auth()->user()->presence_status === $status ? 'font-semibold text-base-content' : 'text-base-content/70' }}">
                                        <span class="w-2 h-2 rounded-full {{ $status->dotColorClass() }}"></span>
                                        <span>{{ $status->label() }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <button type="button" wire:click="openNewChatModal" title="{{ __('Chat Baru') }}"
                        class="btn btn-primary btn-circle btn-sm">
                        <x-icon name="o-plus" class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <label class="input input-sm input-bordered flex items-center gap-2 bg-base-200/60 w-full">
                <x-icon name="o-magnifying-glass" class="w-4 h-4 text-base-content/40 shrink-0" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Cari nama...') }}"
                    class="grow bg-transparent focus:outline-none text-sm" />
            </label>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-base-300">
            @forelse ($this->conversations as $conversation)
                @php
                    $otherUser = $conversation->participants->first();
                    $lastMessage = $conversation->latestMessage;
                    $unread = $conversation->unread_count;
                @endphp
                @continue(! $otherUser)
                <button type="button" wire:click="selectConversation({{ $conversation->id }})"
                    class="js-chat-contact w-full text-left flex items-center gap-3 px-4 py-3 hover:bg-base-200 transition-colors cursor-pointer {{ $selectedConversationId === $conversation->id ? 'bg-base-200' : '' }}"
                    data-profile-url="{{ route('users.show', $otherUser->id) }}">
                    <x-user-avatar :user="$otherUser" size="size-10" department class="mb-1" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-1.5 min-w-0">
                                <x-chat.presence-dot :user="$otherUser" />
                                <span
                                    class="text-sm truncate {{ $unread > 0 ? 'font-bold text-base-content' : 'font-semibold text-base-content' }}">{{ $otherUser->name }}</span>
                            </span>
                            @if ($lastMessage)
                                <span
                                    class="text-[10px] shrink-0 {{ $unread > 0 ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-base-content/40' }}">{{ $this->lastMessageLabel($lastMessage->created_at) }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs truncate {{ $unread > 0 ? 'text-base-content/80 font-medium' : 'text-base-content/50' }}">
                                @if ($lastMessage)
                                    @if ($lastMessage->user_id === auth()->id())
                                        <span class="text-base-content/40">{{ __('Anda:') }}</span>
                                    @endif
                                    {{ $lastMessage->body ?? __('📎 Lampiran') }}
                                @else
                                    {{ $otherUser->email }}
                                @endif
                            </p>
                            @if ($unread > 0)
                                <span
                                    class="badge badge-sm bg-indigo-600 text-white border-0 shrink-0 min-w-5 px-1">{{ $unread > 9 ? '9+' : $unread }}</span>
                            @endif
                        </div>
                    </div>
                </button>
            @empty
                <div class="px-4 py-8 text-center text-xs text-base-content/50 space-y-2">
                    <p>{{ $search ? __('Tidak ada kontak yang cocok.') : __('Belum ada percakapan.') }}</p>
                    @unless ($search)
                        <button type="button" wire:click="openNewChatModal" class="btn btn-primary btn-xs">
                            {{ __('Mulai Chat Baru') }}
                        </button>
                    @endunless
                </div>
            @endforelse
        </div>
    </div>

    {{-- Conversation canvas --}}
    <div class="flex-1 flex flex-col min-w-0 min-h-0 {{ $selectedConversationId ? 'flex' : 'hidden sm:flex' }}">
        @if ($this->selectedConversation && $this->selectedOtherUser)
            <button type="button" wire:click="$toggle('showProfile')"
                class="flex items-center gap-3 px-4 py-3 border-b border-base-300 bg-base-100 shrink-0 text-left hover:bg-base-200/60 transition-colors cursor-pointer">
                <span wire:click.stop="closeConversation"
                    class="btn btn-ghost btn-circle btn-sm sm:hidden">
                    <x-icon name="o-arrow-left" class="w-4 h-4" />
                </span>
                <x-user-avatar :user="$this->selectedOtherUser" size="size-9" department class="mb-1" />
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-sm text-base-content truncate flex items-center gap-1.5">
                        <x-chat.presence-dot :user="$this->selectedOtherUser" />
                        {{ $this->selectedOtherUser->name }}
                    </div>
                    <div
                        x-data
                        class="text-xs truncate"
                        x-bind:class="$store.presence.statusOf({{ $this->selectedOtherUser->id }}) === 'online' ? 'text-green-600 dark:text-green-400 font-medium' : 'text-base-content/50'"
                        x-text="({
                            online: @js(__('Online')),
                            away: @js(__('Pergi Sebentar')),
                            busy: @js(__('Sibuk')),
                            offline: @js(__('Lihat profil')),
                        })[$store.presence.statusOf({{ $this->selectedOtherUser->id }})]"
                    ></div>
                </div>
                <x-icon name="o-information-circle" class="w-5 h-5 text-base-content/30 shrink-0" />
            </button>

            <div class="flex-1 min-h-0 overflow-y-auto px-4 py-4 space-y-3 bg-base-200/40"
                wire:key="thread-{{ $selectedConversationId }}"
                x-data="{ scrollToBottom() { $nextTick(() => { $el.scrollTop = $el.scrollHeight }) } }"
                x-init="scrollToBottom()"
                x-on:message-sent.window="scrollToBottom()">
                @php $lastDate = null; @endphp
                @foreach ($this->selectedConversation->messages as $message)
                    @php $dateKey = $message->created_at->toDateString(); @endphp
                    @if ($dateKey !== $lastDate)
                        @php $lastDate = $dateKey; @endphp
                        <div class="flex justify-center py-1">
                            <span class="badge badge-sm bg-base-300/70 text-base-content/60 border-0 font-medium px-3">
                                {{ $this->dateSeparatorLabel($message->created_at) }}
                            </span>
                        </div>
                    @endif
                    @php $isMine = $message->user_id === auth()->id(); @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div
                            class="max-w-[75%] sm:max-w-sm rounded-2xl px-3 py-2 text-sm leading-relaxed shadow-sm {{ $isMine
                                ? 'bg-indigo-600 text-white rounded-br-sm'
                                : 'bg-base-100 text-base-content border border-base-300 rounded-bl-sm' }}">
                            @if ($message->attachment_path)
                                @if ($message->isImage())
                                    <img src="{{ $message->previewUrl() }}"
                                        alt="{{ $message->attachment_name }}"
                                        class="js-chat-image rounded-lg mb-1 max-h-48 w-full object-cover cursor-pointer"
                                        data-original-url="{{ $message->attachmentUrl() }}"
                                        data-original-name="{{ $message->attachment_name }}">
                                @else
                                    <a href="{{ $message->attachmentUrl() }}" target="_blank"
                                        class="flex items-center gap-2 mb-1 rounded-lg px-2 py-1.5 {{ $isMine ? 'bg-white/15' : 'bg-base-200' }}">
                                        <x-icon name="o-document" class="w-4 h-4 shrink-0" />
                                        <span class="text-xs truncate">{{ $message->attachment_name }}</span>
                                    </a>
                                @endif
                            @endif

                            @if ($message->body)
                                <p>{{ $message->body }}</p>
                            @endif

                            <span
                                class="block text-[10px] mt-1 text-right {{ $isMine ? 'text-indigo-100/80' : 'text-base-content/40' }}">
                                {{ $message->created_at->format('H:i') }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-base-300 bg-base-100 shrink-0">
                @if ($attachment)
                    <div class="px-4 pt-3">
                        <div class="relative inline-block">
                            @if (str_starts_with((string) $attachment->getMimeType(), 'image/'))
                                <img src="{{ $attachment->temporaryUrl() }}"
                                    class="h-16 w-16 rounded-lg object-cover border border-base-300">
                            @else
                                <div
                                    class="h-16 w-16 rounded-lg border border-base-300 bg-base-200 flex flex-col items-center justify-center gap-1 px-1">
                                    <x-icon name="o-document" class="w-5 h-5 text-base-content/50" />
                                    <span
                                        class="text-[9px] text-base-content/50 truncate max-w-full">{{ $attachment->getClientOriginalName() }}</span>
                                </div>
                            @endif
                            <button type="button" wire:click="removeAttachment"
                                class="absolute -top-1.5 -right-1.5 btn btn-circle btn-error btn-xs text-white">
                                <x-icon name="o-x-mark" class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                @endif

                @error('messageInput')
                    <p class="px-4 pt-2 text-xs text-error">{{ $message }}</p>
                @enderror

                <form wire:submit="sendMessage" class="flex items-end gap-2 px-4 py-3"
                    x-data="{
                        resize() {
                            const el = $refs.messageInput;
                            el.style.height = 'auto';
                            el.style.height = Math.min(el.scrollHeight, 128) + 'px';
                        },
                    }"
                    x-on:message-sent.window="resize()">
                    <label
                        class="btn btn-ghost btn-circle btn-sm shrink-0 cursor-pointer"
                        wire:loading.class="opacity-50 pointer-events-none" wire:target="attachment">
                        <x-icon name="o-paper-clip" class="w-4 h-4" />
                        <input type="file" wire:model="attachment" class="hidden" />
                    </label>
                    <textarea
                        x-ref="messageInput"
                        wire:model="messageInput"
                        x-init="resize()"
                        x-on:input="resize()"
                        x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendMessage(); }"
                        rows="1"
                        placeholder="{{ __('Tulis pesan... (Shift+Enter untuk baris baru)') }}"
                        class="textarea textarea-bordered textarea-sm flex-1 bg-base-200/60 resize-none leading-snug py-2 max-h-32 overflow-y-auto"
                        autocomplete="off"
                    ></textarea>
                    <button type="submit" class="btn btn-primary btn-circle btn-sm shrink-0">
                        <x-icon name="o-paper-airplane" class="w-4 h-4" />
                    </button>
                </form>
            </div>
        @else
            <div
                class="flex-1 hidden sm:flex flex-col items-center justify-center text-center gap-2 text-base-content/40 p-8">
                <x-icon name="o-chat-bubble-left-right" class="w-12 h-12" />
                <p class="text-sm font-medium">{{ __('Pilih kontak untuk mulai chat') }}</p>
            </div>
        @endif
    </div>

    {{-- Profile sidebar --}}
    @if ($showProfile && $this->selectedOtherUser)
        <div class="w-72 shrink-0 border-s border-base-300 flex flex-col min-h-0 bg-base-100">
            <div class="flex items-center justify-between px-4 py-3 border-b border-base-300 shrink-0">
                <span class="font-semibold text-sm text-base-content">{{ __('Profil') }}</span>
                <button type="button" wire:click="$set('showProfile', false)" class="btn btn-ghost btn-circle btn-xs">
                    <x-icon name="o-x-mark" class="w-4 h-4" />
                </button>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto p-5 flex flex-col items-center text-center gap-4">
                <x-user-avatar :user="$this->selectedOtherUser" size="size-20" department class="mb-1.5" />

                <div>
                    <div class="font-bold text-base text-base-content flex items-center justify-center gap-1.5">
                        <x-chat.presence-dot :user="$this->selectedOtherUser" />
                        {{ $this->selectedOtherUser->name }}
                    </div>
                    @if ($roleNames = $this->selectedOtherUser->getRoleNames())
                        <div class="flex flex-wrap justify-center gap-1 mt-1.5">
                            @foreach ($roleNames as $roleName)
                                <span class="badge badge-primary badge-sm">{{ $roleName }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="w-full space-y-3 text-left">
                    @if ($jabatan = $this->selectedOtherUser->employee?->positionStruktural?->name ?? $this->selectedOtherUser->employee?->positionFungsional?->name)
                        <div class="flex items-start gap-3">
                            <x-icon name="o-identification" class="w-4 h-4 text-base-content/40 mt-0.5 shrink-0" />
                            <div>
                                <div class="text-[11px] uppercase tracking-wide text-base-content/40">{{ __('Jabatan') }}</div>
                                <div class="text-sm text-base-content">{{ $jabatan }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <a href="{{ route('users.show', $this->selectedOtherUser->id) }}" wire:navigate
                    class="btn btn-outline btn-sm w-full">
                    {{ __('Lihat Profil Lengkap') }}
                </a>
            </div>
        </div>
    @endif

    {{-- New chat picker --}}
    <x-modal wire:model="showNewChatModal" title="{{ __('Chat Baru') }}" subtitle="{{ __('Pilih kontak untuk memulai percakapan.') }}"
        without-trap-focus>
        <div class="space-y-3">
            <label class="input input-sm input-bordered flex items-center gap-2 bg-base-200/60 w-full">
                <x-icon name="o-magnifying-glass" class="w-4 h-4 text-base-content/40 shrink-0" />
                <input type="search" wire:model.live.debounce.300ms="newChatSearch" placeholder="{{ __('Cari nama...') }}"
                    class="grow bg-transparent focus:outline-none text-sm" />
            </label>

            <div class="max-h-80 overflow-y-auto divide-y divide-base-300 -mx-4">
                @forelse ($this->allUsers as $user)
                    <button type="button" wire:click="startNewChat({{ $user->id }})"
                        class="js-chat-contact w-full text-left flex items-center gap-3 px-4 py-2.5 hover:bg-base-200 transition-colors cursor-pointer"
                        data-profile-url="{{ route('users.show', $user->id) }}">
                        <x-user-avatar :user="$user" size="size-9" department class="mb-1" />
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-base-content truncate">{{ $user->name }}</div>
                            <div class="text-xs text-base-content/50 truncate">{{ $user->email }}</div>
                        </div>
                    </button>
                @empty
                    <div class="px-4 py-8 text-center text-xs text-base-content/50">
                        {{ __('Tidak ada user ditemukan.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </x-modal>
</div>

@script
<script>
(function () {
    let menu = null;

    const closeMenu = () => {
        menu?.remove();
        menu = null;
    };

    document.addEventListener('contextmenu', (event) => {
        const image = event.target.closest('.js-chat-image');
        const contact = event.target.closest('.js-chat-contact');

        if (!image && !contact) {
            closeMenu();

            return;
        }

        event.preventDefault();
        closeMenu();

        menu = document.createElement('div');
        menu.className = 'menu bg-base-100 rounded-box shadow-lg border border-base-300 p-1 fixed z-50 text-sm';
        menu.style.left = `${event.clientX}px`;
        menu.style.top = `${event.clientY}px`;

        if (image) {
            const download = document.createElement('button');
            download.type = 'button';
            download.className = 'btn btn-ghost btn-sm justify-start gap-2 whitespace-nowrap';
            download.textContent = @json(__('Unduh gambar asli'));
            download.addEventListener('click', () => {
                const link = document.createElement('a');
                link.href = image.dataset.originalUrl;
                link.download = image.dataset.originalName || '';
                document.body.appendChild(link);
                link.click();
                link.remove();
                closeMenu();
            });

            menu.appendChild(download);
        }

        if (contact) {
            const viewProfile = document.createElement('button');
            viewProfile.type = 'button';
            viewProfile.className = 'btn btn-ghost btn-sm justify-start gap-2 whitespace-nowrap';
            viewProfile.textContent = @json(__('Lihat Profil'));
            viewProfile.addEventListener('click', () => {
                closeMenu();
                Livewire.navigate(contact.dataset.profileUrl);
            });

            menu.appendChild(viewProfile);
        }

        document.body.appendChild(menu);
    });

    document.addEventListener('click', closeMenu);
    document.addEventListener('scroll', closeMenu, true);
})();
</script>
@endscript
