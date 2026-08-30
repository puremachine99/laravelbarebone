@props(['department', 'size' => 'badge-sm'])

@php
    $colorClasses = match ($department?->color) {
        'amber' => 'border-0 bg-linear-to-r from-amber-400 to-yellow-300 text-amber-950 shadow-[0_0_6px_-2px_rgba(251,191,36,0.6)]',
        'red' => 'border-0 bg-red-500 text-white',
        'orange' => 'border-0 bg-orange-500 text-white',
        'yellow' => 'border-0 bg-yellow-500 text-yellow-950',
        'lime' => 'border-0 bg-lime-500 text-lime-950',
        'green' => 'border-0 bg-green-500 text-white',
        'emerald' => 'border-0 bg-emerald-500 text-white',
        'teal' => 'border-0 bg-teal-500 text-white',
        'cyan' => 'border-0 bg-cyan-500 text-white',
        'sky' => 'border-0 bg-sky-500 text-white',
        'blue' => 'border-0 bg-blue-500 text-white',
        'indigo' => 'border-0 bg-indigo-500 text-white',
        'violet' => 'border-0 bg-violet-500 text-white',
        'purple' => 'border-0 bg-purple-500 text-white',
        'fuchsia' => 'border-0 bg-fuchsia-500 text-white',
        'pink' => 'border-0 bg-pink-500 text-white',
        'rose' => 'border-0 bg-rose-500 text-white',
        'slate' => 'border-0 bg-slate-500 text-white',
        'zinc' => 'border-0 bg-zinc-500 text-white',
        'stone' => 'border-0 bg-stone-500 text-white',
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
