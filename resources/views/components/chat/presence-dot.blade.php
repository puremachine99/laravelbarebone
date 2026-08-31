@props(['user'])

<span
    x-data
    {{ $attributes->class(['inline-block w-2 h-2 rounded-full shrink-0']) }}
    x-bind:class="{
        'bg-green-500': $store.presence.statusOf({{ $user->id }}) === 'online',
        'bg-amber-500': $store.presence.statusOf({{ $user->id }}) === 'away',
        'bg-red-500': $store.presence.statusOf({{ $user->id }}) === 'busy',
        'bg-base-content/30': $store.presence.statusOf({{ $user->id }}) === 'offline',
    }"
    x-bind:title="({
        online: @js(__('Online')),
        away: @js(__('Pergi Sebentar')),
        busy: @js(__('Sibuk')),
        offline: @js(__('Offline')),
    })[$store.presence.statusOf({{ $user->id }})]"
></span>
