@props(['user'])

@php
    $employee = $user->employee;
    $position = $employee?->positionStruktural ?? $employee?->positionFungsional;
    $tier = $user->positionTier();
@endphp

@if ($position)
    <span
        {{ $attributes->class([
            'badge badge-sm font-medium border-0 whitespace-nowrap',
            'bg-linear-to-r from-amber-400 to-yellow-300 text-amber-950 shadow-[0_0_8px_-2px_rgba(251,191,36,0.6)]' => $tier === 'c-level',
            'badge-info text-white' => $tier === 'struktural',
            'badge-success text-white' => $tier === 'fungsional',
        ]) }}>
        {{ $position->name }}
    </span>
@endif
