<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use Flux\Flux;
use App\Models\Department;
use Illuminate\Validation\Rule;

usesPagination();

layout('layouts.app');

state([
    'dept_id' => null,
    'name' => '',
    'isEdit' => false,
    'deleteTargetId' => null,
]);

state([
    'sortBy' => 'name',
    'sortDirection' => 'asc',
]);

$sort = function ($column) {
    if ($this->sortBy === $column) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }
};

$departments = computed(function () {
    return Department::query()->when($this->sortBy, fn($query) => $query->orderBy($this->sortBy, $this->sortDirection))->paginate(10);
});
$resetForm = function () {
    $this->reset(['dept_id', 'name', 'isEdit']);
    $this->resetValidation();
};

$save = function () {
    $this->validate([
        'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($this->dept_id)],
    ]);

    $isNew = is_null($this->dept_id);

    Department::updateOrCreate(['id' => $this->dept_id], ['name' => $this->name]);

    $this->resetForm();
    Flux::modal('dept-modal')->close();

    Flux::toast(variant: 'success', heading: $isNew ? 'Departemen ditambahkan' : 'Departemen diperbarui', text: $isNew ? 'Data baru berhasil disimpan.' : 'Perubahan berhasil disimpan.');
};

$edit = function (Department $department) {
    $this->resetValidation();
    $this->dept_id = $department->id;
    $this->name = $department->name;
    $this->isEdit = true;
    Flux::modal('dept-modal')->show();
};

$confirmDelete = function (string $id) {
    $this->deleteTargetId = $id;
    Flux::modal('confirm-delete')->show();
};

$delete = function () {
    Department::findOrFail($this->deleteTargetId)->delete();
    $this->deleteTargetId = null;
    Flux::modal('confirm-delete')->close();
    Flux::toast(variant: 'danger', text: 'Departemen berhasil dihapus.');
};

?>
<div>
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Data Master: Departments</flux:heading>
                <flux:subheading>Kelola daftar departemen di dalam organisasi.</flux:subheading>
            </div>

            <flux:modal.trigger name="dept-modal">
                <flux:button variant="primary" icon="plus" wire:click="resetForm">Departemen</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:separator variant="subtle" />

        <flux:table :paginate="$this->departments" striped hoverable>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection"
                    wire:click="sort('name')">
                    Nama Departemen
                </flux:table.column>
                <flux:table.column align="right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->departments as $dept)
                    <flux:table.row :key="$dept->id">
                        <flux:table.cell>{{ $dept->name }}</flux:table.cell>

                        <flux:table.cell align="right">
                            <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom"
                                wire:click="edit('{{ $dept->id }}')" />

                            <flux:button variant="danger" size="sm" icon="trash" inset="top bottom"
                                wire:click="confirmDelete('{{ $dept->id }}')" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2">
                            <flux:text class="text-center">Belum ada data yang ditampilkan.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="dept-modal" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEdit ? 'Edit Departemen' : 'Tambah Departemen Baru' }}</flux:heading>
                <flux:subheading>Pastikan nama departemen sesuai dengan struktur organisasi.</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nama Departemen</flux:label>
                <flux:input wire:model="name" placeholder="Cth: Finance" required />
                <flux:error name="name" />
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
                <flux:heading size="lg">Hapus Departemen?</flux:heading>
                <flux:text class="mt-1">Tindakan ini tidak bisa dibatalkan.</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">
                    Ya, Hapus
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
