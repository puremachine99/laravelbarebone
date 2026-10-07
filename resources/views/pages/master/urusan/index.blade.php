<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Models\Urusan;
use Illuminate\Validation\Rule;

usesPagination();

state(['perPage' => 10]);
$updatedPerPage = fn () => $this->resetPage();
layout('layouts.app');

state([
'urusan_id' => null,
'code' =>'',
'urusan' =>'',
'isEdit' => false,
'deleteTargetId' => null,
'search' =>'',
'showUrusanModal' => false,
'showDeleteModal' => false,
]);

state([
'sortBy' =>'code',
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

$urusans = computed(function () {
 $search = $this->search;

 return Urusan::query()
 ->when($search, fn($q) => $q->whereRaw('LOWER(urusan) LIKE ?', ['%' . strtolower($search) .'%'])
 ->orWhereRaw('LOWER(code) LIKE ?', ['%' . strtolower($search) .'%']))
 ->when($this->sortBy, fn($q) => $q->orderBy($this->sortBy, $this->sortDirection))
 ->paginate(\App\Support\PerPage::resolve($this->perPage));
});

$resetForm = function () {
 $this->reset(['urusan_id','code','urusan','isEdit']);
 $this->resetValidation();
};

$save = function () {
 $this->validate([
'code' => [
'required',
'string',
'max:10',
 Rule::unique('urusans','code')->ignore($this->urusan_id),
 ],
'urusan' => ['required','string','max:255'],
 ]);

 $isNew = is_null($this->urusan_id);

 Urusan::updateOrCreate(
 ['id' => $this->urusan_id],
 ['code' => $this->code,'urusan' => $this->urusan]
 );

 $this->resetForm();
 $this->showUrusanModal = false;
 session()->flash('success', $isNew ?'Urusan berhasil ditambahkan!' :'Urusan berhasil diperbarui!');
};

$edit = function (Urusan $urusanModel) {
 $this->resetValidation();
 $this->urusan_id = $urusanModel->id;
 $this->code = $urusanModel->code;
 $this->urusan = $urusanModel->urusan;
 $this->isEdit = true;
 $this->showUrusanModal = true;
};

$confirmDelete = function (string $id) {
 $this->deleteTargetId = $id;
 $this->showDeleteModal = true;
};

$delete = function () {
 Urusan::findOrFail($this->deleteTargetId)->delete();
 $this->deleteTargetId = null;
 $this->showDeleteModal = false;
 session()->flash('error','Urusan berhasil dihapus.');
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
 <h2 class="text-xl font-bold text-base-content">Data Master: Urusan</h2>
 <p class="text-sm text-base-content/50 mt-1">Kelola daftar urusan (kode dan bidang).</p>
 </div>

 <x-button variant="primary" icon="o-plus" size="btn-sm" label="Urusan" wire:click="resetForm(); $set('showUrusanModal', true);" />
 </div>

 <x-input
 wire:model.live.debounce.300ms="search"
 placeholder="Cari kode atau nama urusan..."
 icon="o-magnifying-glass"
 clearable
 />

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th class="cursor-pointer py-3" wire:click="sort('code')">
 <div class="flex items-center gap-1">
 <span>Kode Urusan</span>
 @if ($sortBy ==='code')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="cursor-pointer py-3" wire:click="sort('urusan')">
 <div class="flex items-center gap-1">
 <span>Nama Bidang Urusan</span>
 @if ($sortBy ==='urusan')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->urusans as $item)
 <tr class="border-b border-base-300 hover:bg-base-200">
 <td>
 <div class="badge badge-sm badge-info badge-outline">{{ $item->code }}</div>
 </td>
 <td class="font-semibold text-base-content py-3">{{ $item->urusan }}</td>
 <td class="text-right">
 <div class="flex gap-2 justify-end">
 <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="edit('{{ $item->id }}')" />
 <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete('{{ $item->id }}')" />
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="3" class="text-center text-base-content/50 py-8">
 Belum ada data yang ditampilkan.
 </td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>

 <div class="mt-4">
 <x-pagination-footer :paginator="$this->urusans" />
 </div>
 </div>

 <!-- Modal Form -->
 <x-modal wire:model="showUrusanModal" title="{{ $isEdit ?'Edit Urusan' :'Tambah Urusan Baru' }}" subtitle="Tentukan kode unik dan nama bidang urusan.">
 <form wire:submit.prevent="save" class="space-y-4">
 <x-input label="Kode Urusan" wire:model="code" placeholder="Cth: 01" required />
 <x-input label="Nama Bidang Urusan" wire:model="urusan" placeholder="Cth: Bidang Pendidikan" required />

 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showUrusanModal', false)" class="btn-ghost" />
 <x-button label="Simpan Data" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>

 <!-- Confirmation Modal -->
 <x-modal wire:model="showDeleteModal" title="Hapus Urusan?" subtitle="Tindakan ini tidak bisa dibatalkan.">
 <div class="flex justify-end gap-2 pt-4">
 <x-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
 <x-button label="Ya, Hapus" wire:click="delete" class="btn-error text-white" />
 </div>
 </x-modal>
</div>
