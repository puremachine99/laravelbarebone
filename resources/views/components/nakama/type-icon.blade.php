{{-- One icon per notification type, picked client-side: pass the Alpine expression that yields the type. --}}
@props(['type', 'size' => 'size-5'])

<span {{ $attributes->merge(['class' => 'inline-flex']) }} aria-hidden="true">
    <x-icon name="o-check-circle" class="{{ $size }}" x-show="{{ $type }} === 'success'" />
    <x-icon name="o-x-circle" class="{{ $size }}" x-show="{{ $type }} === 'error'" x-cloak />
    <x-icon name="o-exclamation-triangle" class="{{ $size }}" x-show="['warning', 'danger'].includes({{ $type }})" x-cloak />
    <x-icon name="o-information-circle" class="{{ $size }}" x-show="{{ $type }} === 'info'" x-cloak />
    <x-icon name="o-question-mark-circle" class="{{ $size }}" x-show="{{ $type }} === 'question'" x-cloak />
</span>
