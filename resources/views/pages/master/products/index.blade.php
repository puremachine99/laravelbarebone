<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Models\Division;
use App\Models\ServiceCenter;
use App\Models\ProductCategory;
use App\Models\Output;
use Illuminate\Validation\Rule;

usesPagination();
layout('layouts.app');

state([
'search' =>'',
'filterDivision' =>'',
'filterServiceCenter' =>'',
]);

state([
'sortBy' =>'generated_code',
'sortDirection' =>'asc',
]);

state([
'product_id' => null,
'division_id' =>'',
'service_center_id' =>'',
'category_code' =>'',
'output_name' =>'',
'pelaksana' =>'',
'status' => true,
'isEdit' => false,
'deleteTargetId' => null,
'showProductModal' => false,
'showDeleteModal' => false,
]);

$divisions = computed(fn() => Division::orderBy('name')->get());

$filterServiceCenterOptions = computed(function () {
 return ServiceCenter::when($this->filterDivision, fn($q) => $q->where('division_id', $this->filterDivision))
 ->orderBy('name')
 ->get();
});

$formServiceCenterOptions = computed(function () {
 if (!$this->division_id) {
 return collect();
 }
 return ServiceCenter::where('division_id', $this->division_id)
 ->orderBy('name')
 ->get();
});

$categories = computed(fn() => 
 ProductCategory::orderBy('code')
 ->get()
 ->map(fn($cat) => [
'id' => $cat->code,
'name' => $cat->code .' -' . $cat->name
 ])
);

$products = computed(function () {
 $search = $this->search;
 $filterDivision = $this->filterDivision;
 $filterServiceCenter = $this->filterServiceCenter;

 return Output::query()
 ->with(['division','serviceCenter'])
 ->when($search, fn($q) => $q->whereRaw('LOWER(output_name) LIKE ?', ['%' . strtolower($search) .'%'])
 ->orWhereRaw('LOWER(generated_code) LIKE ?', ['%' . strtolower($search) .'%']))
 ->when($filterDivision, fn($q) => $q->where('division_id', $filterDivision))
 ->when($filterServiceCenter, fn($q) => $q->where('service_center_id', $filterServiceCenter))
 ->orderBy($this->sortBy, $this->sortDirection)
 ->paginate(10);
});

$previewCode = computed(function () {
 if (!$this->division_id || !$this->service_center_id || !$this->category_code) {
 return'Akan di-generate otomatis';
 }

 $div = Division::find($this->division_id);
 $sc = ServiceCenter::find($this->service_center_id);

 if (!$div || !$sc) {
 return'Akan di-generate otomatis';
 }

 $existing = Output::where('division_id', $this->division_id)
 ->where('service_center_id', $this->service_center_id)
 ->where('category_code', $this->category_code)
 ->when($this->product_id, fn($q) => $q->where('id','!=', $this->product_id))
 ->pluck('sequence')
 ->toArray();

 $seq = 1;
 while (in_array($seq, $existing)) {
 $seq++;
 }

 return sprintf('%s.%s.%s.%d', $div->code, $sc->code, $this->category_code, $seq);
});

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

$updatedFilterDivision = function () {
 $this->filterServiceCenter ='';
 $this->resetPage();
};

$updatedFilterServiceCenter = function () {
 $this->resetPage();
};

$updatedDivisionId = function () {
 $this->service_center_id ='';
};

$resetForm = function () {
 $this->reset(['product_id','division_id','service_center_id','category_code','output_name','pelaksana','status','isEdit']);
 $this->resetValidation();
};

$save = function () {
 $this->validate([
'division_id' => ['required','exists:pmo_divisions,id'],
'service_center_id' => ['required','exists:pmo_service_centers,id'],
'category_code' => ['required','exists:pmo_product_categories,code'],
'output_name' => ['required','string','max:255'],
'pelaksana' => ['required','string','max:255'],
'status' => ['required','boolean'],
 ]);

 $isNew = is_null($this->product_id);

 Output::updateOrCreate(
 ['id' => $this->product_id],
 [
'division_id' => $this->division_id,
'service_center_id' => $this->service_center_id,
'category_code' => $this->category_code,
'output_name' => $this->output_name,
'pelaksana' => $this->pelaksana,
'status' => $this->status,
 ]
 );

 $this->resetForm();
 $this->showProductModal = false;
 session()->flash('success', $isNew ?'Output berhasil ditambahkan!' :'Output berhasil diperbarui!');
};

$edit = function (Output $output) {
 $this->resetValidation();
 $this->product_id = $output->id;
 $this->division_id = $output->division_id;
 $this->service_center_id = $output->service_center_id;
 $this->category_code = $output->category_code;
 $this->output_name = $output->output_name;
 $this->pelaksana = $output->pelaksana;
 $this->status = $output->status;
 $this->isEdit = true;
 $this->showProductModal = true;
};

$confirmDelete = function (string $id) {
 $this->deleteTargetId = $id;
 $this->showDeleteModal = true;
};

$delete = function () {
 Output::findOrFail($this->deleteTargetId)->delete();
 $this->deleteTargetId = null;
 $this->showDeleteModal = false;
 session()->flash('error','Output berhasil dihapus.');
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
 <h2 class="text-xl font-bold text-base-content">Data Master: Output / Produk</h2>
 <p class="text-sm text-base-content/50 mt-1">Kelola daftar output divisi, pusat layanan, kategori produk, dan kode output otomatis.</p>
 </div>

 <x-button variant="primary" icon="o-plus" size="btn-sm" label="Output" wire:click="resetForm(); $set('showProductModal', true);" />
 </div>

 <div class="flex gap-3 flex-wrap md:flex-nowrap">
 <div class="flex-1 min-w-[200px]">
 <x-input
 wire:model.live.debounce.300ms="search"
 placeholder="Cari kode atau nama output..."
 icon="o-magnifying-glass"
 clearable
 />
 </div>
 <div class="w-64">
 <x-select wire:model.live="filterDivision" :options="$this->divisions" placeholder="Semua Divisi" placeholder-value="" />
 </div>
 <div class="w-64">
 <x-select wire:model.live="filterServiceCenter" :options="$this->filterServiceCenterOptions" placeholder="Semua Pusat Layanan" placeholder-value="" />
 </div>
 </div>

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th class="cursor-pointer py-3" wire:click="sort('generated_code')">
 <div class="flex items-center gap-1">
 <span>Kode Output</span>
 @if ($sortBy ==='generated_code')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="cursor-pointer py-3" wire:click="sort('output_name')">
 <div class="flex items-center gap-1">
 <span>Nama Output / Produk</span>
 @if ($sortBy ==='output_name')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th>Divisi & Pusat Layanan</th>
 <th>Pelaksana</th>
 <th>Status</th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->products as $item)
 <tr class="border-b border-base-300 hover:bg-base-200">
 <td>
 <div class="badge badge-sm badge-info badge-outline">{{ $item->generated_code }}</div>
 </td>
 <td class="font-semibold text-base-content py-3">{{ $item->output_name }}</td>
 <td>
 <div>{{ $item->division?->name }}</div>
 <span class="text-xs text-base-content/40 block mt-0.5">{{ $item->serviceCenter?->name }}</span>
 </td>
 <td>{{ $item->pelaksana ??'-' }}</td>
 <td>
 <div class="badge badge-sm badge-outline {{ $item->status ?'badge-success' :'badge-error' }}">
 {{ $item->status ?'Aktif' :'Non Aktif' }}
 </div>
 </td>
 <td class="text-right">
 <div class="flex gap-2 justify-end">
 <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="edit('{{ $item->id }}')" />
 <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete('{{ $item->id }}')" />
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
 {{ $this->products->links() }}
 </div>
 </div>

 <!-- Modal Form -->
 <x-modal wire:model="showProductModal" title="{{ $isEdit ?'Edit Data Output' :'Tambah Output Baru' }}" subtitle="Masukkan informasi divisi, pusat layanan, kategori, dan detail output." class="md:w-[750px]">
 <form wire:submit.prevent="save" class="space-y-4">
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
 <x-select label="Divisi" wire:model.live="division_id" :options="$this->divisions" placeholder="Pilih Divisi..." placeholder-value="" required />
 <x-select label="Pusat Layanan" wire:model.live="service_center_id" :options="$this->formServiceCenterOptions" placeholder="Pilih Layanan..." placeholder-value="" required />
 </div>

 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
 <x-select label="Kode Produk / Kategori" wire:model.live="category_code" :options="$this->categories" placeholder="Pilih Kategori..." placeholder-value="" required />
 
 @php
 $statusOpts = [
 ['id' =>'1','name' =>'Aktif'],
 ['id' =>'0','name' =>'Non Aktif'],
 ];
 @endphp
 <x-select label="Status Output" wire:model="status" :options="$statusOpts" required />
 </div>

 <x-input label="Output" wire:model="output_name" placeholder="Insert Output Name" required />

 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
 <x-input label="Pelaksana" wire:model="pelaksana" placeholder="Insert Pelaksana" required />
 <div>
 <label class="label"><span class="label-text font-semibold">Kode Output (Pratinjau)</span></label>
 <input type="text" readonly value="{{ $this->previewCode }}" class="input input-bordered w-full bg-base-200 font-mono text-blue-600 dark:text-blue-400" />
 </div>
 </div>

 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showProductModal', false)" class="btn-ghost" />
 <x-button label="Simpan Data" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>

 <!-- Confirmation Modal -->
 <x-modal wire:model="showDeleteModal" title="Hapus Output?" subtitle="Tindakan ini tidak bisa dibatalkan.">
 <div class="flex justify-end gap-2 pt-4">
 <x-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
 <x-button label="Ya, Hapus" wire:click="delete" class="btn-error text-white" />
 </div>
 </x-modal>
</div>
