@props(['value'])

<span {{ $attributes->class(['tooltip']) }} data-tip="{{ \App\Support\Money::exact($value) }}">{{ \App\Support\Money::abbreviate($value) }}</span>
