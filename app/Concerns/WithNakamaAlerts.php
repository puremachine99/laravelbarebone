<?php

namespace App\Concerns;

/**
 * Themed popups from a Livewire component, shown by <x-nakama.dialog-host /> (resources/js/notify.js). Toasts keep
 * using Mary's $this->success() / error() / warning() / info(), which the same notification system renders.
 */
trait WithNakamaAlerts
{
    /**
     * A popup with one button, e.g. $this->alert('success', 'Data tersimpan', 'Laporan sudah dikirim ke atasan.').
     *
     * @param  'success'|'error'|'warning'|'info'  $type
     */
    public function alert(string $type, string $title, ?string $text = null, string $confirmLabel = 'OK'): void
    {
        $this->dispatch('nakama-alert', type: $type, title: $title, text: $text, confirmLabel: $confirmLabel);
    }

    /**
     * Asks first, then calls $method on this component with $params when the user confirms, e.g.
     * $this->confirmThen('archive', 'Arsipkan proyek?', 'Proyek tidak tampil lagi di dashboard.', [$project->id]).
     * Destructive actions use the red "danger" style.
     *
     * @param  list<mixed>  $params
     */
    public function confirmThen(
        string $method,
        string $title,
        ?string $text = null,
        array $params = [],
        string $confirmLabel = 'Ya, lanjutkan',
        bool $danger = false,
    ): void {
        $this->dispatch(
            'nakama-confirm',
            componentId: $this->getId(),
            method: $method,
            params: $params,
            title: $title,
            text: $text,
            confirmLabel: $confirmLabel,
            variant: $danger ? 'danger' : 'question',
        );
    }
}
