@props(['user', 'size' => 'badge-sm'])

<x-department-code :department="$user->employee?->department" :size="$size" {{ $attributes }} />
