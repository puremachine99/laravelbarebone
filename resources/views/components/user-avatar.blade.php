@props(['user' => null, 'size' => 'size-8', 'ring' => true])

@php
    $user ??= auth()->user();
    $avatarUrl = $user?->avatarUrl();
    $tier = $ring ? $user?->positionTier() : null;

    $ringClass = match ($tier) {
        'c-level' => 'ring-2 ring-amber-400',
        'struktural' => 'ring-2 ring-blue-500',
        'fungsional' => 'ring-2 ring-emerald-500',
        default => '',
    };
@endphp

@if ($avatarUrl)
    <div {{ $attributes->class(['rounded-full overflow-hidden shrink-0', $size, $ringClass]) }}>
        <img src="{{ $avatarUrl }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
    </div>
@else
    <div {{ $attributes->class(['bg-base-300 text-base-content flex items-center justify-center rounded-full text-xs font-bold shrink-0', $size, $ringClass]) }}>
        {{ $user?->initials() }}
    </div>
@endif
