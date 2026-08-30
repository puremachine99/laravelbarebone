@props(['user' => null, 'size' => 'size-8', 'ring' => true, 'department' => false])

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

<div {{ $attributes->class(['relative inline-flex shrink-0', $size]) }}>
    @if ($avatarUrl)
        <div class="rounded-full overflow-hidden w-full h-full {{ $ringClass }}">
            <img src="{{ $avatarUrl }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
        </div>
    @else
        <div
            class="bg-base-300 text-base-content flex items-center justify-center rounded-full text-xs font-bold w-full h-full {{ $ringClass }}">
            {{ $user?->initials() }}
        </div>
    @endif

    @if ($department)
        <div class="absolute left-1/2 -bottom-2 -translate-x-1/2 z-10">
            <x-department-code :department="$user?->employee?->department" size="text-[8px] leading-none px-1 py-0 h-3.5 min-h-0"
                class="ring-2 ring-base-100 shadow-sm" />
        </div>
    @endif
</div>
