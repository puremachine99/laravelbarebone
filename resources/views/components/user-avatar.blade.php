@props(['user' => null, 'size' => 'size-8'])

@php
    $user ??= auth()->user();
    $avatarUrl = $user?->avatarUrl();
@endphp

@if ($avatarUrl)
    <div {{ $attributes->class(['rounded-full overflow-hidden shrink-0', $size]) }}>
        <img src="{{ $avatarUrl }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
    </div>
@else
    <div {{ $attributes->class(['bg-base-300 text-base-content flex items-center justify-center rounded-full text-xs font-bold shrink-0', $size]) }}>
        {{ $user?->initials() }}
    </div>
@endif
