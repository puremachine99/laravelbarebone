{{--
    Themed confirmation popup driven by a Livewire boolean, for flows that keep their own state (e.g. deleteTargetId):

        <x-nakama.confirm-modal wire:model="showDeleteModal" title="Hapus OPD?" subtitle="Tidak bisa dibatalkan."
            confirm="delete" confirm-label="Ya, Hapus" />

    "cancel" is an optional Livewire method run on Batal (default: just closes). The slot adds content under the text.
--}}
@props([
    'title',
    'subtitle' => null,
    'confirm',
    'confirmLabel' => 'Ya, Hapus',
    'cancel' => null,
    'cancelLabel' => 'Batal',
    'variant' => 'danger',
])

@php
    $type = $variant === 'danger' ? 'danger' : $variant;
    $closeExpression = $cancel ? '$wire.'.$cancel.'()' : 'open = false';
    $titleId = 'nk-confirm-'.\Illuminate\Support\Str::random(6);
    // wire:target takes the method name only, e.g. "confirmed" for confirm="confirmed('x')".
    $target = \Illuminate\Support\Str::before($confirm, '(');
@endphp

<div x-data="{ open: @entangle($attributes->wire('model')) }">
    <dialog class="modal nk-dialog" x-effect="open ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
        x-on:cancel.prevent="{{ $closeExpression }}" aria-labelledby="{{ $titleId }}">
        <div class="modal-box nk-dialog__box nk-dialog--{{ $type }}">
            <span class="nk-dialog__mark" aria-hidden="true"></span>

            <div class="nk-dialog__icon">
                <x-nakama.type-icon type="'{{ $type }}'" size="size-18" />
            </div>

            <h3 id="{{ $titleId }}" class="nk-dialog__title">{{ $title }}</h3>
            @if ($subtitle)
                <p class="nk-dialog__text">{{ $subtitle }}</p>
            @endif

            {{ $slot }}

            <div class="nk-dialog__actions">
                {{-- Safe default focus: Batal for a dangerous action. --}}
                <button type="button" class="btn btn-ghost" x-on:click="{{ $closeExpression }}" @if ($type === 'danger') autofocus @endif>{{ $cancelLabel }}</button>
                <button type="button" @class(['btn', 'btn-error text-white' => $type === 'danger', 'btn-primary' => $type !== 'danger'])
                    wire:click="{{ $confirm }}" wire:loading.attr="disabled" wire:target="{{ $target }}"
                    @if ($type !== 'danger') autofocus @endif>
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="{{ $target }}"></span>
                    {{ $confirmLabel }}
                </button>
            </div>
        </div>
        <div class="modal-backdrop" x-on:click="{{ $closeExpression }}"></div>
    </dialog>
</div>
