@props(['class' => ''])

@php
    $export = \App\Support\CsvExport\CsvExportRegistry::forRouteName(request()->route()?->getName());
@endphp

@if ($export && (! $export->ability || auth()->user()?->can($export->ability)))
    <button
        type="button"
        title="{{ $export->label }} (CSV)"
        x-on:click="window.dispatchEvent(new CustomEvent('csv-export:start', {
            detail: { url: @js(route('csv-export.download', $export->slug)), label: @js($export->label) },
        }))"
        {{ $attributes->class(['btn btn-ghost btn-circle btn-sm text-base-content/50 hover:text-base-content', $class]) }}
    >
        <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
    </button>
@endif
