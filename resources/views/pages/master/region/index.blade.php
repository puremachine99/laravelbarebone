<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use Flux\Flux;
use App\Models\Region;
use App\Models\Province;
use App\Models\Regency;
use Illuminate\Validation\Rule;

usesPagination();
layout('layouts.app');

state(['activeTab' => 'regions']);

state([
    'region_id'      => null,
    'region_name'    => '',
    'region_code'    => '',
    'isEditRegion'   => false,
]);

state(['sortRegionBy' => 'name', 'sortRegionDir' => 'asc']);

$regions = computed(fn() =>
    Region::orderBy($this->sortRegionBy, $this->sortRegionDir)->paginate(10, pageName: 'regionsPage')
);

$sortRegion = function ($col) {
    if ($this->sortRegionBy === $col) {
        $this->sortRegionDir = $this->sortRegionDir === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortRegionBy = $col;
        $this->sortRegionDir = 'asc';
    }
};

$sortProvince = function ($col) {
    if ($this->sortProvinceBy === $col) {
        $this->sortProvinceDir = $this->sortProvinceDir === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortProvinceBy = $col;
        $this->sortProvinceDir = 'asc';
    }
};

$resetRegionForm = fn() => $this->reset(['region_id', 'region_name', 'region_code', 'isEditRegion'])
    + $this->resetValidation();

$saveRegion = function () {
    $this->validate([
        'region_name' => ['required', 'string', 'max:100', Rule::unique('regions', 'name')->ignore($this->region_id)],
        'region_code' => ['nullable', 'string', 'max:20', Rule::unique('regions', 'code')->ignore($this->region_id)],
    ]);

    $isNew = is_null($this->region_id);
    Region::updateOrCreate(['id' => $this->region_id], ['name' => $this->region_name, 'code' => $this->region_code ?: null]);

    $this->resetRegionForm();
    Flux::modal('region-modal')->close();
    Flux::toast(variant: 'success', heading: $isNew ? 'Region ditambahkan' : 'Region diperbarui', text: 'Data berhasil disimpan.');
};

$editRegion = function (Region $region) {
    $this->resetValidation();
    $this->region_id   = $region->id;
    $this->region_name = $region->name;
    $this->region_code = $region->code ?? '';
    $this->isEditRegion = true;
    Flux::modal('region-modal')->show();
};

$deleteRegion = function (string $id) {
    Region::findOrFail($id)->delete();
    Flux::toast(variant: 'danger', text: 'Region berhasil dihapus.');
};

state([
    'province_id'       => null,
    'province_name'     => '',
    'province_code'     => '',
    'province_region_id'=> '',
    'isEditProvince'    => false,
]);

state(['sortProvinceBy' => 'name', 'sortProvinceDir' => 'asc']);

$regionOptions = computed(fn() => Region::orderBy('name')->get());

$provinces = computed(fn() =>
    Province::with('region')
        ->orderBy($this->sortProvinceBy, $this->sortProvinceDir)
        ->paginate(10, pageName: 'provincesPage')
);

$resetProvinceForm = fn() => $this->reset(['province_id', 'province_name', 'province_code', 'province_region_id', 'isEditProvince'])
    + $this->resetValidation();

$saveProvince = function () {
    $this->validate([
        'province_name'      => ['required', 'string', 'max:150', Rule::unique('provinces', 'name')->ignore($this->province_id)],
        'province_code'      => ['nullable', 'string', 'max:10', Rule::unique('provinces', 'code')->ignore($this->province_id)],
        'province_region_id' => ['required', 'exists:regions,id'],
    ]);

    $isNew = is_null($this->province_id);
    Province::updateOrCreate(
        ['id' => $this->province_id],
        ['name' => $this->province_name, 'code' => $this->province_code ?: null, 'region_id' => $this->province_region_id]
    );

    $this->resetProvinceForm();
    Flux::modal('province-modal')->close();
    Flux::toast(variant: 'success', heading: $isNew ? 'Provinsi ditambahkan' : 'Provinsi diperbarui', text: 'Data berhasil disimpan.');
};

$editProvince = function (Province $province) {
    $this->resetValidation();
    $this->province_id        = $province->id;
    $this->province_name      = $province->name;
    $this->province_code      = $province->code ?? '';
    $this->province_region_id = $province->region_id;
    $this->isEditProvince     = true;
    Flux::modal('province-modal')->show();
};

$deleteProvince = function (string $id) {
    Province::findOrFail($id)->delete();
    Flux::toast(variant: 'danger', text: 'Provinsi berhasil dihapus.');
};

state([
    'regency_id'          => null,
    'regency_name'        => '',
    'regency_code'        => '',
    'regency_type'        => 'Kabupaten',
    'regency_province_id' => '',
    'isEditRegency'       => false,
    'searchRegency'       => '',
    'filterProvinceId'    => '',
]);

state(['sortRegencyBy' => 'name', 'sortRegencyDir' => 'asc']);

$provinceOptions = computed(fn() => Province::orderBy('name')->get());

$regencies = computed(function () {
    $search = $this->searchRegency;
    return Regency::with('province.region')
        ->when($search, fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']))
        ->when($this->filterProvinceId, fn($q) => $q->where('province_id', $this->filterProvinceId))
        ->orderBy($this->sortRegencyBy, $this->sortRegencyDir)
        ->paginate(10, pageName: 'regenciesPage');
});

$sortRegency = function ($col) {
    if ($this->sortRegencyBy === $col) {
        $this->sortRegencyDir = $this->sortRegencyDir === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortRegencyBy = $col;
        $this->sortRegencyDir = 'asc';
    }
};


$updatedSearchRegency = fn() => $this->resetPage('regenciesPage');

$resetRegencyForm = fn() => $this->reset(['regency_id', 'regency_name', 'regency_code', 'regency_type', 'regency_province_id', 'isEditRegency'])
    + $this->resetValidation();

$saveRegency = function () {
    $this->validate([
        'regency_name'        => ['required', 'string', 'max:150'],
        'regency_code'        => ['nullable', 'string', 'max:10', Rule::unique('regencies', 'code')->ignore($this->regency_id)],
        'regency_type'        => ['required', 'in:Kabupaten,Kota,Lembaga'],
        'regency_province_id' => ['required', 'exists:provinces,id'],
    ]);

    $isNew = is_null($this->regency_id);
    Regency::updateOrCreate(
        ['id' => $this->regency_id],
        [
            'name'        => $this->regency_name,
            'code'        => $this->regency_code ?: null,
            'type'        => $this->regency_type,
            'province_id' => $this->regency_province_id,
        ]
    );

    $this->resetRegencyForm();
    Flux::modal('regency-modal')->close();
    Flux::toast(variant: 'success', heading: $isNew ? 'Data ditambahkan' : 'Data diperbarui', text: 'Data berhasil disimpan.');
};

$editRegency = function (Regency $regency) {
    $this->resetValidation();
    $this->regency_id          = $regency->id;
    $this->regency_name        = $regency->name;
    $this->regency_code        = $regency->code ?? '';
    $this->regency_type        = $regency->type;
    $this->regency_province_id = $regency->province_id;
    $this->isEditRegency       = true;
    Flux::modal('regency-modal')->show();
};

$deleteRegency = function (string $id) {
    Regency::findOrFail($id)->delete();
    Flux::toast(variant: 'danger', text: 'Data berhasil dihapus.');
};

?>
<div>
    <flux:card class="space-y-6">
        <div>
            <flux:heading size="xl">Data Master: Lokasi</flux:heading>
            <flux:subheading>Kelola data Region, Provinsi, dan Kabupaten/Kota.</flux:subheading>
        </div>

        <flux:separator variant="subtle" />

        <flux:tab.group>
            <flux:tabs wire:model="activeTab">
                <flux:tab name="regions" icon="map">Regions</flux:tab>
                <flux:tab name="provinces" icon="building-library">Provinces</flux:tab>
                <flux:tab name="regencies" icon="building-office-2">Kabupaten / Kota</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="regions">
                <div class="space-y-4 pt-2">
                    <div class="flex justify-end">
                        <flux:modal.trigger name="region-modal">
                            <flux:button variant="primary" icon="plus" size="sm" wire:click="resetRegionForm">Region</flux:button>
                        </flux:modal.trigger>
                    </div>

                    <flux:table :paginate="$this->regions" striped hoverable>
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$sortRegionBy === 'code'" :direction="$sortRegionDir" wire:click="sortRegion('code')">Kode</flux:table.column>
                            <flux:table.column sortable :sorted="$sortRegionBy === 'name'" :direction="$sortRegionDir" wire:click="sortRegion('name')">Nama Region</flux:table.column>
                            <flux:table.column align="right">Aksi</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($this->regions as $region)
                                <flux:table.row :key="$region->id">
                                    <flux:table.cell>
                                        @if ($region->code)
                                            <flux:badge variant="pill" color="zinc">{{ $region->code }}</flux:badge>
                                        @else <flux:text>-</flux:text> @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $region->name }}</flux:table.cell>
                                    <flux:table.cell align="right">
                                        <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom" wire:click="editRegion('{{ $region->id }}')" />
                                        <flux:button variant="danger" size="sm" icon="trash" inset="top bottom" wire:confirm="Yakin hapus region ini?" wire:click="deleteRegion('{{ $region->id }}')" />
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="3"><flux:text class="text-center">Belum ada data.</flux:text></flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:tab.panel>

            <flux:tab.panel name="provinces">
                <div class="space-y-4 pt-2">
                    <div class="flex justify-end">
                        <flux:modal.trigger name="province-modal">
                            <flux:button variant="primary" icon="plus" size="sm" wire:click="resetProvinceForm">Provinsi</flux:button>
                        </flux:modal.trigger>
                    </div>

                    <flux:table :paginate="$this->provinces" striped hoverable>
                        <flux:table.columns>
                            <flux:table.column>Kode</flux:table.column>
                            <flux:table.column sortable :sorted="$sortProvinceBy === 'name'" :direction="$sortProvinceDir" wire:click="sortProvince('name')">Nama Provinsi</flux:table.column>
                            <flux:table.column>Region</flux:table.column>
                            <flux:table.column align="right">Aksi</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($this->provinces as $province)
                                <flux:table.row :key="$province->id">
                                    <flux:table.cell>
                                        @if ($province->code)
                                            <flux:badge variant="pill" color="zinc">{{ $province->code }}</flux:badge>
                                        @else <flux:text>-</flux:text> @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $province->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge variant="pill" color="blue">{{ $province->region?->name ?? '-' }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell align="right">
                                        <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom" wire:click="editProvince('{{ $province->id }}')" />
                                        <flux:button variant="danger" size="sm" icon="trash" inset="top bottom" wire:confirm="Yakin hapus provinsi ini?" wire:click="deleteProvince('{{ $province->id }}')" />
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4"><flux:text class="text-center">Belum ada data.</flux:text></flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:tab.panel>

            <flux:tab.panel name="regencies">
                <div class="space-y-4 pt-2">
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <flux:input wire:model.live.debounce.300ms="searchRegency" placeholder="Cari kabupaten/kota..." icon="magnifying-glass" clearable />
                        </div>
                        <div class="w-48">
                            <flux:select wire:model.live="filterProvinceId">
                                <flux:select.option value="">Semua Provinsi</flux:select.option>
                                @foreach ($this->provinceOptions as $prov)
                                    <flux:select.option value="{{ $prov->id }}">{{ $prov->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:modal.trigger name="regency-modal">
                            <flux:button variant="primary" icon="plus" size="sm" wire:click="resetRegencyForm">Tambah</flux:button>
                        </flux:modal.trigger>
                    </div>

                    <flux:table :paginate="$this->regencies" striped hoverable>
                        <flux:table.columns>
                            <flux:table.column>Kode</flux:table.column>
                            <flux:table.column sortable :sorted="$sortRegencyBy === 'name'" :direction="$sortRegencyDir" wire:click="sortRegency('name')">Nama</flux:table.column>
                            <flux:table.column>Tipe</flux:table.column>
                            <flux:table.column>Provinsi</flux:table.column>
                            <flux:table.column align="right">Aksi</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($this->regencies as $regency)
                                <flux:table.row :key="$regency->id">
                                    <flux:table.cell>
                                        @if ($regency->code)
                                            <flux:badge variant="pill" color="zinc">{{ $regency->code }}</flux:badge>
                                        @else <flux:text>-</flux:text> @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $regency->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge variant="pill" color="{{ match($regency->type) { 'Kota' => 'blue', 'Lembaga' => 'purple', default => 'zinc' } }}">
                                            {{ $regency->type }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $regency->province?->name ?? '-' }}</flux:table.cell>
                                    <flux:table.cell align="right">
                                        <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom" wire:click="editRegency('{{ $regency->id }}')" />
                                        <flux:button variant="danger" size="sm" icon="trash" inset="top bottom" wire:confirm="Yakin hapus data ini?" wire:click="deleteRegency('{{ $regency->id }}')" />
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5"><flux:text class="text-center">Belum ada data.</flux:text></flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:tab.panel>
        </flux:tab.group>
    </flux:card>

    <flux:modal name="region-modal" class="md:w-96">
        <form wire:submit="saveRegion" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEditRegion ? 'Edit Region' : 'Tambah Region' }}</flux:heading>
            </div>
            <flux:field>
                <flux:label>Nama Region</flux:label>
                <flux:input wire:model="region_name" placeholder="Cth: Region 1" required />
                <flux:error name="region_name" />
            </flux:field>
            <flux:field>
                <flux:label>Kode <flux:text size="sm" class="text-zinc-400">(opsional)</flux:text></flux:label>
                <flux:input wire:model="region_code" placeholder="Cth: REG-1" />
                <flux:error name="region_code" />
            </flux:field>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="province-modal" class="md:w-96">
        <form wire:submit="saveProvince" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEditProvince ? 'Edit Provinsi' : 'Tambah Provinsi' }}</flux:heading>
            </div>
            <flux:field>
                <flux:label>Nama Provinsi</flux:label>
                <flux:input wire:model="province_name" placeholder="Cth: Prov. Jawa Timur" required />
                <flux:error name="province_name" />
            </flux:field>
            <flux:field>
                <flux:label>Kode <flux:text size="sm" class="text-zinc-400">(opsional)</flux:text></flux:label>
                <flux:input wire:model="province_code" placeholder="Cth: 21" />
                <flux:error name="province_code" />
            </flux:field>
            <flux:field>
                <flux:label>Region</flux:label>
                <flux:select wire:model="province_region_id">
                    <flux:select.option value="">-- Pilih Region --</flux:select.option>
                    @foreach ($this->regionOptions as $reg)
                        <flux:select.option value="{{ $reg->id }}">{{ $reg->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="province_region_id" />
            </flux:field>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="regency-modal" class="md:w-96">
        <form wire:submit="saveRegency" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEditRegency ? 'Edit Kabupaten/Kota' : 'Tambah Kabupaten/Kota' }}</flux:heading>
            </div>
            <flux:field>
                <flux:label>Nama</flux:label>
                <flux:input wire:model="regency_name" placeholder="Cth: Kab. Malang" required />
                <flux:error name="regency_name" />
            </flux:field>
            <flux:field>
                <flux:label>Kode <flux:text size="sm" class="text-zinc-400">(opsional)</flux:text></flux:label>
                <flux:input wire:model="regency_code" placeholder="Cth: 21.04" />
                <flux:error name="regency_code" />
            </flux:field>
            <flux:field>
                <flux:label>Tipe</flux:label>
                <flux:select wire:model="regency_type">
                    <flux:select.option value="Kabupaten">Kabupaten</flux:select.option>
                    <flux:select.option value="Kota">Kota</flux:select.option>
                    <flux:select.option value="Lembaga">Lembaga</flux:select.option>
                </flux:select>
                <flux:error name="regency_type" />
            </flux:field>
            <flux:field>
                <flux:label>Provinsi</flux:label>
                <flux:select wire:model="regency_province_id">
                    <flux:select.option value="">-- Pilih Provinsi --</flux:select.option>
                    @foreach ($this->provinceOptions as $prov)
                        <flux:select.option value="{{ $prov->id }}">{{ $prov->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="regency_province_id" />
            </flux:field>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
