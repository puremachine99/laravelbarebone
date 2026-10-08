@props(['value', 'abbreviated' => false])

@if ($abbreviated)
    <span {{ $attributes->class(['tooltip']) }} data-tip="{{ \App\Support\Money::exact($value) }}">{{ \App\Support\Money::abbreviate($value) }}</span>
@else
    <span {{ $attributes }}>{{ \App\Support\Money::exact($value) }}</span>
@endif
