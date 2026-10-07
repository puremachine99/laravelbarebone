<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Models\Region;
use App\Models\Province;
use App\Models\Regency;
use Illuminate\Validation\Rule;

usesPagination();
layout('layouts.app');

state(['activeTab' =>'regions']);

state([
'region_id' => null,
'region_name' =>'',
'region_code' =>'',
'isEditRegion' => false,
'showRegionModal' => false,
]);

state(['sortRegionBy' =>'name','sortRegionDir' =>'asc']);

$regions = computed(fn() =>
 Region::orderBy($this->sortRegionBy, $this->sortRegionDir)->paginate(10, pageName:'regionsPage')
);

$sortRegion = function ($col) {
 if ($this->sortRegionBy === $col) {
 $this->sortRegionDir = $this->sortRegionDir ==='asc' ?'desc' :'asc';
 } else {
 $this->sortRegionBy = $col;
 $this->sortRegionDir ='asc';
 }
};

$sortProvince = function ($col) {
 if ($this->sortProvinceBy === $col) {
 $this->sortProvinceDir = $this->sortProvinceDir ==='asc' ?'desc' :'asc';
 } else {
 $this->sortProvinceBy = $col;
 $this->sortProvinceDir ='asc';
 }
};

$resetRegionForm = fn() => $this->reset(['region_id','region_name','region_code','isEditRegion'])
 + $this->resetValidation();

$saveRegion = function () {
 $this->validate([
'region_name' => ['required','string','max:100', Rule::unique('regions','name')->ignore($this->region_id)],
'region_code' => ['nullable','string','max:20', Rule::unique('regions','code')->ignore($this->region_id)],
 ]);

 $isNew = is_null($this->region_id);
 Region::updateOrCreate(['id' => $this->region_id], ['name' => $this->region_name,'code' => $this->region_code ?: null]);

 $this->resetRegionForm();
 $this->showRegionModal = false;
 session()->flash('success', $isNew ?'Region berhasil ditambahkan!' :'Region berhasil diperbarui!');
};

$editRegion = function (Region $region) {
 $this->resetValidation();
 $this->region_id = $region->id;
 $this->region_name = $region->name;
 $this->region_code = $region->code ??'';
 $this->isEditRegion = true;
 $this->showRegionModal = true;
};

$deleteRegion = function (string $id) {
 Region::findOrFail($id)->delete();
 session()->flash('error','Region berhasil dihapus.');
};

state([
'province_id' => null,
'province_name' =>'',
'province_code' =>'',
'province_region_id'=>'',
'isEditProvince' => false,
'showProvinceModal' => false,
]);

state(['sortProvinceBy' =>'name','sortProvinceDir' =>'asc']);

$regionOptions = computed(fn() => Region::orderBy('name')->get());

$provinces = computed(fn() =>
 Province::with('region')
 ->withCount(['regencies as regencies_count' => fn($q) => $q->where('type','!=','Provinsi')])
 ->orderBy($this->sortProvinceBy, $this->sortProvinceDir)
 ->paginate(10, pageName:'provincesPage')
);

$resetProvinceForm = fn() => $this->reset(['province_id','province_name','province_code','province_region_id','isEditProvince'])
 + $this->resetValidation();

$saveProvince = function () {
 $this->validate([
'province_name' => ['required','string','max:150', Rule::unique('provinces','name')->ignore($this->province_id)],
'province_code' => ['nullable','string','max:10', Rule::unique('provinces','code')->ignore($this->province_id)],
'province_region_id' => ['required','exists:regions,id'],
 ]);

 $isNew = is_null($this->province_id);
 Province::updateOrCreate(
 ['id' => $this->province_id],
 ['name' => $this->province_name,'code' => $this->province_code ?: null,'region_id' => $this->province_region_id]
 );

 $this->resetProvinceForm();
 $this->showProvinceModal = false;
 session()->flash('success', $isNew ?'Provinsi berhasil ditambahkan!' :'Provinsi berhasil diperbarui!');
};

$editProvince = function (Province $province) {
 $this->resetValidation();
 $this->province_id = $province->id;
 $this->province_name = $province->name;
 $this->province_code = $province->code ??'';
 $this->province_region_id = $province->region_id;
 $this->isEditProvince = true;
 $this->showProvinceModal = true;
};

$deleteProvince = function (string $id) {
 Province::findOrFail($id)->delete();
 session()->flash('error','Provinsi berhasil dihapus.');
};

state([
'regency_id' => null,
'regency_name' =>'',
'regency_code' =>'',
'regency_type' =>'Kabupaten',
'regency_province_id' =>'',
'isEditRegency' => false,
'searchRegency' =>'',
'filterProvinceId' =>'',
'showRegencyModal' => false,
]);

state(['sortRegencyBy' =>'name','sortRegencyDir' =>'asc']);

$provinceOptions = computed(fn() => Province::orderBy('name')->get());

$regencies = computed(function () {
 $search = $this->searchRegency;
 return Regency::with(['province','region'])
 ->when($search, fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) .'%']))
 ->when($this->filterProvinceId, fn($q) => $q->where('province_id', $this->filterProvinceId))
 ->orderBy($this->sortRegencyBy, $this->sortRegencyDir)
 ->paginate(10, pageName:'regenciesPage');
});

$sortRegency = function ($col) {
 if ($this->sortRegencyBy === $col) {
 $this->sortRegencyDir = $this->sortRegencyDir ==='asc' ?'desc' :'asc';
 } else {
 $this->sortRegencyBy = $col;
 $this->sortRegencyDir ='asc';
 }
};

$updatedSearchRegency = fn() => $this->resetPage('regenciesPage');

$resetRegencyForm = fn() => $this->reset(['regency_id','regency_name','regency_code','regency_type','regency_province_id','isEditRegency'])
 + $this->resetValidation();

$saveRegency = function () {
 $this->validate([
'regency_name' => ['required','string','max:150'],
'regency_code' => ['nullable','string','max:10', Rule::unique('regencies','code')->ignore($this->regency_id)],
'regency_type' => ['required','in:Kabupaten,Kota,Lembaga'],
'regency_province_id' => ['required','exists:provinces,id'],
 ]);

 $isNew = is_null($this->regency_id);
 Regency::updateOrCreate(
 ['id' => $this->regency_id],
 [
'name' => $this->regency_name,
'code' => $this->regency_code ?: null,
'type' => $this->regency_type,
'province_id' => $this->regency_province_id,
 ]
 );

 $this->resetRegencyForm();
 $this->showRegencyModal = false;
 session()->flash('success', $isNew ?'Kabupaten/Kota berhasil ditambahkan!' :'Kabupaten/Kota berhasil diperbarui!');
};

$editRegency = function (Regency $regency) {
 $this->resetValidation();
 $this->regency_id = $regency->id;
 $this->regency_name = $regency->name;
 $this->regency_code = $regency->code ??'';
 $this->regency_type = $regency->type;
 $this->regency_province_id = $regency->province_id;
 $this->isEditRegency = true;
 $this->showRegencyModal = true;
};

$deleteRegency = function (string $id) {
 Regency::findOrFail($id)->delete();
 session()->flash('error','Kabupaten/Kota berhasil dihapus.');
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
 <div>
 <h2 class="text-xl font-bold text-base-content">Data Master: Lokasi</h2>
 <p class="text-sm text-base-content/50 mt-1">Kelola data Region, Provinsi, dan Kabupaten/Kota.</p>
 </div>

 <x-tabs wire:model="activeTab">
 <x-tab name="regions" label="Regions" icon="o-map">
 <div class="space-y-4 pt-4">
 <div class="flex justify-end">
 <x-button variant="primary" icon="o-plus" size="btn-sm" label="Region" wire:click="resetRegionForm(); $set('showRegionModal', true);" />
 </div>

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th class="cursor-pointer py-3" wire:click="sortRegion('code')">
 <div class="flex items-center gap-1">
 <span>Kode</span>
 @if ($sortRegionBy ==='code')
 <x-icon name="{{ $sortRegionDir ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="cursor-pointer py-3" wire:click="sortRegion('name')">
 <div class="flex items-center gap-1">
 <span>Nama Region</span>
 @if ($sortRegionBy ==='name')
 <x-icon name="{{ $sortRegionDir ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->regions as $region)
 <tr class="border-b border-base-300 hover:bg-base-200">
 <td>
 @if ($region->code)
 <div class="badge badge-sm badge-neutral">{{ $region->code }}</div>
 @else - @endif
 </td>
 <td class="font-semibold text-base-content">{{ $region->name }}</td>
 <td class="text-right">
 <div class="flex gap-2 justify-end">
 <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="editRegion('{{ $region->id }}')" />
 <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:confirm="Yakin hapus region ini?" wire:click="deleteRegion('{{ $region->id }}')" />
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="3" class="text-center text-base-content/50 py-8">Belum ada data.</td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>
 <div class="mt-4">
 {{ $this->regions->links() }}
 </div>
 </div>
 </x-tab>

 <x-tab name="provinces" label="Provinces" icon="o-building-library">
 <div class="space-y-4 pt-4">
 <div class="flex justify-end">
 <x-button variant="primary" icon="o-plus" size="btn-sm" label="Provinsi" wire:click="resetProvinceForm(); $set('showProvinceModal', true);" />
 </div>

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th>Kode</th>
 <th class="cursor-pointer py-3" wire:click="sortProvince('name')">
 <div class="flex items-center gap-1">
 <span>Nama Provinsi</span>
 @if ($sortProvinceBy ==='name')
 <x-icon name="{{ $sortProvinceDir ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th>Region</th>
 <th class="text-right">Kab/Kota</th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->provinces as $province)
 <tr class="border-b border-base-300 hover:bg-base-200">
 <td>
 @if ($province->code)
 <div class="badge badge-sm badge-neutral">{{ $province->code }}</div>
 @else - @endif
 </td>
 <td class="font-semibold text-base-content">{{ $province->name }}</td>
 <td>
 <div class="badge badge-sm badge-info text-white">{{ $province->region?->name ??'-' }}</div>
 </td>
 <td class="text-right">{{ $province->regencies_count }}</td>
 <td class="text-right">
 <div class="flex gap-2 justify-end">
 <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="editProvince('{{ $province->id }}')" />
 <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:confirm="Yakin hapus provinsi ini?" wire:click="deleteProvince('{{ $province->id }}')" />
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="5" class="text-center text-base-content/50 py-8">Belum ada data.</td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>
 <div class="mt-4">
 {{ $this->provinces->links() }}
 </div>
 </div>
 </x-tab>

 <x-tab name="regencies" label="Kabupaten / Kota" icon="o-building-office-2">
 <div class="space-y-4 pt-4">
 <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
 <div class="flex-1">
 <x-input wire:model.live.debounce.300ms="searchRegency" placeholder="Cari kabupaten/kota..." icon="o-magnifying-glass" clearable />
 </div>
 <div class="w-full sm:w-48">
 <x-select wire:model.live="filterProvinceId" :options="$this->provinceOptions" placeholder="Semua Provinsi" placeholder-value="" />
 </div>
 <x-button variant="primary" icon="o-plus" size="btn-sm" label="Tambah" wire:click="resetRegencyForm(); $set('showRegencyModal', true);" />
 </div>

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th>Kode</th>
 <th class="cursor-pointer py-3" wire:click="sortRegency('name')">
 <div class="flex items-center gap-1">
 <span>Nama</span>
 @if ($sortRegencyBy ==='name')
 <x-icon name="{{ $sortRegencyDir ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th>Tipe</th>
 <th>Provinsi</th>
 <th>Region</th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->regencies as $regency)
 <tr class="border-b border-base-300 hover:bg-base-200">
 <td>
 @if ($regency->code)
 <div class="badge badge-sm badge-neutral">{{ $regency->code }}</div>
 @else - @endif
 </td>
 <td class="font-semibold text-base-content">{{ $regency->name }}</td>
 <td>
 <div class="badge badge-sm badge-outline {{ match($regency->type) {'Kota' =>'badge-info','Lembaga' =>'badge-secondary', default =>'badge-ghost' } }}">
 {{ $regency->type }}
 </div>
 </td>
 <td>{{ $regency->province?->name ??'-' }}</td>
 <td>
 <div class="badge badge-sm badge-info text-white">{{ $regency->region?->name ??'-' }}</div>
 </td>
 <td class="text-right">
 <div class="flex gap-2 justify-end">
 <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="editRegency('{{ $regency->id }}')" />
 <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:confirm="Yakin hapus data ini?" wire:click="deleteRegency('{{ $regency->id }}')" />
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="6" class="text-center text-base-content/50 py-8">Belum ada data.</td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>
 <div class="mt-4">
 {{ $this->regencies->links() }}
 </div>
 </div>
 </x-tab>
 </x-tabs>
 </div>

 <!-- Region Form Modal -->
 <x-modal wire:model="showRegionModal" title="{{ $isEditRegion ?'Edit Region' :'Tambah Region' }}">
 <form wire:submit.prevent="saveRegion" class="space-y-4">
 <x-input label="Nama Region" wire:model="region_name" placeholder="Cth: Region 1" required />
 <x-input label="Kode (opsional)" wire:model="region_code" placeholder="Cth: REG-1" />
 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showRegionModal', false)" class="btn-ghost" />
 <x-button label="Simpan" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>

 <!-- Province Form Modal -->
 <x-modal wire:model="showProvinceModal" title="{{ $isEditProvince ?'Edit Provinsi' :'Tambah Provinsi' }}">
 <form wire:submit.prevent="saveProvince" class="space-y-4">
 <x-input label="Nama Provinsi" wire:model="province_name" placeholder="Cth: Prov. Jawa Timur" required />
 <x-input label="Kode (opsional)" wire:model="province_code" placeholder="Cth: 21" />
 <x-select label="Region" wire:model="province_region_id" :options="$this->regionOptions" placeholder="-- Pilih Region --" placeholder-value="" />
 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showProvinceModal', false)" class="btn-ghost" />
 <x-button label="Simpan" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>

 <!-- Regency Form Modal -->
 <x-modal wire:model="showRegencyModal" title="{{ $isEditRegency ?'Edit Kabupaten/Kota' :'Tambah Kabupaten/Kota' }}">
 <form wire:submit.prevent="saveRegency" class="space-y-4">
 <x-input label="Nama" wire:model="regency_name" placeholder="Cth: Kab. Malang" required />
 <x-input label="Kode (opsional)" wire:model="regency_code" placeholder="Cth: 21.04" />
 
 @php
 $typeOpts = [
 ['id' =>'Kabupaten','name' =>'Kabupaten'],
 ['id' =>'Kota','name' =>'Kota'],
 ['id' =>'Lembaga','name' =>'Lembaga'],
 ];
 @endphp
 <x-select label="Tipe" wire:model="regency_type" :options="$typeOpts" />
 <x-select label="Provinsi" wire:model="regency_province_id" :options="$this->provinceOptions" placeholder="-- Pilih Provinsi --" placeholder-value="" />

 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showRegencyModal', false)" class="btn-ghost" />
 <x-button label="Simpan" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>
</div>
