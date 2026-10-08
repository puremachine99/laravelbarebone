@props(['name', 'words' => 2, 'fallback' => '-'])

@php
    $fullName = trim((string) $name);
    $shortName = \App\Support\PersonName::short($fullName, $words);
@endphp

@if ($fullName === '')
    {{ $fallback }}
@else
    <span {{ $attributes->class(['tooltip' => $shortName !== $fullName]) }} @if ($shortName !== $fullName) data-tip="{{ $fullName }}" @endif>{{ $shortName }}</span>
@endif
