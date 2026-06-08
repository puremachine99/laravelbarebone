<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use Flux\Flux;
use App\Models\Urusan;
use Illuminate\Validation\Rule;

usesPagination();
layout('layouts.app');

state([
    'urusan_id'       => null,
    'code'            => '',
    'urusan'          => '',
    'isEdit'          => false,
    'deleteTargetId'  => null,
    'search'          => '',
]);

state([
    'sortBy'        => 'code',
    'sortDirection' => 'asc',
]);

$sort = function ($column) {
    if ($this->sortBy === $column) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy        = $column;
        $this->sortDirection = 'asc';
    }
};

$updatedSearch = function () {
    $this->resetPage();
};

$urusans = computed(function () {
    $search = $this->search;

    return Urusan::query()
        ->when($search, fn($q) => $q->whereRaw('LOWER(urusan) LIKE ?', ['%' . strtolower($search) . '%'])
                                    ->orWhereRaw('LOWER(code) LIKE ?', ['%' . strtolower($search) . '%']))
        ->when($this->sortBy, fn($q) => $q->orderBy($this->sortBy, $this->sortDirection))
        ->paginate(10);
});

$resetForm = function () {
    $this->reset(['urusan_id', 'code', 'urusan', 'isEdit']);
    $this->resetValidation();
};

$save = function () {
    $this->validate([
        'code' => [
            'required',
            'string',
            'max:10',
            Rule::unique('urusans', 'code')->ignore($this->urusan_id),
        ],
        'urusan' => ['required', 'string', 'max:255'],
    ]);

    $isNew = is_null($this->urusan_id);

    Urusan::updateOrCreate(
        ['id'   => $this->urusan_id],
        ['code' => $this->code, 'urusan' => $this->urusan]
    );

    $this->resetForm();
    Flux::modal('urusan-modal')->close();
    Flux::toast(
        variant: 'success',
        heading: $isNew ? 'Urusan ditambahkan' : 'Urusan diperbarui',
        text:    $isNew ? 'Data baru berhasil disimpan.' : 'Perubahan berhasil disimpan.',
    );
};

$edit = function (Urusan $urusanModel) {
    $this->resetValidation();
    $this->urusan_id = $urusanModel->id;
    $this->code      = $urusanModel->code;
    $this->urusan    = $urusanModel->urusan;
    $this->isEdit    = true;
    Flux::modal('urusan-modal')->show();
};

$confirmDelete = function (string $id) {
    $this->deleteTargetId = $id;
    Flux::modal('confirm-delete')->show();
};

$delete = function () {
    Urusan::findOrFail($this->deleteTargetId)->delete();
    $this->deleteTargetId = null;
    Flux::modal('confirm-delete')->close();
    Flux::toast(variant: 'danger', text: 'Urusan berhasil dihapus.');
};

?>
<div>
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Data Master: Urusan</flux:heading>
                <flux:subheading>Kelola daftar urusan (kode dan bidang).</flux:subheading>
            </div>

            <flux:modal.trigger name="urusan-modal">
                <flux:button variant="primary" icon="plus" wire:click="resetForm">Urusan</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Cari kode atau nama urusan..."
            icon="magnifying-glass"
            clearable
        />

        <flux:separator variant="subtle" />

        <flux:table :paginate="$this->urusans" striped hoverable>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection"
                    wire:click="sort('code')">
                    Kode Urusan
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'urusan'" :direction="$sortDirection"
                    wire:click="sort('urusan')">
                    Nama Bidang Urusan
                </flux:table.column>
                <flux:table.column align="right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->urusans as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <flux:badge variant="pill" color="blue">{{ $item->code }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>{{ $item->urusan }}</flux:table.cell>

                        <flux:table.cell align="right">
                            <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom"
                                wire:click="edit('{{ $item->id }}')" />
                            <flux:button variant="danger" size="sm" icon="trash" inset="top bottom"
                                wire:click="confirmDelete('{{ $item->id }}')" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            <flux:text class="text-center">Belum ada data yang ditampilkan.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="urusan-modal" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEdit ? 'Edit Urusan' : 'Tambah Urusan Baru' }}</flux:heading>
                <flux:subheading>Tentukan kode unik dan nama bidang urusan.</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Kode Urusan</flux:label>
                <flux:input wire:model="code" placeholder="Cth: 01" required />
                <flux:error name="code" />
            </flux:field>

            <flux:field>
                <flux:label>Nama Bidang Urusan</flux:label>
                <flux:input wire:model="urusan" placeholder="Cth: Bidang Pendidikan" required />
                <flux:error name="urusan" />
            </flux:field>

            <div class="flex justify-end space-x-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan Data</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-delete" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Hapus Urusan?</flux:heading>
                <flux:text class="mt-1">Tindakan ini tidak bisa dibatalkan.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">Ya, Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
