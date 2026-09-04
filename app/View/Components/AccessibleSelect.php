<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Mary\View\Components\Select as MarySelect;

/**
 * Overrides Mary UI's <x-select> markup so the visible label is actually wired to the
 * <select> element via aria-labelledby. Mary renders the label text as a <legend> for the
 * surrounding <fieldset>, which only names the fieldset group — the <select> itself ends up
 * with no accessible name, which Lighthouse's accessibility-tree / "form elements" audits flag.
 *
 * Registered over the 'select' Blade component alias in AppServiceProvider (after all
 * providers have booted, so it wins regardless of provider load order) instead of patching
 * the vendor file, which would be overwritten by `composer update`.
 */
class AccessibleSelect extends MarySelect
{
    public function render(): View|Closure|string
    {
        return <<<'BLADE'
            <div>
                @php
                    // We need this extra step to support models arrays. Ex: wire:model="emails.0"  , wire:model="emails.1"
                    $uuid = $uuid . $modelName()
                @endphp

                <fieldset class="fieldset py-0">
                    {{-- STANDARD LABEL --}}
                    @if($label && !$inline)
                        <legend class="fieldset-legend mb-0.5" id="{{ $uuid }}-label">
                            {{ $label }}

                            @if($attributes->get('required'))
                                <span class="text-error">*</span>
                            @endif

                            {{-- INPUT POPOVER --}}
                            @if($popover)
                                <x-mary-popover offset="5" position="top-start">
                                    <x-slot:trigger class="{{ $popoverTriggerClass }}">
                                        <x-mary-icon :name="$popoverIcon" class="w-4 h-4 opacity-40 mb-0.5" />
                                    </x-slot:trigger>
                                    <x-slot:content class="{{ $popoverContentClass }}">
                                        {{ $popover }}
                                    </x-slot:content>
                                </x-mary-popover>
                            @endif
                        </legend>
                    @endif

                    <label @class(["floating-label" => $label && $inline])>
                        {{-- FLOATING LABEL--}}
                        @if ($label && $inline)
                            <span class="font-semibold" id="{{ $uuid }}-label">{{ $label }}</span>
                        @endif

                        <div @class(["w-full", "join" => $prepend || $append])>
                            {{-- PREPEND --}}
                            @if($prepend)
                                {{ $prepend }}
                            @endif

                            {{-- THE LABEL THAT HOLDS THE INPUT --}}
                            <label
                                {{
                                    $attributes->whereStartsWith('class')->class([
                                        "select w-full",
                                        "join-item" => $prepend || $append,
                                        "border-dashed" => $attributes->has("readonly") && $attributes->get("readonly") == true,
                                        "!select-error" => $errorFieldName() && $errors->has($errorFieldName()) && !$omitError
                                    ])
                                }}
                             >

                                {{-- PREFIX --}}
                                @if($prefix)
                                    <span class="label">{{ $prefix }}</span>
                                @endif

                                {{-- ICON LEFT --}}
                                @if($icon)
                                    <x-mary-icon :name="$icon" class="pointer-events-none w-4 h-4 -ms-1 opacity-40" />
                                @endif

                                {{-- SELECT --}}
                                <select id="{{ $uuid }}" @if($label) aria-labelledby="{{ $uuid }}-label" @endif {{ $attributes->whereDoesntStartWith('class') }}>
                                    @if($placeholder)
                                        <option value="{{ $placeholderValue }}">{{ $placeholder }}</option>
                                    @endif

                                    @foreach ($options as $option)
                                        <option value="{{ data_get($option, $optionValue) }}" @if(data_get($option, 'disabled')) disabled @endif>{{ data_get($option, $optionLabel) }}</option>
                                    @endforeach
                                </select>

                                {{-- ICON RIGHT --}}
                                @if($iconRight)
                                    <x-mary-icon :name="$iconRight" class="pointer-events-none w-4 h-4 opacity-40" />
                                @endif

                                {{-- SUFFIX --}}
                                @if($suffix)
                                    <span class="label">{{ $suffix }}</span>
                                @endif
                            </label>

                            {{-- APPEND --}}
                            @if($append)
                                {{ $append }}
                            @endif
                        </div>
                    </label>

                    {{-- ERROR --}}
                    @if(!$omitError && $errors->has($errorFieldName()))
                        @foreach($errors->get($errorFieldName()) as $message)
                            @foreach(Arr::wrap($message) as $line)
                                <div class="{{ $errorClass }}" x-class="text-error">{{ $line }}</div>
                                @break($firstErrorOnly)
                            @endforeach
                            @break($firstErrorOnly)
                        @endforeach
                    @endif

                    {{-- HINT --}}
                    @if($hint)
                        <div class="{{ $hintClass }}" x-classes="fieldset-label">{{ $hint }}</div>
                    @endif
                </fieldset>
            </div>
            BLADE;
    }
}
