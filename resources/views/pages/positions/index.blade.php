<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use Flux\Flux;
use App\Models\Position;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

usesPagination();
layout('layouts.app');

state([
    'position_id'     => null,
    'name'            => '',
    'type'            => 'Fungsional',
    'isEdit'          => false,
    'deleteTargetId'  => null,
    'search'          => '',
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

$positions = computed(function () {
    $search = $this->search;

    return Position::query()
        ->addSelect([
            'employee_count' => DB::table('employees')
                ->selectRaw('count(*)')
                ->whereColumn('position_fungsional_id', 'positions.id')
                ->orWhereColumn('position_struktural_id', 'positions.id'),
        ])
        ->when($search, fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']))
        ->when($this->sortBy, fn($q) => $q->orderBy($this->sortBy, $this->sortDirection))
        ->paginate(10);
});

$resetForm = function () {
    $this->reset(['position_id', 'name', 'type', 'isEdit']);
    $this->resetValidation();
};

$save = function () {
    $this->validate([
        'name' => [
            'required',
            'string',
            'max:100',
            Rule::unique('positions', 'name')->ignore($this->position_id),
        ],
        'type' => ['required', 'in:Fungsional,Struktural'],
    ]);

    $isNew = is_null($this->position_id);

    Position::updateOrCreate(
        ['id'   => $this->position_id],
        ['name' => $this->name, 'type' => $this->type]
    );

    $this->resetForm();
    Flux::modal('position-modal')->close();
    Flux::toast(
        variant: 'success',
        heading: $isNew ? 'Posisi ditambahkan' : 'Posisi diperbarui',
        text:    $isNew ? 'Data baru berhasil disimpan.' : 'Perubahan berhasil disimpan.',
    );
};

$edit = function (Position $position) {
    $this->resetValidation();
    $this->position_id = $position->id;
    $this->name        = $position->name;
    $this->type        = $position->type;
    $this->isEdit      = true;
    Flux::modal('position-modal')->show();
};

$confirmDelete = function (string $id) {
    $this->deleteTargetId = $id;
    Flux::modal('confirm-delete')->show();
};

$delete = function () {
    Position::findOrFail($this->deleteTargetId)->delete();
    $this->deleteTargetId = null;
    Flux::modal('confirm-delete')->close();
    Flux::toast(variant: 'danger', text: 'Posisi berhasil dihapus.');
};

?>
<div>
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Data Master: Positions</flux:heading>
                <flux:subheading>Kelola daftar posisi fungsional dan struktural.</flux:subheading>
            </div>

            <flux:modal.trigger name="position-modal">
                <flux:button variant="primary" icon="plus" wire:click="resetForm">Posisi</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Cari posisi..."
            icon="magnifying-glass"
            clearable
        />

        <flux:separator variant="subtle" />

        <flux:table :paginate="$this->positions" striped hoverable>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection"
                    wire:click="sort('name')">
                    Nama Posisi
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection"
                    wire:click="sort('type')">
                    Tipe
                </flux:table.column>
                <flux:table.column>Jumlah Karyawan</flux:table.column>
                <flux:table.column align="right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->positions as $position)
                    <flux:table.row :key="$position->id">
                        <flux:table.cell>{{ $position->name }}</flux:table.cell>

                        <flux:table.cell>
                            <flux:badge
                                variant="pill"
                                color="{{ $position->type === 'Struktural' ? 'blue' : 'zinc' }}">
                                {{ $position->type }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $position->employee_count ?? 0 }}
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom"
                                wire:click="edit('{{ $position->id }}')" />
                            <flux:button variant="danger" size="sm" icon="trash" inset="top bottom"
                                wire:click="confirmDelete('{{ $position->id }}')" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <flux:text class="text-center">Belum ada data yang ditampilkan.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="position-modal" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEdit ? 'Edit Posisi' : 'Tambah Posisi Baru' }}</flux:heading>
                <flux:subheading>Tentukan nama dan tipe posisi dalam organisasi.</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nama Posisi</flux:label>
                <flux:input wire:model="name" placeholder="Cth: Product Consultant Ahli" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Tipe Posisi</flux:label>
                <flux:select wire:model="type">
                    <flux:select.option value="Fungsional">Fungsional</flux:select.option>
                    <flux:select.option value="Struktural">Struktural</flux:select.option>
                </flux:select>
                <flux:error name="type" />
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
                <flux:heading size="lg">Hapus Posisi?</flux:heading>
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
