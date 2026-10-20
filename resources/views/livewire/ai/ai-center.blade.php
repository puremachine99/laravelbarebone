@php
    $conversation = $this->selectedConversation;
    $isChatOpen = $selectedConversationId || $isComposingNew;
    $quotaPercent = $this->quotaLimit > 0 ? min(100, round($this->quotaUsed / $this->quotaLimit * 100)) : 100;
@endphp

<div
    class="flex h-[calc(100vh-5.5rem)] lg:h-[calc(100vh-7rem)] overflow-hidden rounded-2xl border border-base-300 bg-base-100"
    x-data="aiCenterChat({
        streamUrl: @js(route('ai-center.messages.stream')),
        maxChars: @js($maxInputChars),
        uploadUrl: @js(route('ai-center.files.store')),
        deleteUrl: @js(route('ai-center.files.destroy', '__ID__')),
        maxFiles: @js(config('ai_center.files.max_per_message')),
        maxFileMb: @js(config('ai_center.files.max_mb')),
    })">

    {{-- Conversation list --}}
    <div
        class="w-full sm:w-72 lg:w-80 shrink-0 border-e border-base-300 flex flex-col min-h-0 {{ $isChatOpen ? 'hidden sm:flex' : 'flex' }}">
        <div class="p-4 border-b border-base-300 space-y-3 shrink-0">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-base-content flex items-center gap-2">
                    <x-icon name="o-sparkles" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    {{ __('AI Center') }}
                </h2>
                <button type="button" wire:click="newConversation" title="{{ __('Percakapan Baru') }}"
                    class="btn btn-primary btn-circle btn-sm">
                    <x-icon name="o-plus" class="w-4 h-4" />
                </button>
            </div>

            <label class="input input-sm input-bordered flex items-center gap-2 bg-base-200/60 w-full">
                <x-icon name="o-magnifying-glass" class="w-4 h-4 text-base-content/40 shrink-0" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Cari percakapan...') }}"
                    class="grow bg-transparent focus:outline-none text-sm" />
            </label>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-base-300">
            @forelse ($this->conversations as $item)
                <button type="button" wire:key="ai-conversation-{{ $item->id }}"
                    wire:click="selectConversation({{ $item->id }})"
                    class="w-full text-left flex items-center gap-3 px-4 py-3 hover:bg-base-200 transition-colors cursor-pointer {{ $selectedConversationId === $item->id ? 'bg-base-200' : '' }}">
                    <span
                        class="size-9 rounded-full bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center shrink-0">
                        <x-icon name="o-chat-bubble-left-ellipsis" class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-base-content truncate">
                                {{ $item->title ?? __('Percakapan baru') }}
                            </span>
                            <span class="text-[10px] shrink-0 text-base-content/40">
                                {{ $this->lastMessageLabel($item->last_message_at) }}
                            </span>
                        </div>
                        <p class="text-xs truncate text-base-content/50">
                            @if ($item->latestMessage?->isFromAssistant())
                                <x-icon name="o-sparkles" class="w-3 h-3 inline -mt-0.5 text-base-content/40" />
                            @endif
                            {{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($item->latestMessage?->content ?? ''), 80) ?: '—' }}
                        </p>
                    </div>
                </button>
            @empty
                <div class="px-4 py-8 text-center text-xs text-base-content/50 space-y-2">
                    <p>{{ $search ? __('Tidak ada percakapan yang cocok.') : __('Belum ada percakapan.') }}</p>
                    @unless ($search)
                        <button type="button" wire:click="newConversation" class="btn btn-primary btn-xs">
                            {{ __('Mulai Percakapan') }}
                        </button>
                    @endunless
                </div>
            @endforelse
        </div>

        <div class="px-4 py-3 border-t border-base-300 shrink-0 space-y-1.5">
            <div class="flex items-center justify-between text-xs">
                <span class="text-base-content/50">{{ __('Kuota hari ini') }}</span>
                <span @class([
                    'font-semibold',
                    'text-error' => $this->quotaUsed >= $this->quotaLimit,
                    'text-base-content/70' => $this->quotaUsed < $this->quotaLimit,
                ])>
                    {{ __(':used/:limit pesan', ['used' => $this->quotaUsed, 'limit' => $this->quotaLimit]) }}
                </span>
            </div>
            <progress @class([
                'progress w-full h-1.5',
                'progress-error' => $quotaPercent >= 100,
                'progress-primary' => $quotaPercent < 100,
            ]) value="{{ $quotaPercent }}" max="100"></progress>
        </div>
    </div>

    {{-- Chat pane --}}
    <div class="flex-1 flex flex-col min-w-0 min-h-0 {{ $isChatOpen ? 'flex' : 'hidden sm:flex' }}">
        <div class="flex items-center gap-3 px-4 py-3 border-b border-base-300 bg-base-100 shrink-0">
            <button type="button" wire:click="closeConversation" class="btn btn-ghost btn-circle btn-sm sm:hidden">
                <x-icon name="o-arrow-left" class="w-4 h-4" />
            </button>
            <span class="size-9 rounded-full bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center shrink-0">
                <x-icon name="o-sparkles" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-sm text-base-content truncate">
                    {{ $conversation?->title ?? __('Percakapan baru') }}
                </div>
                <div class="text-xs text-base-content/50 truncate">
                    {{ __('Asisten menulis & merangkum · tidak terhubung ke data ERP') }}
                </div>
            </div>
            @if ($conversation)
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle btn-sm" title="{{ __('Opsi') }}">
                        <x-icon name="o-ellipsis-vertical" class="w-5 h-5" />
                    </div>
                    <ul tabindex="0"
                        class="dropdown-content menu menu-sm p-2 shadow-xl bg-base-100 border border-base-300 rounded-box w-44 z-50">
                        <li>
                            <button type="button" wire:click="openRenameModal" class="flex items-center gap-2 text-sm">
                                <x-icon name="o-pencil" class="w-4 h-4" /> {{ __('Ganti judul') }}
                            </button>
                        </li>
                        <li>
                            <button type="button" wire:click="$set('showDeleteModal', true)"
                                class="flex items-center gap-2 text-sm text-error">
                                <x-icon name="o-trash" class="w-4 h-4" /> {{ __('Hapus percakapan') }}
                            </button>
                        </li>
                    </ul>
                </div>
            @endif
        </div>

        {{-- Thread --}}
        <div class="flex-1 min-h-0 overflow-y-auto px-4 py-4 bg-base-200/40" x-ref="thread"
            wire:key="ai-thread-{{ $selectedConversationId ?? 'new' }}" x-init="scrollToBottom()">
            <div class="max-w-3xl mx-auto space-y-4">
                @if (! $conversation || $conversation->messages->isEmpty())
                    <div class="py-6 sm:py-10 text-center space-y-6" x-show="!pending">
                        <div class="space-y-2">
                            <span
                                class="inline-flex size-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 items-center justify-center">
                                <x-icon name="o-sparkles" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
                            </span>
                            <h3 class="text-lg font-semibold text-base-content">
                                {{ __('Halo, :name! Ada yang bisa dibantu?', ['name' => \App\Support\PersonName::short(auth()->user()->name, 1)]) }}
                            </h3>
                            <p class="text-sm text-base-content/60 max-w-md mx-auto">
                                {{ __('Minta bantuan meringkas, merapikan tulisan, membuat draf email, atau menjelaskan istilah. AI tidak bisa membaca atau mengubah data di Nakama.') }}
                            </p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-left">
                            @foreach ($quickActions as $key => $action)
                                <button type="button" x-on:click="useQuickAction(@js($key), @js($action['template']))"
                                    class="p-4 rounded-2xl border border-base-300 bg-base-100 hover:border-indigo-300 dark:hover:border-indigo-700 hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors cursor-pointer">
                                    <div class="flex items-center gap-2 font-semibold text-sm text-indigo-600 dark:text-indigo-400">
                                        <x-icon :name="$action['icon']" class="w-4 h-4" />
                                        {{ __($action['label']) }}
                                    </div>
                                    <p class="text-xs text-base-content/60 mt-1">{{ __($action['description']) }}</p>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @else
                    @php $lastUserContent = null; @endphp
                    @foreach ($conversation->messages as $message)
                        @if (! $message->isFromAssistant())
                            @php $lastUserContent = $message->content; @endphp
                            <div class="flex flex-col items-end gap-1" wire:key="ai-message-{{ $message->id }}">
                                @foreach ($message->files as $file)
                                    <button type="button" x-on:click="openPreview(@js($file->previewPayload()))" title="{{ __('Lihat file') }}"
                                        class="inline-flex items-center gap-1.5 max-w-[85%] sm:max-w-lg rounded-lg border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50 dark:bg-indigo-950/30 px-2 py-1 text-xs text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-950/60 cursor-pointer">
                                        <x-icon name="o-paper-clip" class="w-3.5 h-3.5 shrink-0" />
                                        <span class="truncate">{{ $file->name }}</span>
                                        @if ($file->size_bytes !== null)
                                            <span class="shrink-0 opacity-60">{{ $file->sizeLabel() }}</span>
                                        @endif
                                    </button>
                                @endforeach
                                <div
                                    class="max-w-[85%] sm:max-w-lg rounded-2xl rounded-br-sm px-3 py-2 text-sm leading-relaxed shadow-sm bg-indigo-600 text-white whitespace-pre-wrap wrap-break-word">{{ $message->content }}</div>
                            </div>
                        @else
                            <div class="flex items-start gap-2" wire:key="ai-message-{{ $message->id }}">
                                <span
                                    class="size-7 rounded-full bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center shrink-0 mt-0.5">
                                    <x-icon name="o-sparkles" class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                </span>
                                <div class="min-w-0 max-w-[85%] sm:max-w-2xl space-y-1">
                                    <div
                                        class="rounded-2xl rounded-bl-sm px-3 py-2 bg-base-100 text-base-content border border-base-300 shadow-sm">
                                        @if ($message->content !== '')
                                            <div class="ai-markdown">{!! $message->renderedContent() !!}</div>
                                        @endif

                                        @foreach ($message->files as $file)
                                            <div @class(['flex items-center gap-1 rounded-lg border border-base-300 bg-base-200/50 text-sm', 'mt-2' => $message->content !== '' || ! $loop->first])>
                                                <button type="button" x-on:click="openPreview(@js($file->previewPayload()))" title="{{ __('Lihat file') }}"
                                                    class="flex flex-1 min-w-0 items-center gap-2 px-3 py-2 text-start hover:bg-base-200 rounded-s-lg cursor-pointer">
                                                    <x-icon name="o-document-text" class="w-4 h-4 shrink-0 text-indigo-600 dark:text-indigo-400" />
                                                    <span class="truncate">{{ $file->name }}</span>
                                                    @if ($file->size_bytes !== null)
                                                        <span class="shrink-0 text-xs text-base-content/50">{{ $file->sizeLabel() }}</span>
                                                    @endif
                                                </button>
                                                <a href="{{ route('ai-center.files.download', $file) }}" download title="{{ __('Unduh') }}"
                                                    class="btn btn-ghost btn-sm btn-square">
                                                    <x-icon name="o-arrow-down-tray" class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                                </a>
                                            </div>
                                        @endforeach

                                        @if ($message->status === \App\Enums\AiMessageStatus::Error)
                                            <div @class(['flex items-center gap-2 text-sm text-error', 'mt-2' => $message->content !== ''])>
                                                <x-icon name="o-exclamation-circle" class="w-4 h-4 shrink-0" />
                                                <span class="flex-1">{{ ($message->error_code ?? \App\Enums\AiErrorCode::UpstreamError)->message() }}</span>
                                                @if ($loop->last && $lastUserContent !== null)
                                                    <button type="button" x-on:click="send(@js($lastUserContent))"
                                                        x-bind:disabled="isStreaming" class="btn btn-ghost btn-xs">
                                                        <x-icon name="o-arrow-path" class="w-3.5 h-3.5" /> {{ __('Coba lagi') }}
                                                    </button>
                                                @endif
                                            </div>
                                        @elseif ($message->status === \App\Enums\AiMessageStatus::Cancelled)
                                            <p @class(['text-xs italic text-base-content/50', 'mt-1' => $message->content !== ''])>
                                                {{ __('Jawaban dihentikan.') }}
                                            </p>
                                        @elseif ($message->status === \App\Enums\AiMessageStatus::Streaming)
                                            <p class="text-xs italic text-base-content/50">{{ __('Jawaban belum selesai.') }}</p>
                                        @endif
                                    </div>

                                    @if ($message->status === \App\Enums\AiMessageStatus::Complete)
                                        <div class="flex items-center gap-1 ps-1 text-base-content/50">
                                            <span class="text-[10px] flex items-center gap-1 me-1">
                                                <x-icon name="o-information-circle" class="w-3 h-3" />
                                                {{ __('Jawaban AI, perlu dicek') }}
                                            </span>
                                            <button type="button" title="{{ __('Salin') }}" class="btn btn-ghost btn-xs btn-square"
                                                x-data="{ copied: false }"
                                                x-on:click="navigator.clipboard.writeText(@js($message->content)); copied = true; setTimeout(() => copied = false, 1500)">
                                                <x-icon name="o-clipboard-document" class="w-3.5 h-3.5" x-show="!copied" />
                                                <x-icon name="o-check" class="w-3.5 h-3.5 text-success" x-show="copied" x-cloak />
                                            </button>
                                            <button type="button" title="{{ __('Membantu') }}"
                                                wire:click="rateMessage({{ $message->id }}, 1)"
                                                @class(['btn btn-ghost btn-xs btn-square', 'text-success' => $message->feedback === 1])>
                                                <x-icon :name="$message->feedback === 1 ? 's-hand-thumb-up' : 'o-hand-thumb-up'" class="w-3.5 h-3.5" />
                                            </button>
                                            <button type="button" title="{{ __('Tidak membantu') }}"
                                                wire:click="rateMessage({{ $message->id }}, -1)"
                                                @class(['btn btn-ghost btn-xs btn-square', 'text-error' => $message->feedback === -1])>
                                                <x-icon :name="$message->feedback === -1 ? 's-hand-thumb-down' : 'o-hand-thumb-down'" class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif

                {{-- The turn being streamed right now (browser-side only until it is saved) --}}
                <div wire:ignore wire:key="ai-pending-turn">
                    <template x-if="pending">
                        <div class="space-y-4">
                            <div class="flex flex-col items-end gap-1">
                                <template x-for="file in pending.sent" x-bind:key="file.id">
                                    <button type="button" x-on:click="openPreview(file)" title="{{ __('Lihat file') }}"
                                        class="inline-flex items-center gap-1.5 max-w-[85%] sm:max-w-lg rounded-lg border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50 dark:bg-indigo-950/30 px-2 py-1 text-xs text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-950/60 cursor-pointer">
                                        <x-icon name="o-paper-clip" class="w-3.5 h-3.5 shrink-0" />
                                        <span class="truncate" x-text="file.name"></span>
                                        <span x-show="file.size_label" class="shrink-0 opacity-60" x-text="file.size_label"></span>
                                    </button>
                                </template>
                                <div class="max-w-[85%] sm:max-w-lg rounded-2xl rounded-br-sm px-3 py-2 text-sm leading-relaxed shadow-sm bg-indigo-600 text-white whitespace-pre-wrap wrap-break-word"
                                    x-text="pending.prompt"></div>
                            </div>
                            <div class="flex items-start gap-2">
                                <span
                                    class="size-7 rounded-full bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center shrink-0 mt-0.5">
                                    <x-icon name="o-sparkles" class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                </span>
                                <div
                                    class="min-w-0 max-w-[85%] sm:max-w-2xl rounded-2xl rounded-bl-sm px-3 py-2 bg-base-100 text-base-content border border-base-300 shadow-sm">
                                    <div x-show="pending.text === '' && !pending.error"
                                        class="flex items-center gap-2 text-sm text-base-content/50">
                                        <span class="loading loading-dots loading-sm"></span>
                                        <span x-text="pending.status?.label ?? @js(__('AI sedang mengetik…'))"></span>
                                    </div>
                                    <div x-show="pending.text !== ''" class="text-sm leading-relaxed whitespace-pre-wrap wrap-break-word"
                                        x-text="pending.text"></div>
                                    <template x-for="file in pending.generated" x-bind:key="file.url">
                                        <div class="flex items-center gap-1 rounded-lg border border-base-300 bg-base-200/50 text-sm mt-2">
                                            <button type="button" x-on:click="openPreview(file)" title="{{ __('Lihat file') }}"
                                                class="flex flex-1 min-w-0 items-center gap-2 px-3 py-2 text-start hover:bg-base-200 rounded-s-lg cursor-pointer">
                                                <x-icon name="o-document-text" class="w-4 h-4 shrink-0 text-indigo-600 dark:text-indigo-400" />
                                                <span class="truncate" x-text="file.name"></span>
                                                <span x-show="file.size_label" class="shrink-0 text-xs text-base-content/50" x-text="file.size_label"></span>
                                            </button>
                                            <a x-bind:href="file.url" download title="{{ __('Unduh') }}" class="btn btn-ghost btn-sm btn-square">
                                                <x-icon name="o-arrow-down-tray" class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                            </a>
                                        </div>
                                    </template>
                                    <div x-show="pending.error" class="flex items-center gap-2 text-sm text-error">
                                        <x-icon name="o-exclamation-circle" class="w-4 h-4 shrink-0" />
                                        <span x-text="pending.error"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Composer --}}
        <div class="border-t border-base-300 bg-base-100 shrink-0" wire:ignore>
            <div class="max-w-3xl mx-auto px-4 pt-3 space-y-2">
                <div
                    class="flex items-start gap-2 rounded-lg px-3 py-2 text-xs bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900/50">
                    <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                    <span>{{ __('Jawaban AI bisa keliru — selalu cek sebelum dipakai. Jangan memasukkan data rahasia (misal nominal anggaran, data pegawai, data Pemda) di luar aturan penggunaan.') }}</span>
                </div>

                <div class="flex gap-1.5 overflow-x-auto pb-0.5">
                    @foreach ($quickActions as $key => $action)
                        <button type="button" x-on:click="useQuickAction(@js($key), @js($action['template']))"
                            x-bind:class="quickAction === @js($key) ? 'border-indigo-400 text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/30' : 'border-base-300 text-base-content/70'"
                            class="btn btn-ghost btn-xs rounded-full border font-normal shrink-0 gap-1">
                            <x-icon :name="$action['icon']" class="w-3.5 h-3.5" />
                            {{ __($action['label']) }}
                        </button>
                    @endforeach
                </div>

                <div x-show="attachments.length > 0 || uploading" x-cloak class="flex flex-wrap items-center gap-1.5">
                    <template x-for="file in attachments" x-bind:key="file.id">
                        <span class="inline-flex items-center gap-1.5 max-w-full rounded-lg border border-base-300 bg-base-200/60 ps-2 pe-1 py-0.5 text-xs">
                            <x-icon name="o-paper-clip" class="w-3.5 h-3.5 shrink-0" />
                            <button type="button" x-on:click="openPreview(file)" title="{{ __('Lihat file') }}" x-bind:disabled="file.kind === 'file' && !file.download_url" class="flex items-center gap-1.5 min-w-0 enabled:hover:underline enabled:cursor-pointer">
                                <span class="truncate max-w-48" x-text="file.name"></span>
                                <span x-show="file.size_label" class="shrink-0 opacity-60" x-text="file.size_label"></span>
                            </button>
                            <button type="button" x-on:click="removeFile(file)" title="{{ __('Hapus file') }}" class="btn btn-ghost btn-xs btn-square size-5 min-h-0">
                                <x-icon name="o-x-mark" class="w-3 h-3" />
                            </button>
                        </span>
                    </template>
                    <span x-show="uploading" class="flex items-center gap-1.5 text-xs text-base-content/50">
                        <span class="loading loading-spinner loading-xs"></span> {{ __('Mengunggah…') }}
                    </span>
                </div>

                <p x-show="errorMessage" x-cloak class="text-xs text-error flex items-center gap-1.5">
                    <x-icon name="o-exclamation-circle" class="w-4 h-4 shrink-0" />
                    <span x-text="errorMessage"></span>
                </p>
            </div>

            <form x-on:submit.prevent="send()" class="max-w-3xl mx-auto flex items-end gap-2 px-4 pt-2 pb-3">
                <input type="file" multiple x-ref="fileInput" class="hidden"
                    accept="{{ collect(config('ai_center.files.extensions'))->map(fn ($extension) => '.'.$extension)->implode(',') }}"
                    x-on:change="attachFiles($event.target.files); $event.target.value = ''">
                <button type="button" x-on:click="$refs.fileInput.click()" x-bind:disabled="isStreaming || uploading"
                    title="{{ __('Lampirkan file (maks :mb MB)', ['mb' => config('ai_center.files.max_mb')]) }}"
                    class="btn btn-ghost btn-circle btn-sm shrink-0 mb-5">
                    <x-icon name="o-paper-clip" class="w-4 h-4" />
                </button>
                <div class="flex-1 min-w-0">
                    <textarea
                        x-ref="input"
                        x-model="input"
                        x-init="resize()"
                        x-on:input="resize()"
                        x-on:keydown.enter="if (!$event.shiftKey && !$event.isComposing) { $event.preventDefault(); send(); }"
                        rows="1"
                        placeholder="{{ __('Tulis pesan... (Shift+Enter untuk baris baru)') }}"
                        class="textarea textarea-bordered textarea-sm w-full bg-base-200/60 resize-none leading-snug py-2 max-h-40 overflow-y-auto"
                        autocomplete="off"
                    ></textarea>
                    <div class="text-[10px] text-right mt-0.5"
                        x-bind:class="overLimit ? 'text-error font-semibold' : 'text-base-content/40'"
                        x-text="input.length.toLocaleString('id-ID') + ' / ' + maxChars.toLocaleString('id-ID')"></div>
                </div>
                <button type="submit" x-show="!isStreaming" x-bind:disabled="input.trim() === '' || overLimit || uploading"
                    title="{{ __('Kirim') }}" class="btn btn-primary btn-circle btn-sm shrink-0 mb-5">
                    <x-icon name="o-paper-airplane" class="w-4 h-4" />
                </button>
                <button type="button" x-show="isStreaming" x-cloak x-on:click="stop()" title="{{ __('Hentikan') }}"
                    class="btn btn-neutral btn-circle btn-sm shrink-0 mb-5">
                    <x-icon name="s-stop" class="w-4 h-4" />
                </button>
            </form>
        </div>
    </div>

    {{-- File preview: images and PDFs only; other files are downloaded --}}
    <div x-show="preview" x-cloak x-transition.opacity x-on:keydown.escape.window="closePreview()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-on:click.self="closePreview()">
        <div class="flex flex-col w-full max-w-4xl h-[85vh] rounded-2xl bg-base-100 border border-base-300 shadow-xl overflow-hidden">
            <div class="flex items-center gap-2 border-b border-base-300 px-4 py-3 shrink-0">
                <x-icon name="o-document-text" class="w-5 h-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium text-sm" x-text="preview?.name"></div>
                    <div class="text-xs text-base-content/50" x-show="preview?.size_label" x-text="preview?.size_label"></div>
                </div>
                <a x-show="preview?.download_url" x-bind:href="preview?.download_url" download class="btn btn-ghost btn-sm gap-1">
                    <x-icon name="o-arrow-down-tray" class="w-4 h-4" /> {{ __('Unduh') }}
                </a>
                <button type="button" x-on:click="closePreview()" title="{{ __('Tutup') }}" class="btn btn-ghost btn-sm btn-square">
                    <x-icon name="o-x-mark" class="w-4 h-4" />
                </button>
            </div>

            <div class="flex-1 min-h-0 overflow-auto bg-base-200/40">
                <template x-if="preview?.kind === 'image'">
                    <div class="flex h-full items-center justify-center p-4">
                        <img x-bind:src="preview.preview_url" x-bind:alt="preview.name" class="max-h-full max-w-full object-contain rounded-lg">
                    </div>
                </template>

                <template x-if="preview?.kind === 'pdf'">
                    <iframe x-bind:src="preview.preview_url" x-bind:title="preview.name" class="h-full w-full border-0"></iframe>
                </template>

            </div>
        </div>
    </div>

    {{-- Rename --}}
    <x-modal wire:model="showRenameModal" title="{{ __('Ganti Judul') }}" without-trap-focus>
        <form wire:submit="renameConversation" class="space-y-4">
            <x-input wire:model="renameTitle" label="{{ __('Judul percakapan') }}" maxlength="150" />
            <div class="flex justify-end gap-2">
                <x-button label="{{ __('Batal') }}" wire:click="$set('showRenameModal', false)" class="btn-ghost" />
                <x-button type="submit" label="{{ __('Simpan') }}" class="btn-primary" spinner="renameConversation" />
            </div>
        </form>
    </x-modal>

    {{-- Delete --}}
    <x-modal wire:model="showDeleteModal" title="{{ __('Hapus Percakapan?') }}"
        subtitle="{{ __('Percakapan ini akan hilang dari daftar Anda.') }}">
        <div class="flex justify-end gap-2 pt-4">
            <x-button label="{{ __('Batal') }}" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
            <x-button label="{{ __('Ya, Hapus') }}" wire:click="deleteConversation" class="btn-error text-white"
                spinner="deleteConversation" />
        </div>
    </x-modal>
</div>
