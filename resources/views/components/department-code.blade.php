@props(['department', 'size' => 'badge-sm'])

@php
    $colorClasses = match ($department?->color) {
        'amber' => 'border-0 bg-linear-to-r from-amber-400 to-yellow-300 text-amber-950 shadow-[0_0_6px_-2px_rgba(251,191,36,0.6)]',
        'violet' => 'border-violet-500/30 bg-violet-500/15 text-violet-600 dark:text-violet-400',
        'rose' => 'border-rose-500/30 bg-rose-500/15 text-rose-600 dark:text-rose-400',
        'fuchsia' => 'border-fuchsia-500/30 bg-fuchsia-500/15 text-fuchsia-600 dark:text-fuchsia-400',
        'cyan' => 'border-cyan-500/30 bg-cyan-500/15 text-cyan-600 dark:text-cyan-400',
        'orange' => 'border-orange-500/30 bg-orange-500/15 text-orange-600 dark:text-orange-400',
        'teal' => 'border-teal-500/30 bg-teal-500/15 text-teal-600 dark:text-teal-400',
        default => 'badge-outline text-base-content/60',
    };

    $code = $department?->code ?? \Illuminate\Support\Str::of($department?->name)->substr(0, 3)->upper();
@endphp

@if ($department)
    <span title="{{ $department->name }}"
        {{ $attributes->class(['badge font-bold whitespace-nowrap', $colorClasses, $size]) }}>
        {{ $code }}
    </span>
@endif
