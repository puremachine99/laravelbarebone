<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Models\ContactPosition;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

usesPagination();
layout('layouts.app');

state([
'contact_position_id' => null,
'kelompok_jabatan' =>'',
'eselon' =>'',
'name' =>'',
'tingkat' =>'',
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

$contactPositions = computed(function () {
 $search = $this->search;

 return ContactPosition::query()
 ->addSelect([
'contacts_count' => DB::table('pmo_contacts')
 ->selectRaw('count(*)')
 ->whereColumn('contact_position_id','pmo_contact_positions.id'),
 ])
 ->when($search, function ($q) use ($search) {
 $q->where(function ($query) use ($search) {
 $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) .'%'])
 ->orWhereRaw('LOWER(kelompok_jabatan) LIKE ?', ['%' . strtolower($search) .'%'])
 ->orWhereRaw('LOWER(eselon) LIKE ?', ['%' . strtolower($search) .'%'])
 ->orWhereRaw('LOWER(tingkat) LIKE ?', ['%' . strtolower($search) .'%']);
 });
 })
 ->when($this->sortBy, fn($q) => $q->orderBy($this->sortBy, $this->sortDirection))
 ->paginate(10);
});

$resetForm = function () {
 $this->reset(['contact_position_id','kelompok_jabatan','eselon','name','tingkat','isEdit']);
 $this->resetValidation();
};

$save = function () {
 $this->validate([
'name' => [
'required',
'string',
'max:255',
 Rule::unique('contact_positions','name')->ignore($this->contact_position_id),
 ],
'kelompok_jabatan' => ['nullable','string','max:255'],
'eselon' => ['nullable','string','max:255'],
'tingkat' => ['nullable','string','max:255'],
 ]);

 $isNew = is_null($this->contact_position_id);

 ContactPosition::updateOrCreate(
 ['id' => $this->contact_position_id],
 [
'name' => $this->name,
'kelompok_jabatan' => $this->kelompok_jabatan,
'eselon' => $this->eselon,
'tingkat' => $this->tingkat,
 ]
 );

 $this->resetForm();
 $this->showPositionModal = false;
 session()->flash('success', $isNew ?'Jabatan Kontak berhasil ditambahkan!' :'Jabatan Kontak berhasil diperbarui!');
};

$edit = function (ContactPosition $position) {
 $this->resetValidation();
 $this->contact_position_id = $position->id;
 $this->name = $position->name;
 $this->kelompok_jabatan = $position->kelompok_jabatan;
 $this->eselon = $position->eselon;
 $this->tingkat = $position->tingkat;
 $this->isEdit = true;
 $this->showPositionModal = true;
};

$confirmDelete = function (string $id) {
 $this->deleteTargetId = $id;
 $this->showDeleteModal = true;
};

$delete = function () {
 ContactPosition::findOrFail($this->deleteTargetId)->delete();
 $this->deleteTargetId = null;
 $this->showDeleteModal = false;
 session()->flash('error','Jabatan Kontak berhasil dihapus.');
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
 <h2 class="text-xl font-bold text-base-content">Data Master: Jabatan Kontak</h2>
 <p class="text-sm text-base-content/50 mt-1">Kelola daftar nomenklatur jabatan kontak mitra, kelompok jabatan, eselon, dan tingkat instansi.</p>
 </div>

 <x-button variant="primary" icon="o-plus" size="btn-sm" label="Tambah Jabatan" wire:click="resetForm(); $set('showPositionModal', true);" />
 </div>

 <x-input
 wire:model.live.debounce.300ms="search"
 placeholder="Cari jabatan..."
 icon="o-magnifying-glass"
 clearable
 />

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th class="cursor-pointer py-3" wire:click="sort('name')">
 <div class="flex items-center gap-1">
 <span>Nomenklatur Jabatan</span>
 @if ($sortBy ==='name')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="cursor-pointer py-3" wire:click="sort('kelompok_jabatan')">
 <div class="flex items-center gap-1">
 <span>Kelompok Jabatan</span>
 @if ($sortBy ==='kelompok_jabatan')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="cursor-pointer py-3" wire:click="sort('eselon')">
 <div class="flex items-center gap-1">
 <span>Jenjang / Eselon</span>
 @if ($sortBy ==='eselon')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="cursor-pointer py-3" wire:click="sort('tingkat')">
 <div class="flex items-center gap-1">
 <span>Tingkat / Keterangan</span>
 @if ($sortBy ==='tingkat')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th>Jumlah Kontak</th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->contactPositions as $pos)
 <tr class="border-b border-base-300 hover:bg-base-200">
 <td class="font-semibold text-base-content py-3">{{ $pos->name }}</td>
 <td>{{ $pos->kelompok_jabatan ??'-' }}</td>
 <td>{{ $pos->eselon ??'-' }}</td>
 <td>{{ $pos->tingkat ??'-' }}</td>
 <td>{{ $pos->contacts_count ?? 0 }}</td>
 <td class="text-right">
 <div class="flex gap-2 justify-end">
 <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="edit('{{ $pos->id }}')" />
 <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete('{{ $pos->id }}')" />
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="6" class="text-center text-base-content/50 py-8">
 Belum ada data yang ditampilkan.
 </td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>

 <div class="mt-4">
 {{ $this->contactPositions->links() }}
 </div>
 </div>

 <!-- Modal Form -->
 <x-modal wire:model="showPositionModal" title="{{ $isEdit ?'Edit Jabatan Kontak' :'Tambah Jabatan Kontak Baru' }}" subtitle="Tentukan detail kelompok jabatan, eselon, nomenklatur, dan tingkat instansi." class="md:w-96">
 <form wire:submit.prevent="save" class="space-y-4">
 <x-input label="Kelompok Jabatan" wire:model="kelompok_jabatan" placeholder="Cth: Jabatan Pimpinan Tinggi (JPT)" />
 <x-input label="Jenjang / Eselon" wire:model="eselon" placeholder="Cth: JPT Utama (Eselon I.a)" />
 <x-input label="Nomenklatur Jabatan" wire:model="name" placeholder="Cth: Kepala Dinas Provinsi" required />
 <x-input label="Tingkat / Keterangan" wire:model="tingkat" placeholder="Cth: Provinsi" />

 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showPositionModal', false)" class="btn-ghost" />
 <x-button label="Simpan Data" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>

 <!-- Confirmation Modal -->
 <x-modal wire:model="showDeleteModal" title="Hapus Jabatan Kontak?" subtitle="Tindakan ini tidak bisa dibatalkan.">
 <div class="flex justify-end gap-2 pt-4">
 <x-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
 <x-button label="Ya, Hapus" wire:click="delete" class="btn-error text-white" />
 </div>
 </x-modal>
</div>
