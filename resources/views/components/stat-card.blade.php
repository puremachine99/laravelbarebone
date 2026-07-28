@props(['label', 'value', 'accent' => 'border-t-primary'])

<div {{ $attributes->class(['card bg-base-100 border border-base-300 border-t-4 p-4 rounded-xl', $accent]) }}>
    <div class="text-xs font-semibold text-base-content/40 uppercase tracking-wide">{{ $label }}</div>
    <div class="text-2xl font-bold text-base-content mt-1">{{ $value }}</div>
    @if ($slot->isNotEmpty())
        <div class="text-xs text-base-content/50 mt-1">{{ $slot }}</div>
    @endif
</div>
