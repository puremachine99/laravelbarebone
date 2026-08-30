@props(['department', 'size' => 'badge-sm'])

@php
    $colorClasses = match ($department?->color) {
        'amber' => 'border-0 bg-linear-to-r from-amber-400 to-yellow-300 text-amber-950 shadow-[0_0_6px_-2px_rgba(251,191,36,0.6)]',
        'red' => 'border-red-500/30 bg-red-500/15 text-red-600 dark:text-red-400',
        'orange' => 'border-orange-500/30 bg-orange-500/15 text-orange-600 dark:text-orange-400',
        'yellow' => 'border-yellow-500/30 bg-yellow-500/15 text-yellow-700 dark:text-yellow-400',
        'lime' => 'border-lime-500/30 bg-lime-500/15 text-lime-700 dark:text-lime-400',
        'green' => 'border-green-500/30 bg-green-500/15 text-green-600 dark:text-green-400',
        'emerald' => 'border-emerald-500/30 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        'teal' => 'border-teal-500/30 bg-teal-500/15 text-teal-600 dark:text-teal-400',
        'cyan' => 'border-cyan-500/30 bg-cyan-500/15 text-cyan-600 dark:text-cyan-400',
        'sky' => 'border-sky-500/30 bg-sky-500/15 text-sky-600 dark:text-sky-400',
        'blue' => 'border-blue-500/30 bg-blue-500/15 text-blue-600 dark:text-blue-400',
        'indigo' => 'border-indigo-500/30 bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
        'violet' => 'border-violet-500/30 bg-violet-500/15 text-violet-600 dark:text-violet-400',
        'purple' => 'border-purple-500/30 bg-purple-500/15 text-purple-600 dark:text-purple-400',
        'fuchsia' => 'border-fuchsia-500/30 bg-fuchsia-500/15 text-fuchsia-600 dark:text-fuchsia-400',
        'pink' => 'border-pink-500/30 bg-pink-500/15 text-pink-600 dark:text-pink-400',
        'rose' => 'border-rose-500/30 bg-rose-500/15 text-rose-600 dark:text-rose-400',
        'slate' => 'border-slate-500/30 bg-slate-500/15 text-slate-600 dark:text-slate-400',
        'zinc' => 'border-zinc-500/30 bg-zinc-500/15 text-zinc-600 dark:text-zinc-400',
        'stone' => 'border-stone-500/30 bg-stone-500/15 text-stone-600 dark:text-stone-400',
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
