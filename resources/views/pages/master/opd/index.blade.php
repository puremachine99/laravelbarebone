<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Models\Opd;
use App\Models\Region;
use App\Models\Regency;
use App\Models\Urusan;
use Illuminate\Validation\Rule;

usesPagination();

state(['perPage' => 10]);
$updatedPerPage = fn () => $this->resetPage();
layout('layouts.app');

state([
'search' =>'',
'filterRegency' =>'',
'filterJenis' =>'',
'filterRegion' =>'',
]);

state([
'sortBy' =>'name',
'sortDirection' =>'asc',
]);

state([
'opd_id' => null,
'name' =>'',
'jenis' =>'',
'regency_id' =>'',
'selectedUrusans' => [],
'isEdit' => false,
'deleteTargetId' => null,
'showOpdModal' => false,
'showDeleteModal' => false,
]);

$regencyOptions = computed(function () {
 return Regency::with('province')
 ->get()
 ->map(fn($reg) => [
'id' => $reg->id,
'name' => $reg->name .' -' . ($reg->province?->name ??'')
 ])
 ->sortBy('name')
 ->values();
});

$jenisOptions = computed(fn() => Opd::query()
 ->whereNotNull('jenis')
 ->distinct()
 ->orderBy('jenis')
 ->pluck('jenis')
 ->map(fn($jenis) => ['id' => $jenis, 'name' => $jenis])
 ->values()
);

$regionOptions = computed(fn() => Region::orderBy('name')->get(['id', 'name']));

$urusanOptions = computed(fn() => 
 Urusan::orderBy('code')
 ->get()
 ->map(fn($u) => [
'id' => $u->id,
'name' => $u->code .' -' . $u->urusan
 ])
);

$filteredQuery = function () {
 $search = $this->search;
 $filterRegency = $this->filterRegency;

 return Opd::query()
 ->search($search)
 ->when($filterRegency, fn($q) => $q->where('pmo_opds.regency_id', $filterRegency))
 ->when($this->filterJenis, fn($q) => $q->where('pmo_opds.jenis', $this->filterJenis))
 ->when($this->filterRegion, fn($q) => $q->whereHas('regency', fn($r) => $r->where('region_id', $this->filterRegion)))
 ;
};

$opds = computed(function () {
 return $this->filteredQuery()
 ->with(['regency.province','regency.region','urusans'])
 ->when($this->sortBy ==='regency', function ($q) {
 $q->join('regencies','regencies.id','=','pmo_opds.regency_id')
 ->select('pmo_opds.*')
 ->orderBy('regencies.name', $this->sortDirection);
 }, function ($q) {
 $q->orderBy('pmo_opds.' . $this->sortBy, $this->sortDirection);
 })
 ->paginate(\App\Support\PerPage::resolve($this->perPage));
});


$summary = computed(function () {
 $total = $this->filteredQuery()->count();
 $perJenis = $this->filteredQuery()
 ->reorder()
 ->selectRaw('pmo_opds.jenis, COUNT(*) as total')
 ->groupBy('pmo_opds.jenis')
 ->pluck('total', 'jenis');
 $withUrusan = $this->filteredQuery()->has('urusans')->count();

 $perRegion = $this->filteredQuery()
 ->reorder()
 ->join('regencies', 'regencies.id', '=', 'pmo_opds.regency_id')
 ->join('regions', 'regions.id', '=', 'regencies.region_id')
 ->selectRaw('regions.name as region_name, COUNT(*) as total')
 ->groupBy('regions.name')
 ->orderBy('regions.name')
 ->pluck('total', 'region_name');

 $coverage = $this->filteredQuery()
 ->reorder()
 ->join('regencies', 'regencies.id', '=', 'pmo_opds.regency_id')
 ->selectRaw('COUNT(DISTINCT pmo_opds.regency_id) as regencies, COUNT(DISTINCT regencies.province_id) as provinces')
 ->first();

 return [
'total' => $total,
'jenis' => collect(['OPD', 'Kementrian', 'Unit Kesehatan'])->mapWithKeys(fn ($jenis) => [$jenis => (int) ($perJenis[$jenis] ?? 0)]),
'withUrusan' => $withUrusan,
'perRegion' => $perRegion,
'regencies' => (int) $coverage->regencies,
'provinces' => (int) $coverage->provinces,
 ];
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

$updatedFilterRegency = function () {
 $this->resetPage();
};

$updatedFilterJenis = function () {
 $this->resetPage();
};

$updatedFilterRegion = function () {
 $this->resetPage();
};

$resetForm = function () {
 $this->reset(['opd_id','name','jenis','regency_id','selectedUrusans','isEdit']);
 $this->resetValidation();
};

$save = function () {
 $this->validate([
'name' => ['required','string','max:255'],
'jenis' => ['nullable','string','max:50'],
'regency_id' => ['required','exists:regencies,id'],
'selectedUrusans' => ['nullable','array'],
'selectedUrusans.*' => ['exists:pmo_urusans,id'],
 ]);

 $isNew = is_null($this->opd_id);

 $opd = Opd::updateOrCreate(
 ['id' => $this->opd_id],
 [
'name' => $this->name,
'jenis' => $this->jenis ?: null,
'regency_id' => $this->regency_id,
 ]
 );

 $opd->urusans()->sync($this->selectedUrusans ?? []);

 $this->resetForm();
 $this->showOpdModal = false;
 session()->flash('success', $isNew ?'OPD berhasil ditambahkan!' :'OPD berhasil diperbarui!');
};

$edit = function (Opd $opd) {
 $this->resetValidation();
 $this->opd_id = $opd->id;
 $this->name = $opd->name;
 $this->jenis = $opd->jenis ??'';
 $this->regency_id = $opd->regency_id;
 $this->selectedUrusans = $opd->urusans->pluck('id')->map(fn($id) => (string) $id)->toArray();
 $this->isEdit = true;
 $this->showOpdModal = true;
};

$confirmDelete = function (string $id) {
 $this->deleteTargetId = $id;
 $this->showDeleteModal = true;
};

$delete = function () {
 $opd = Opd::findOrFail($this->deleteTargetId);
 $opd->urusans()->detach();
 $opd->delete();

 $this->deleteTargetId = null;
 $this->showDeleteModal = false;
 session()->flash('error','OPD berhasil dihapus.');
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

 @php
 $summary = $this->summary;
 $jenisColors = ['OPD' => 'bg-primary', 'Kementrian' => 'bg-success', 'Unit Kesehatan' => 'bg-warning'];
 $percentOf = fn ($count) => $summary['total'] > 0 ? round($count / $summary['total'] * 100, 1) : 0;
 $topRegions = $summary['perRegion']->sortDesc()->take(3);
 $withoutUrusan = $summary['total'] - $summary['withUrusan'];
 @endphp

 <!-- Mini Dashboard -->
 <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
 <!-- Card 1: Total Instansi & Jenis -->
 <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-sm rounded-2xl">
 <div>
 <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">TOTAL INSTANSI & JENIS</h3>
 <h2 class="text-2xl mt-1 font-extrabold text-base-content">{{ number_format($summary['total'], 0, ',', '.') }} Instansi</h2>
 </div>

 <!-- Stacked Horizontal Bar Chart -->
 <div class="h-4 w-full bg-base-200 rounded-full overflow-hidden flex">
 @foreach ($summary['jenis'] as $jenisName => $jenisTotal)
 @if ($jenisTotal > 0)
 <div class="h-full {{ $jenisColors[$jenisName] }}"
 style="width: {{ $percentOf($jenisTotal) }}%"
 title="{{ $jenisName }}: {{ number_format($jenisTotal, 0, ',', '.') }} ({{ $percentOf($jenisTotal) }}%)"></div>
 @endif
 @endforeach
 </div>

 <!-- Legend Grid -->
 <div class="grid grid-cols-2 gap-x-2 gap-y-1 text-xs">
 @foreach ($summary['jenis'] as $jenisName => $jenisTotal)
 <div class="flex items-center gap-1.5">
 <span class="w-2.5 h-2.5 rounded-full {{ $jenisColors[$jenisName] }} inline-block"></span>
 <span class="text-base-content/70 truncate">{{ $jenisName }} ({{ number_format($jenisTotal, 0, ',', '.') }})</span>
 </div>
 @endforeach
 </div>
 </div>

 <!-- Card 2: Cakupan Wilayah -->
 <div class="card bg-base-100 border border-base-300 p-6 space-y-4 flex flex-col justify-between shadow-sm rounded-2xl">
 <div>
 <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">CAKUPAN WILAYAH</h3>
 <h2 class="text-xl mt-1 font-extrabold text-base-content">{{ number_format($summary['provinces'], 0, ',', '.') }} Provinsi</h2>
 <p class="text-xs text-base-content/40 mt-0.5">
 Tersebar di {{ number_format($summary['regencies'], 0, ',', '.') }} kabupaten / kota.
 </p>
 </div>

 <div class="space-y-1.5 pt-2">
 @forelse ($topRegions as $regionName => $regionTotal)
 <div class="flex items-center justify-between text-xs">
 <span class="font-medium text-base-content/70">{{ $regionName }}</span>
 <span class="text-base-content/50 font-semibold">{{ number_format($regionTotal, 0, ',', '.') }} Instansi</span>
 </div>
 @empty
 <span class="text-xs text-base-content/40">-</span>
 @endforelse
 </div>
 </div>

 <!-- Card 3: Kelengkapan Bidang Urusan -->
 <div class="card bg-base-100 border border-base-300 p-6 space-y-4 flex flex-col justify-between shadow-sm rounded-2xl">
 <div>
 <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">KELENGKAPAN BIDANG URUSAN</h3>
 <h2 class="text-xl mt-1 font-extrabold text-base-content">{{ $percentOf($summary['withUrusan']) }}% Terisi</h2>
 <p class="text-xs text-base-content/40 mt-0.5">
 Persentase instansi yang sudah memiliki bidang urusan.
 </p>
 </div>

 <div class="flex items-center justify-between text-xs border-t border-base-300 pt-3">
 <div>
 <span class="text-base-content/40 block uppercase tracking-wider text-[10px]">Sudah Ada</span>
 <span class="font-semibold text-sm text-base-content">{{ number_format($summary['withUrusan'], 0, ',', '.') }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block uppercase tracking-wider text-[10px] text-right">Belum Ada</span>
 <span class="font-semibold text-sm block text-right text-base-content">{{ number_format($withoutUrusan, 0, ',', '.') }}</span>
 </div>
 </div>
 </div>
 </div>

 <div class="card bg-base-100 border border-base-300 p-6 space-y-6 shadow-sm rounded-2xl">
 <div class="flex items-center justify-between">
 <div>
 <h2 class="text-xl font-bold text-base-content">Data Master: OPD</h2>
 <p class="text-sm text-base-content/50 mt-1">Kelola daftar Organisasi Perangkat Daerah (OPD) dan bidang urusannya.</p>
 </div>

 <x-button variant="primary" icon="o-plus" size="btn-sm" label="OPD" wire:click="resetForm(); $set('showOpdModal', true);" />
 </div>

 <div class="flex gap-3">
 <div class="flex-1">
 <x-input
 wire:model.live.debounce.300ms="search"
 placeholder="Cari nama, jenis, wilayah, provinsi, region, atau urusan..."
 icon="o-magnifying-glass"
 clearable
 />
 </div>
 <div class="w-44">
 <x-select wire:model.live="filterJenis" :options="$this->jenisOptions" placeholder="Semua Jenis" placeholder-value="" />
 </div>
 <div class="w-44">
 <x-select wire:model.live="filterRegion" :options="$this->regionOptions" placeholder="Semua Region" placeholder-value="" />
 </div>
 <div class="w-72">
 <x-select wire:model.live="filterRegency" :options="$this->regencyOptions" placeholder="Semua Wilayah" placeholder-value="" />
 </div>
 </div>

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th>ID</th>
 <th class="cursor-pointer py-3" wire:click="sort('name')">
 <div class="flex items-center gap-1">
 <span>Nama Instansi / OPD</span>
 @if ($sortBy ==='name')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th class="cursor-pointer py-3" wire:click="sort('regency')">
 <div class="flex items-center gap-1">
 <span>Kabupaten / Kota - Provinsi</span>
 @if ($sortBy ==='regency')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th>Jenis</th>
 <th>Bidang Urusan</th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->opds as $item)
 <tr class="border-b border-base-300 hover:bg-base-200">
 <td class="text-base-content/50">{{ $item->id }}</td>
 <td class="font-semibold text-base-content py-3">{{ $item->name }}</td>
 <td>
 <div>{{ $item->regency?->name ??'-' }}</div>
 <span class="text-xs text-base-content/40 block mt-0.5">{{ $item->regency?->province?->name }}@if ($item->regency?->region) · {{ $item->regency->region->name }}@endif</span>
 </td>
 <td>
 @if ($item->jenis)
 <div class="badge badge-sm badge-outline">{{ $item->jenis }}</div>
 @else
 <span class="text-base-content/40">-</span>
 @endif
 </td>
 <td>
 @if ($item->urusans->isNotEmpty())
 <span class="block max-w-md truncate" title="{{ $item->urusans->pluck('urusan')->implode(',') }}">
 {{ $item->urusans->pluck('urusan')->implode(',') }}
 </span>
 @else
 <span class="text-base-content/40">-</span>
 @endif
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
 <x-pagination-footer :paginator="$this->opds" />
 </div>
 </div>

 <!-- Modal Form -->
 <x-modal wire:model="showOpdModal" title="{{ $isEdit ?'Edit OPD' :'Tambah OPD Baru' }}" subtitle="Masukkan nama instansi, wilayah, dan bidang urusannya." class="md:w-[500px]">
 <form wire:submit.prevent="save" class="space-y-4">
 <x-input label="Nama Instansi / OPD" wire:model="name" placeholder="Cth: Dinas Kesehatan" required />
 
 @php
 $jenisFormOptions = [['id' =>'OPD','name' =>'OPD'],['id' =>'Kementrian','name' =>'Kementrian'],['id' =>'Unit Kesehatan','name' =>'Unit Kesehatan']];
 @endphp
 <x-select label="Jenis" wire:model="jenis" :options="$jenisFormOptions" placeholder="Pilih Jenis..." placeholder-value="" />

 <x-select label="Kabupaten / Kota" wire:model="regency_id" :options="$this->regencyOptions" placeholder="Pilih Kabupaten / Kota..." placeholder-value="" required />

 <div>
 <span class="label-text font-semibold mb-1 block">Bidang Urusan</span>
 <div class="border border-base-300 rounded-lg p-3 max-h-48 overflow-y-auto space-y-2">
 @foreach ($this->urusanOptions as $urusan)
 <label class="flex items-center gap-2.5 cursor-pointer">
 <input type="checkbox" wire:model="selectedUrusans" value="{{ $urusan['id'] }}" class="checkbox checkbox-sm checkbox-primary" />
 <span class="text-sm text-base-content/80">{{ $urusan['name'] }}</span>
 </label>
 @endforeach
 </div>
 @error('selectedUrusans') <span class="text-error text-xs block mt-1">{{ $message }}</span> @enderror
 </div>

 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showOpdModal', false)" class="btn-ghost" />
 <x-button label="Simpan Data" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>

 <!-- Confirmation Modal -->
 <x-modal wire:model="showDeleteModal" title="Hapus OPD?" subtitle="Tindakan ini tidak bisa dibatalkan.">
 <div class="flex justify-end gap-2 pt-4">
 <x-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
 <x-button label="Ya, Hapus" wire:click="delete" class="btn-error text-white" />
 </div>
 </x-modal>
</div>
