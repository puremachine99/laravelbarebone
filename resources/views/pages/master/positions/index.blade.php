<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Models\Position;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

usesPagination();
layout('layouts.app');

state([
'position_id' => null,
'name' =>'',
'type' =>'Fungsional',
'isEdit' => false,
'deleteTargetId' => null,
'search' =>'',
'showPositionModal' => false,
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

$updatedSearch = function () {
 $this->resetPage();
};

$positions = computed(function () {
 $search = $this->search;

 return Position::query()
 ->addSelect([
'employee_count' => DB::table('employees')
 ->selectRaw('count(*)')
 ->whereColumn('position_fungsional_id','positions.id')
 ->orWhereColumn('position_struktural_id','positions.id'),
 ])
 ->when($search, fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) .'%']))
 ->when($this->sortBy, fn($q) => $q->orderBy($this->sortBy, $this->sortDirection))
 ->paginate(10);
});

$resetForm = function () {
 $this->reset(['position_id','name','type','isEdit']);
 $this->resetValidation();
};

$save = function () {
 $this->validate([
'name' => [
'required',
'string',
'max:100',
 Rule::unique('positions','name')->ignore($this->position_id),
 ],
'type' => ['required','in:Fungsional,Struktural'],
 ]);

 $isNew = is_null($this->position_id);

 Position::updateOrCreate(
 ['id' => $this->position_id],
 ['name' => $this->name,'type' => $this->type]
 );

 $this->resetForm();
 $this->showPositionModal = false;
 session()->flash('success', $isNew ?'Posisi berhasil ditambahkan!' :'Posisi berhasil diperbarui!');
};

$edit = function (Position $position) {
 $this->resetValidation();
 $this->position_id = $position->id;
 $this->name = $position->name;
 $this->type = $position->type;
 $this->isEdit = true;
 $this->showPositionModal = true;
};

$confirmDelete = function (string $id) {
 $this->deleteTargetId = $id;
 $this->showDeleteModal = true;
};

$delete = function () {
 Position::findOrFail($this->deleteTargetId)->delete();
 $this->deleteTargetId = null;
 $this->showDeleteModal = false;
 session()->flash('error','Posisi berhasil dihapus.');
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
 <h2 class="text-xl font-bold text-base-content">Data Master: Positions</h2>
 <p class="text-sm text-base-content/50 mt-1">Kelola daftar posisi fungsional dan struktural.</p>
 </div>

 <x-button variant="primary" icon="o-plus" size="btn-sm" label="Posisi" wire:click="resetForm(); $set('showPositionModal', true);" />
 </div>

 <x-input
 wire:model.live.debounce.300ms="search"
 placeholder="Cari posisi..."
 icon="o-magnifying-glass"
 clearable
 />

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th class="cursor-pointer py-3" wire:click="sort('name')">
 <div class="flex items-center gap-1">
 <span>Nama Posisi</span>
 @if ($sortBy ==='name')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="cursor-pointer py-3" wire:click="sort('type')">
 <div class="flex items-center gap-1">
 <span>Tipe</span>
 @if ($sortBy ==='type')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th>Jumlah Karyawan</th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->positions as $position)
 <tr class="border-b border-base-300 hover:bg-base-200">
 <td class="font-semibold text-base-content py-3">{{ $position->name }}</td>
 <td>
 <div class="badge badge-sm badge-outline {{ $position->type ==='Struktural' ?'badge-info' :'badge-ghost' }}">
 {{ $position->type }}
 </div>
 </td>
 <td>{{ $position->employee_count ?? 0 }}</td>
 <td class="text-right">
 <div class="flex gap-2 justify-end">
 <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="edit('{{ $position->id }}')" />
 <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete('{{ $position->id }}')" />
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="4" class="text-center text-base-content/50 py-8">
 Belum ada data yang ditampilkan.
 </td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>

 <div class="mt-4">
 {{ $this->positions->links() }}
 </div>
 </div>

 <!-- Modal Form -->
 <x-modal wire:model="showPositionModal" title="{{ $isEdit ?'Edit Posisi' :'Tambah Posisi Baru' }}" subtitle="Tentukan nama dan tipe posisi dalam organisasi.">
 <form wire:submit.prevent="save" class="space-y-4">
 <x-input label="Nama Posisi" wire:model="name" placeholder="Cth: Product Consultant Ahli" required />
 
 @php
 $typeOptions = [
 ['id' =>'Fungsional','name' =>'Fungsional'],
 ['id' =>'Struktural','name' =>'Struktural'],
 ];
 @endphp
 <x-select label="Tipe Posisi" wire:model="type" :options="$typeOptions" required />

 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showPositionModal', false)" class="btn-ghost" />
 <x-button label="Simpan Data" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>

 <!-- Confirmation Modal -->
 <x-modal wire:model="showDeleteModal" title="Hapus Posisi?" subtitle="Tindakan ini tidak bisa dibatalkan.">
 <div class="flex justify-end gap-2 pt-4">
 <x-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
 <x-button label="Ya, Hapus" wire:click="delete" class="btn-error text-white" />
 </div>
 </x-modal>
</div>
