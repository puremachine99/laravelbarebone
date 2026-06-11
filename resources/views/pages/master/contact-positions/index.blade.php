<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use Flux\Flux;
use App\Models\ContactPosition;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

usesPagination();
layout('layouts.app');

state([
    'contact_position_id' => null,
    'kelompok_jabatan'    => '',
    'eselon'              => '',
    'name'                => '',
    'tingkat'             => '',
    'isEdit'              => false,
    'deleteTargetId'      => null,
    'search'              => '',
]);

state([
    'sortBy'        => 'name',
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

$contactPositions = computed(function () {
    $search = $this->search;

    return ContactPosition::query()
        ->addSelect([
            'contacts_count' => DB::table('contacts')
                ->selectRaw('count(*)')
                ->whereColumn('contact_position_id', 'contact_positions.id'),
        ])
        ->when($search, function ($q) use ($search) {
            $q->where(function ($query) use ($search) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhereRaw('LOWER(kelompok_jabatan) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhereRaw('LOWER(eselon) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhereRaw('LOWER(tingkat) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        })
        ->when($this->sortBy, fn($q) => $q->orderBy($this->sortBy, $this->sortDirection))
        ->paginate(10);
});

$resetForm = function () {
    $this->reset(['contact_position_id', 'kelompok_jabatan', 'eselon', 'name', 'tingkat', 'isEdit']);
    $this->resetValidation();
};

$save = function () {
    $this->validate([
        'name' => [
            'required',
            'string',
            'max:255',
            Rule::unique('contact_positions', 'name')->ignore($this->contact_position_id),
        ],
        'kelompok_jabatan' => ['nullable', 'string', 'max:255'],
        'eselon'           => ['nullable', 'string', 'max:255'],
        'tingkat'          => ['nullable', 'string', 'max:255'],
    ]);

    $isNew = is_null($this->contact_position_id);

    ContactPosition::updateOrCreate(
        ['id'   => $this->contact_position_id],
        [
            'name'             => $this->name,
            'kelompok_jabatan' => $this->kelompok_jabatan,
            'eselon'           => $this->eselon,
            'tingkat'          => $this->tingkat,
        ]
    );

    $this->resetForm();
    Flux::modal('contact-position-modal')->close();
    Flux::toast(
        variant: 'success',
        heading: $isNew ? 'Jabatan Kontak ditambahkan' : 'Jabatan Kontak diperbarui',
        text:    $isNew ? 'Data baru berhasil disimpan.' : 'Perubahan berhasil disimpan.',
    );
};

$edit = function (ContactPosition $position) {
    $this->resetValidation();
    $this->contact_position_id = $position->id;
    $this->name                = $position->name;
    $this->kelompok_jabatan    = $position->kelompok_jabatan;
    $this->eselon              = $position->eselon;
    $this->tingkat             = $position->tingkat;
    $this->isEdit              = true;
    Flux::modal('contact-position-modal')->show();
};

$confirmDelete = function (string $id) {
    $this->deleteTargetId = $id;
    Flux::modal('confirm-delete')->show();
};

$delete = function () {
    ContactPosition::findOrFail($this->deleteTargetId)->delete();
    $this->deleteTargetId = null;
    Flux::modal('confirm-delete')->close();
    Flux::toast(variant: 'danger', text: 'Jabatan Kontak berhasil dihapus.');
};

?>
<div>
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Data Master: Jabatan Kontak</flux:heading>
                <flux:subheading>Kelola daftar nomenklatur jabatan kontak mitra, kelompok jabatan, eselon, dan tingkat instansi.</flux:subheading>
            </div>

            <flux:modal.trigger name="contact-position-modal">
                <flux:button variant="primary" icon="plus" wire:click="resetForm">Tambah Jabatan</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Cari jabatan..."
            icon="magnifying-glass"
            clearable
        />

        <flux:separator variant="subtle" />

        <flux:table :paginate="$this->contactPositions" striped hoverable>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection"
                    wire:click="sort('name')">
                    Nomenklatur Jabatan
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'kelompok_jabatan'" :direction="$sortDirection"
                    wire:click="sort('kelompok_jabatan')">
                    Kelompok Jabatan
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'eselon'" :direction="$sortDirection"
                    wire:click="sort('eselon')">
                    Jenjang / Eselon
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tingkat'" :direction="$sortDirection"
                    wire:click="sort('tingkat')">
                    Tingkat / Keterangan
                </flux:table.column>
                <flux:table.column>Jumlah Kontak</flux:table.column>
                <flux:table.column align="right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->contactPositions as $pos)
                    <flux:table.row :key="$pos->id">
                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">{{ $pos->name }}</flux:table.cell>
                        <flux:table.cell>{{ $pos->kelompok_jabatan ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $pos->eselon ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $pos->tingkat ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $pos->contacts_count ?? 0 }}</flux:table.cell>

                        <flux:table.cell align="right">
                            <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom"
                                wire:click="edit('{{ $pos->id }}')" />
                            <flux:button variant="danger" size="sm" icon="trash" inset="top bottom"
                                wire:click="confirmDelete('{{ $pos->id }}')" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text class="text-center">Belum ada data yang ditampilkan.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="contact-position-modal" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEdit ? 'Edit Jabatan Kontak' : 'Tambah Jabatan Kontak Baru' }}</flux:heading>
                <flux:subheading>Tentukan detail kelompok jabatan, eselon, nomenklatur, dan tingkat instansi.</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Kelompok Jabatan</flux:label>
                <flux:input wire:model="kelompok_jabatan" placeholder="Cth: Jabatan Pimpinan Tinggi (JPT)" />
                <flux:error name="kelompok_jabatan" />
            </flux:field>

            <flux:field>
                <flux:label>Jenjang / Eselon</flux:label>
                <flux:input wire:model="eselon" placeholder="Cth: JPT Utama (Eselon I.a)" />
                <flux:error name="eselon" />
            </flux:field>

            <flux:field>
                <flux:label>Nomenklatur Jabatan</flux:label>
                <flux:input wire:model="name" placeholder="Cth: Kepala Dinas Provinsi" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Tingkat / Keterangan</flux:label>
                <flux:input wire:model="tingkat" placeholder="Cth: Provinsi" />
                <flux:error name="tingkat" />
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
                <flux:heading size="lg">Hapus Jabatan Kontak?</flux:heading>
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
