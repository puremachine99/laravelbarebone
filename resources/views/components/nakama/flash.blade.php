{{--
    Shows session()->flash('success' | 'error' | 'warning' | 'info', …) as a toast. Placed in the layouts (redirects,
    full page loads) and inside Livewire views that flash during an action. The message is pulled from the session,
    so it shows once even though Livewire re-renders, and a new wire:key per render makes x-init run again.
--}}
@php
    $messages = collect(['success', 'error', 'warning', 'info'])
        ->mapWithKeys(fn (string $type) => [$type => session()->pull($type)])
        ->filter(fn ($message) => is_string($message) && $message !== '');
@endphp

@foreach ($messages as $type => $message)
    <div hidden wire:key="nakama-flash-{{ $type }}-{{ \Illuminate\Support\Str::random(8) }}" data-nakama-flash="{{ $type }}"
        x-data x-init="nakama.toast({{ \Illuminate\Support\Js::from(['type' => $type, 'title' => $message]) }})">{{ $message }}</div>
@endforeach
