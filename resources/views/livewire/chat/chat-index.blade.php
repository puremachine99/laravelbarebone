<div
    class="flex h-[calc(100vh-5.5rem)] lg:h-[calc(100vh-7rem)] overflow-hidden rounded-2xl border border-base-300 bg-base-100">

    {{-- Contact list --}}
    <div
        class="w-full sm:w-72 lg:w-80 shrink-0 border-e border-base-300 flex flex-col min-h-0 {{ $selectedUserId ? 'hidden sm:flex' : 'flex' }}">
        <div class="p-4 border-b border-base-300 space-y-3 shrink-0">
            <h2 class="text-lg font-semibold text-base-content">{{ __('Chat') }}</h2>

            <label class="input input-sm input-bordered flex items-center gap-2 bg-base-200/60 w-full">
                <x-icon name="o-magnifying-glass" class="w-4 h-4 text-base-content/40 shrink-0" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Cari nama...') }}"
                    class="grow bg-transparent focus:outline-none text-sm" />
            </label>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-base-300">
            @forelse ($this->users as $user)
                @php
                    $lastMessage = isset($conversations[$user->id]) ? end($conversations[$user->id]) : null;
                    $unread = $unreadCounts[$user->id] ?? 0;
                @endphp
                <button type="button" wire:click="selectUser({{ $user->id }})"
                    class="w-full text-left flex items-center gap-3 px-4 py-3 hover:bg-base-200 transition-colors cursor-pointer {{ $selectedUserId === $user->id ? 'bg-base-200' : '' }}">
                    <x-user-avatar :user="$user" size="size-10" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span
                                    class="text-sm truncate {{ $unread > 0 ? 'font-bold text-base-content' : 'font-semibold text-base-content' }}">{{ $user->name }}</span>
                                <x-department-badge :user="$user" size="badge-xs" class="shrink-0" />
                            </div>
                            @if ($lastMessage)
                                <span
                                    class="text-[10px] shrink-0 {{ $unread > 0 ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-base-content/40' }}">{{ $this->lastMessageLabel($user->id) }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs truncate {{ $unread > 0 ? 'text-base-content/80 font-medium' : 'text-base-content/50' }}">
                                @if ($lastMessage)
                                    @if ($lastMessage['from'] === 'me')
                                        <span class="text-base-content/40">{{ __('Anda:') }}</span>
                                    @endif
                                    {{ $lastMessage['text'] ?? __('📎 Lampiran') }}
                                @else
                                    {{ $user->email }}
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
                <div class="px-4 py-8 text-center text-xs text-base-content/50">
                    {{ __('Tidak ada user ditemukan.') }}
                </div>
            @endforelse
        </div>
    </div>

    {{-- Conversation canvas --}}
    <div class="flex-1 flex flex-col min-w-0 min-h-0 {{ $selectedUserId ? 'flex' : 'hidden sm:flex' }}">
        @if ($this->selectedUser)
            <button type="button" wire:click="$toggle('showProfile')"
                class="flex items-center gap-3 px-4 py-3 border-b border-base-300 bg-base-100 shrink-0 text-left hover:bg-base-200/60 transition-colors cursor-pointer">
                <span wire:click.stop="$set('selectedUserId', null)"
                    class="btn btn-ghost btn-circle btn-sm sm:hidden">
                    <x-icon name="o-arrow-left" class="w-4 h-4" />
                </span>
                <x-user-avatar :user="$this->selectedUser" size="size-9" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="font-semibold text-sm text-base-content truncate">{{ $this->selectedUser->name }}</span>
                        <x-department-badge :user="$this->selectedUser" size="badge-xs" class="shrink-0" />
                    </div>
                    <div class="text-xs text-base-content/50 truncate">{{ __('Lihat profil') }}</div>
                </div>
                <x-icon name="o-information-circle" class="w-5 h-5 text-base-content/30 shrink-0" />
            </button>

            <div class="flex-1 min-h-0 overflow-y-auto px-4 py-4 space-y-3 bg-base-200/40"
                wire:key="thread-{{ $selectedUserId }}">
                @foreach ($conversations[$selectedUserId] ?? [] as $message)
                    <div class="flex {{ $message['from'] === 'me' ? 'justify-end' : 'justify-start' }}">
                        <div
                            class="max-w-[75%] sm:max-w-sm rounded-2xl px-3 py-2 text-sm leading-relaxed shadow-sm {{ $message['from'] === 'me'
                                ? 'bg-indigo-600 text-white rounded-br-sm'
                                : 'bg-base-100 text-base-content border border-base-300 rounded-bl-sm' }}">
                            @if (! empty($message['attachment']))
                                @if ($message['attachment']['is_image'])
                                    <img src="{{ $message['attachment']['url'] }}"
                                        alt="{{ $message['attachment']['name'] }}"
                                        class="rounded-lg mb-1 max-h-48 w-full object-cover">
                                @else
                                    <div
                                        class="flex items-center gap-2 mb-1 rounded-lg px-2 py-1.5 {{ $message['from'] === 'me' ? 'bg-white/15' : 'bg-base-200' }}">
                                        <x-icon name="o-document" class="w-4 h-4 shrink-0" />
                                        <span class="text-xs truncate">{{ $message['attachment']['name'] }}</span>
                                    </div>
                                @endif
                            @endif

                            @if (! empty($message['text']))
                                <p>{{ $message['text'] }}</p>
                            @endif

                            <span
                                class="block text-[10px] mt-1 text-right {{ $message['from'] === 'me' ? 'text-indigo-100/80' : 'text-base-content/40' }}">
                                {{ $message['time'] }}
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

                <form wire:submit="sendMessage" class="flex items-center gap-2 px-4 py-3">
                    <label
                        class="btn btn-ghost btn-circle btn-sm shrink-0 cursor-pointer"
                        wire:loading.class="opacity-50 pointer-events-none" wire:target="attachment">
                        <x-icon name="o-paper-clip" class="w-4 h-4" />
                        <input type="file" wire:model="attachment" class="hidden" />
                    </label>
                    <input type="text" wire:model="messageInput" placeholder="{{ __('Tulis pesan...') }}"
                        class="input input-bordered input-sm flex-1 bg-base-200/60" autocomplete="off" />
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
    @if ($showProfile && $this->selectedUser)
        <div class="w-72 shrink-0 border-s border-base-300 flex flex-col min-h-0 bg-base-100">
            <div class="flex items-center justify-between px-4 py-3 border-b border-base-300 shrink-0">
                <span class="font-semibold text-sm text-base-content">{{ __('Profil') }}</span>
                <button type="button" wire:click="$set('showProfile', false)" class="btn btn-ghost btn-circle btn-xs">
                    <x-icon name="o-x-mark" class="w-4 h-4" />
                </button>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto p-5 flex flex-col items-center text-center gap-4">
                <x-user-avatar :user="$this->selectedUser" size="size-20" />

                <div>
                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                        <span class="font-bold text-base text-base-content">{{ $this->selectedUser->name }}</span>
                        <x-department-badge :user="$this->selectedUser" size="badge-xs" />
                    </div>
                    @if ($roleNames = $this->selectedUser->getRoleNames())
                        <div class="flex flex-wrap justify-center gap-1 mt-1.5">
                            @foreach ($roleNames as $roleName)
                                <span class="badge badge-primary badge-sm">{{ $roleName }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="w-full space-y-3 text-left">
                    @if ($jabatan = $this->selectedUser->employee?->positionStruktural?->name ?? $this->selectedUser->employee?->positionFungsional?->name)
                        <div class="flex items-start gap-3">
                            <x-icon name="o-identification" class="w-4 h-4 text-base-content/40 mt-0.5 shrink-0" />
                            <div>
                                <div class="text-[11px] uppercase tracking-wide text-base-content/40">{{ __('Jabatan') }}</div>
                                <div class="text-sm text-base-content">{{ $jabatan }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
