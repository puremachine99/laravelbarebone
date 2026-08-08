<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};

usesPagination();
layout('layouts.app');

state([
    'filter' => 'all',
]);

$notifications = computed(function () {
    return auth()->user()
        ->notifications()
        ->when($this->filter === 'unread', fn ($q) => $q->whereNull('read_at'))
        ->paginate(15);
});

$unreadCount = computed(fn () => auth()->user()->unreadNotifications()->count());

$open = function (string $id) {
    $notification = auth()->user()->notifications()->findOrFail($id);
    $notification->markAsRead();

    return redirect()->to($notification->data['url'] ?? route('dashboard'));
};

$markAllAsRead = function () {
    auth()->user()->unreadNotifications->markAsRead();
};

?>

<div class="p-4 md:p-6 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-xl font-bold text-base-content">{{ __('Notifikasi') }}</h1>

        @if ($this->unreadCount > 0)
            <button type="button" wire:click="markAllAsRead" class="btn btn-ghost btn-sm">
                {{ __('Tandai semua sudah dibaca') }}
            </button>
        @endif
    </div>

    <div role="tablist" class="tabs tabs-boxed w-fit">
        <button type="button" role="tab" wire:click="$set('filter', 'all')"
            class="tab {{ $filter === 'all' ? 'tab-active' : '' }}">
            {{ __('Semua') }}
        </button>
        <button type="button" role="tab" wire:click="$set('filter', 'unread')"
            class="tab {{ $filter === 'unread' ? 'tab-active' : '' }}">
            {{ __('Belum dibaca') }} @if ($this->unreadCount > 0)({{ $this->unreadCount }})@endif
        </button>
    </div>

    <div class="bg-base-100 border border-base-300 rounded-box divide-y divide-base-300">
        @forelse ($this->notifications as $notification)
            <button type="button" wire:click="open('{{ $notification->id }}')"
                class="w-full text-left flex items-start gap-3 p-4 hover:bg-base-200 transition-colors {{ $notification->read_at ? 'opacity-60' : '' }}">
                <x-icon name="{{ $notification->data['icon'] ?? 'o-bell' }}" class="w-5 h-5 mt-0.5 text-indigo-600 dark:text-indigo-400 shrink-0" />
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-base-content">{{ $notification->data['title'] ?? __('Notifikasi') }}</div>
                    <div class="text-sm text-base-content/70">{{ $notification->data['message'] ?? '' }}</div>
                    <div class="text-xs text-base-content/40 mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                </div>
                @unless ($notification->read_at)
                    <span class="w-2 h-2 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span>
                @endunless
            </button>
        @empty
            <div class="p-8 text-center text-sm text-base-content/50">
                {{ __('Belum ada notifikasi.') }}
            </div>
        @endforelse
    </div>

    {{ $this->notifications->links() }}
</div>
