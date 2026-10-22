@php
    $types = ['success' => 'Sukses', 'error' => 'Error', 'warning' => 'Peringatan', 'info' => 'Info'];
    $buttonClass = ['success' => 'btn-success', 'error' => 'btn-error', 'warning' => 'btn-warning', 'info' => 'btn-info'];
@endphp

<div class="space-y-6">
    <x-nakama.flash />

    <div>
        <h1 class="text-xl font-bold text-base-content">UI Kit Notifikasi</h1>
        <p class="text-sm text-base-content/60 mt-0.5">
            Toast, popup, dan konfirmasi Nakama. Semua mengikuti tema aktif; ganti tema di Profil &rarr; Tampilan untuk membandingkan.
            Sudah dikonfirmasi: <span class="badge badge-sm badge-primary">{{ $confirmedCount }}</span>
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card bg-base-100 border border-base-300 shadow-sm">
            <div class="card-body gap-3">
                <h2 class="card-title text-base">Toast</h2>
                <p class="text-xs text-base-content/60">Dari JavaScript</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($types as $type => $label)
                        <button type="button" class="btn btn-sm {{ $buttonClass[$type] }}"
                            x-on:click="nakama.{{ $type }}('{{ $label }}', 'nakama.{{ $type }}(title, description)')">{{ $label }}</button>
                    @endforeach
                    <button type="button" class="btn btn-sm"
                        x-on:click="nakama.toast({ type: 'info', title: 'Item dipindah ke arsip', description: 'Toast dengan tombol aksi, tidak hilang sendiri.', timeout: 0, action: { label: 'Batalkan', onClick: () => nakama.success('Dibatalkan') } })">Dengan aksi</button>
                </div>
                <p class="text-xs text-base-content/60">Dari Livewire (Mary: <code>$this->success()</code> dll., tidak perlu diubah)</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($types as $type => $label)
                        <button type="button" class="btn btn-sm btn-outline" wire:click="serverToast('{{ $type }}')">{{ $label }}</button>
                    @endforeach
                    <button type="button" class="btn btn-sm btn-outline" wire:click="flashMessage">session()->flash()</button>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 border border-base-300 shadow-sm">
            <div class="card-body gap-3">
                <h2 class="card-title text-base">Popup (alert)</h2>
                <p class="text-xs text-base-content/60">Dari JavaScript: <code>await nakama.alert({ type, title, text })</code></p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($types as $type => $label)
                        <button type="button" class="btn btn-sm {{ $buttonClass[$type] }}"
                            x-on:click="nakama.alert({ type: '{{ $type }}', title: '{{ $label }}', text: 'Popup dari nakama.alert().' })">{{ $label }}</button>
                    @endforeach
                </div>
                <p class="text-xs text-base-content/60">Dari Livewire: <code>$this->alert('success', 'Judul', 'Teks')</code> (trait WithNakamaAlerts)</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($types as $type => $label)
                        <button type="button" class="btn btn-sm btn-outline" wire:click="serverAlert('{{ $type }}')">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card bg-base-100 border border-base-300 shadow-sm">
            <div class="card-body gap-3">
                <h2 class="card-title text-base">Konfirmasi</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-primary"
                        x-on:click="nakama.confirm({ title: 'Kirim laporan?', text: 'Laporan akan dikirim ke atasan.', confirmLabel: 'Kirim' }).then((ok) => ok ? nakama.success('Terkirim') : nakama.info('Dibatalkan'))">nakama.confirm()</button>
                    <button type="button" class="btn btn-sm btn-error text-white"
                        x-on:click="nakama.confirm({ title: 'Hapus data?', text: 'Tindakan ini tidak bisa dibatalkan.', variant: 'danger', confirmLabel: 'Ya, hapus' }).then((ok) => ok && nakama.success('Terhapus'))">Varian bahaya</button>
                    <button type="button" class="btn btn-sm btn-outline"
                        x-on:click="nakama.prompt({ title: 'Hapus permanen?', text: 'Data tidak bisa dipulihkan.', type: 'danger', expected: 'HAPUS', confirmLabel: 'Hapus permanen' }).then((value) => value && nakama.success('Dihapus permanen'))">Ketik untuk konfirmasi</button>
                </div>
                <p class="text-xs text-base-content/60">Livewire <code>wire:confirm</code> (otomatis memakai popup ini)</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline" wire:click="confirmed('wire:confirm')"
                        wire:confirm="Jalankan aksi ini?">wire:confirm</button>
                    <button type="button" class="btn btn-sm btn-outline text-error" wire:click="confirmed('wire:confirm hapus')"
                        wire:confirm="Hapus item ini? Tidak bisa dibatalkan." data-confirm-title="Hapus item?" data-confirm-button="Ya, hapus">wire:confirm (hapus)</button>
                    <button type="button" class="btn btn-sm btn-outline" wire:click="confirmed('wire:confirm.prompt')"
                        wire:confirm.prompt="Kosongkan cache aplikasi?|RESET">wire:confirm.prompt</button>
                    <button type="button" class="btn btn-sm btn-outline" wire:click="askServerConfirm">$this->confirmThen()</button>
                    <button type="button" class="btn btn-sm btn-outline" wire:click="$set('showConfirmModal', true)">&lt;x-nakama.confirm-modal&gt;</button>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 border border-base-300 shadow-sm">
            <div class="card-body gap-2 text-sm">
                <h2 class="card-title text-base">Cara pakai</h2>
                <pre class="text-xs bg-base-200 rounded p-3 overflow-x-auto"><code>// JavaScript / Alpine
nakama.success('Tersimpan');
if (await nakama.confirm({ title: 'Hapus?', variant: 'danger' })) { … }

// Livewire (PHP)
use Mary\Traits\Toast;                 // $this->success(), ->error(), …
use App\Concerns\WithNakamaAlerts;     // $this->alert(), ->confirmThen()

// Blade
&lt;button wire:click="delete" wire:confirm="Hapus data ini?"&gt;
&lt;x-nakama.confirm-modal wire:model="showDeleteModal" title="Hapus?" confirm="delete" /&gt;
session()->flash('success', '…') + &lt;x-nakama.flash /&gt;</code></pre>
            </div>
        </div>
    </div>

    <x-nakama.confirm-modal wire:model="showConfirmModal" title="Simpan perubahan?" subtitle="Contoh komponen confirm-modal yang dibuka lewat state Livewire."
        confirm="confirmed('confirm-modal')" confirm-label="Simpan" variant="question" />
</div>
