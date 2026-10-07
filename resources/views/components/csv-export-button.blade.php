@props(['class' => '', 'slug' => null, 'showLabel' => false])

@php
    $export = $slug
        ? \App\Support\CsvExport\CsvExportRegistry::find($slug)
        : \App\Support\CsvExport\CsvExportRegistry::forRouteName(request()->route()?->getName());
@endphp

@if ($export && (! $export->ability || auth()->user()?->can($export->ability)))
    <button
        type="button"
        title="{{ $export->label }} (CSV)"
        x-on:click="window.dispatchEvent(new CustomEvent('csv-export:start', {
            detail: { url: @js(route('csv-export.download', $export->slug)), label: @js($export->label) },
        }))"
        {{ $attributes->class([$showLabel ? 'btn btn-outline btn-sm gap-2' : 'btn btn-ghost btn-circle btn-sm text-base-content/50 hover:text-base-content', $class]) }}
    >
        <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
        @if ($showLabel)
            <span>{{ $export->label }}</span>
        @endif
    </button>
@endif
