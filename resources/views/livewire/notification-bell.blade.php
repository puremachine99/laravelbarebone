<?php

use function Livewire\Volt\{state, computed};

$notifications = computed(fn () => auth()->user()->notifications()->latest()->limit(8)->get());

$unreadCount = computed(fn () => auth()->user()->unreadNotifications()->count());

$open = function (string $id) {
    $notification = auth()->user()->notifications()->findOrFail($id);
    $notification->markAsRead();

    return redirect()->to($notification->data['url'] ?? route('dashboard'));
};

$markAllAsRead = function () {
    auth()->user()->unreadNotifications->markAsRead();
};

$receiveBroadcast = function () {
    unset($this->notifications, $this->unreadCount);
};

?>

<div class="dropdown dropdown-end"
    x-data
    x-init="Echo.private('App.Models.User.{{ auth()->id() }}').notification(() => $wire.receiveBroadcast())">
    <div tabindex="0" role="button" aria-label="{{ __('Notifikasi') }}" aria-haspopup="true" class="btn btn-ghost btn-circle btn-sm relative cursor-pointer">
        <x-icon name="o-bell" class="w-4 h-4" />
        @if ($this->unreadCount > 0)
            <span class="absolute -top-1 -right-1 badge badge-error badge-xs text-[10px] font-bold px-1">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </div>

    <div tabindex="0" class="dropdown-content menu p-0 shadow-xl bg-base-100 border border-base-300 rounded-box w-80 z-50">
        <div class="flex items-center justify-between px-4 py-3 border-b border-base-300">
            <span class="font-bold text-sm text-base-content">{{ __('Notifikasi') }}</span>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs text-indigo-600 dark:text-indigo-400 font-medium cursor-pointer">
                    {{ __('Tandai semua dibaca') }}
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-base-300">
            @forelse ($this->notifications as $notification)
                <button type="button" wire:click="open('{{ $notification->id }}')"
                    class="w-full text-left flex items-start gap-2 px-4 py-3 hover:bg-base-200 transition-colors cursor-pointer {{ $notification->read_at ? 'opacity-60' : '' }}">
                    <x-icon name="{{ $notification->data['icon'] ?? 'o-bell' }}" class="w-4 h-4 mt-0.5 text-indigo-600 dark:text-indigo-400 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-xs text-base-content truncate">{{ $notification->data['title'] ?? __('Notifikasi') }}</div>
                        <div class="text-xs text-base-content/70 line-clamp-2">{{ $notification->data['message'] ?? '' }}</div>
                        <div class="text-[11px] text-base-content/40 mt-0.5">{{ $notification->created_at->diffForHumans() }}</div>
                    </div>
                </button>
            @empty
                <div class="px-4 py-8 text-center text-xs text-base-content/50">
                    {{ __('Belum ada notifikasi.') }}
                </div>
            @endforelse
        </div>

        <a href="{{ route('notifications.index') }}" wire:navigate
            class="block text-center text-xs font-medium text-indigo-600 dark:text-indigo-400 px-4 py-3 border-t border-base-300 cursor-pointer">
            {{ __('Lihat semua notifikasi') }}
        </a>
    </div>
</div>
