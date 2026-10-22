<?php

namespace App\Livewire\Tech;

use App\Concerns\WithNakamaAlerts;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * /dev/ui-kit (Super Admin / Sentry permission only): every Nakama notification (toast, alert, confirm, prompt, wire:confirm, confirm modal, flash) with
 * the code that triggers it, to check the look in each theme and copy the usage.
 */
#[Title('UI Kit Notifikasi')]
class UiKit extends Component
{
    use Toast;
    use WithNakamaAlerts;

    public bool $showConfirmModal = false;

    public int $confirmedCount = 0;

    public function serverToast(string $type): void
    {
        match ($type) {
            'success' => $this->success('Data tersimpan', 'Dikirim dari server dengan $this->success().'),
            'error' => $this->error('Gagal menyimpan', 'Dikirim dari server dengan $this->error().'),
            'warning' => $this->warning('Kuota hampir habis', 'Dikirim dari server dengan $this->warning().'),
            default => $this->info('Sinkronisasi berjalan', 'Dikirim dari server dengan $this->info().'),
        };
    }

    public function serverAlert(string $type): void
    {
        $this->alert($type, match ($type) {
            'success' => 'Berhasil!',
            'error' => 'Terjadi kesalahan',
            'warning' => 'Perhatian',
            default => 'Informasi',
        }, 'Popup ini dikirim dari server dengan $this->alert().');
    }

    public function askServerConfirm(): void
    {
        $this->confirmThen('confirmed', 'Arsipkan laporan?', 'Ditanya dari server dengan $this->confirmThen(); method confirmed() jalan setelah user setuju.', ['server']);
    }

    public function confirmed(string $source = 'wire:confirm'): void
    {
        $this->confirmedCount++;
        $this->showConfirmModal = false;
        $this->success('Aksi dijalankan', "Dikonfirmasi lewat {$source}.");
    }

    public function flashMessage(): void
    {
        session()->flash('success', 'Pesan flash dari session()->flash(), tampil sebagai toast.');
    }

    public function render()
    {
        return view('livewire.tech.ui-kit');
    }
}
