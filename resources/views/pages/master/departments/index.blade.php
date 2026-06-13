<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Models\Department;
use Illuminate\Validation\Rule;

usesPagination();

layout('layouts.app');

state([
'dept_id' => null,
'name' =>'',
'isEdit' => false,
'deleteTargetId' => null,
'showDeptModal' => false,
'showDeleteModal' => false,
]);

state([
'sortBy' =>'name',
'sortDirection' =>'asc',
]);

$sort = function ($column) {
 if ($this->sortBy === $column) {
 $this->sortDirection = $this->sortDirection ==='asc' ?'desc' :'asc';
 } else {
 $this->sortBy = $column;
 $this->sortDirection ='asc';
 }
};

$isSuperAdmin = computed(fn() => auth()->user()?->can('modify-master-data'));

$departments = computed(function () {
 return Department::query()->when($this->sortBy, fn($query) => $query->orderBy($this->sortBy, $this->sortDirection))->paginate(10);
});

$resetForm = function () {
 $this->reset(['dept_id','name','isEdit']);
 $this->resetValidation();
};

$save = function () {
 if (!$this->isSuperAdmin) {
 abort(403,'Unauthorized action.');
 }
 $this->validate([
'name' => ['required','string','max:100', Rule::unique('departments','name')->ignore($this->dept_id)],
 ]);

 $isNew = is_null($this->dept_id);

 Department::updateOrCreate(['id' => $this->dept_id], ['name' => $this->name]);

 $this->resetForm();
 $this->showDeptModal = false;

 session()->flash('success', $isNew ?'Departemen berhasil ditambahkan!' :'Departemen berhasil diperbarui!');
};

$edit = function (Department $department) {
 if (!$this->isSuperAdmin) {
 abort(403,'Unauthorized action.');
 }
 $this->resetValidation();
 $this->dept_id = $department->id;
 $this->name = $department->name;
 $this->isEdit = true;
 $this->showDeptModal = true;
};

$confirmDelete = function (string $id) {
 if (!$this->isSuperAdmin) {
 abort(403,'Unauthorized action.');
 }
 $this->deleteTargetId = $id;
 $this->showDeleteModal = true;
};

$delete = function () {
 if (!$this->isSuperAdmin) {
 abort(403,'Unauthorized action.');
 }
 Department::findOrFail($this->deleteTargetId)->delete();
 $this->deleteTargetId = null;
 $this->showDeleteModal = false;
 session()->flash('error','Departemen berhasil dihapus.');
};

?>
<div class="space-y-6">
 @if (session()->has('success'))
 <div class="alert alert-success shadow-sm">
 <x-icon name="o-check-circle" class="w-5 h-5 text-white" />
 <span class="text-white">{{ session('success') }}</span>
 </div>
 @endif
 @if (session()->has('error'))
 <div class="alert alert-error shadow-sm">
 <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-white" />
 <span class="text-white">{{ session('error') }}</span>
 </div>
 @endif

 <div class="card bg-base-100 border border-base-300 p-6 space-y-6 shadow-sm rounded-2xl">
 <div class="flex items-center justify-between">
 <div>
 <h2 class="text-xl font-bold text-base-content">Data Master: Departments</h2>
 <p class="text-sm text-base-content/50 mt-1">Kelola daftar departemen di dalam organisasi.</p>
 </div>

 @if ($this->isSuperAdmin)
 <x-button variant="primary" icon="o-plus" size="btn-sm" label="Departemen" wire:click="resetForm(); $set('showDeptModal', true);" />
 @endif
 </div>

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th class="cursor-pointer py-3" wire:click="sort('name')">
 <div class="flex items-center gap-1">
 <span>Nama Departemen</span>
 @if ($sortBy ==='name')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->departments as $dept)
 <tr class="border-b border-base-300 hover:bg-base-200">
 <td class="font-semibold text-base-content py-3">{{ $dept->name }}</td>
 <td class="text-right">
 <div class="flex gap-2 justify-end">
 @if ($this->isSuperAdmin)
 <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="edit('{{ $dept->id }}')" />
 <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete('{{ $dept->id }}')" />
 @endif
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="2" class="text-center text-base-content/50 py-8">
 Belum ada data yang ditampilkan.
 </td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>

 <div class="mt-4">
 {{ $this->departments->links() }}
 </div>
 </div>

 <!-- Modal Form -->
 <x-modal wire:model="showDeptModal" title="{{ $isEdit ?'Edit Departemen' :'Tambah Departemen Baru' }}" subtitle="Pastikan nama departemen sesuai dengan struktur organisasi.">
 <form wire:submit.prevent="save" class="space-y-4">
 <x-input label="Nama Departemen" wire:model="name" placeholder="Cth: Finance" required />
 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showDeptModal', false)" class="btn-ghost" />
 <x-button label="Simpan Data" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>

 <!-- Confirmation Modal -->
 <x-modal wire:model="showDeleteModal" title="Hapus Departemen?" subtitle="Tindakan ini tidak bisa dibatalkan.">
 <div class="flex justify-end gap-2 pt-4">
 <x-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
 <x-button label="Ya, Hapus" wire:click="delete" class="btn-error text-white" />
 </div>
 </x-modal>
</div>
