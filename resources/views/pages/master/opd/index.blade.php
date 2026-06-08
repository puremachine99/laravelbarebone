<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use Flux\Flux;
use App\Models\Opd;
use App\Models\Regency;
use App\Models\Urusan;
use Illuminate\Validation\Rule;

usesPagination();
layout('layouts.app');

state([
    'search' => '',
    'filterRegency' => '',
]);

state([
    'sortBy' => 'name',
    'sortDirection' => 'asc',
]);

state([
    'opd_id' => null,
    'name' => '',
    'regency_id' => '',
    'selectedUrusans' => [],
    'isEdit' => false,
    'deleteTargetId' => null,
]);

$regencyOptions = computed(function () {
    return Regency::join('provinces', 'provinces.id', '=', 'regencies.province_id')
        ->select('regencies.id', 'regencies.name as regency_name', 'provinces.name as province_name')
        ->orderBy('regencies.name')
        ->get();
});

$urusanOptions = computed(fn() => Urusan::orderBy('code')->get());

$opds = computed(function () {
    $search = $this->search;
    $filterRegency = $this->filterRegency;

    return Opd::query()
        ->with(['regency.province', 'urusans'])
        ->when($search, fn($q) => $q->whereRaw('LOWER(opds.name) LIKE ?', ['%' . strtolower($search) . '%']))
        ->when($filterRegency, fn($q) => $q->where('opds.regency_id', $filterRegency))
        ->when($this->sortBy === 'regency', function ($q) {
            $q->join('regencies', 'regencies.id', '=', 'opds.regency_id')
              ->select('opds.*')
              ->orderBy('regencies.name', $this->sortDirection);
        }, function ($q) {
            $q->orderBy('opds.' . $this->sortBy, $this->sortDirection);
        })
        ->paginate(10);
});

$sort = function ($column) {
    if ($this->sortBy === $column) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }
};

$updatedSearch = function () {
    $this->resetPage();
};

$updatedFilterRegency = function () {
    $this->resetPage();
};

$resetForm = function () {
    $this->reset(['opd_id', 'name', 'regency_id', 'selectedUrusans', 'isEdit']);
    $this->resetValidation();
};

$save = function () {
    $this->validate([
        'name' => ['required', 'string', 'max:255'],
        'regency_id' => ['required', 'exists:regencies,id'],
        'selectedUrusans' => ['nullable', 'array'],
        'selectedUrusans.*' => ['exists:urusans,id'],
    ]);

    $isNew = is_null($this->opd_id);

    $opd = Opd::updateOrCreate(
        ['id' => $this->opd_id],
        [
            'name' => $this->name,
            'regency_id' => $this->regency_id,
        ]
    );

    $opd->urusans()->sync($this->selectedUrusans ?? []);

    $this->resetForm();
    Flux::modal('opd-modal')->close();
    Flux::toast(
        variant: 'success',
        heading: $isNew ? 'OPD ditambahkan' : 'OPD diperbarui',
        text: $isNew ? 'Data OPD baru berhasil disimpan.' : 'Perubahan data OPD berhasil disimpan.',
    );
};

$edit = function (Opd $opd) {
    $this->resetValidation();
    $this->opd_id = $opd->id;
    $this->name = $opd->name;
    $this->regency_id = $opd->regency_id;
    $this->selectedUrusans = $opd->urusans->pluck('id')->map(fn($id) => (string) $id)->toArray();
    $this->isEdit = true;
    Flux::modal('opd-modal')->show();
};

$confirmDelete = function (string $id) {
    $this->deleteTargetId = $id;
    Flux::modal('confirm-delete')->show();
};

$delete = function () {
    $opd = Opd::findOrFail($this->deleteTargetId);
    $opd->urusans()->detach();
    $opd->delete();

    $this->deleteTargetId = null;
    Flux::modal('confirm-delete')->close();
    Flux::toast(variant: 'danger', text: 'OPD berhasil dihapus.');
};

?>
<div>
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Data Master: OPD</flux:heading>
                <flux:subheading>Kelola daftar Organisasi Perangkat Daerah (OPD) dan bidang urusannya.</flux:subheading>
            </div>

            <flux:modal.trigger name="opd-modal">
                <flux:button variant="primary" icon="plus" wire:click="resetForm">OPD</flux:button>
            </flux:modal.trigger>
        </div>

        <div class="flex gap-3">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari nama OPD..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>
            <div class="w-72">
                <flux:select wire:model.live="filterRegency" variant="listbox" searchable placeholder="Semua Wilayah...">
                    <flux:select.option value="">Semua Wilayah</flux:select.option>
                    @foreach ($this->regencyOptions as $reg)
                        <flux:select.option value="{{ $reg->id }}">{{ $reg->regency_name }} - {{ $reg->province_name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <flux:table :paginate="$this->opds" striped hoverable>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection"
                    wire:click="sort('name')">
                    Nama Instansi / OPD
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'regency'" :direction="$sortDirection"
                    wire:click="sort('regency')">
                    Kabupaten / Kota - Provinsi
                </flux:table.column>
                <flux:table.column>
                    Bidang Urusan
                </flux:table.column>
                <flux:table.column align="right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->opds as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-medium">{{ $item->name }}</flux:table.cell>

                        <flux:table.cell>
                            {{ $item->regency?->name ?? '-' }}
                            <flux:text size="sm" class="text-zinc-400 block">{{ $item->regency?->province?->name }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($item->urusans->isNotEmpty())
                                <flux:text class="block max-w-md truncate" title="{{ $item->urusans->pluck('urusan')->implode(', ') }}">
                                    {{ $item->urusans->pluck('urusan')->implode(', ') }}
                                </flux:text>
                            @else
                                <flux:text class="text-zinc-400">-</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom"
                                wire:click="edit('{{ $item->id }}')" />
                            <flux:button variant="danger" size="sm" icon="trash" inset="top bottom"
                                wire:click="confirmDelete('{{ $item->id }}')" />
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

    <flux:modal name="opd-modal" class="md:w-[500px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEdit ? 'Edit OPD' : 'Tambah OPD Baru' }}</flux:heading>
                <flux:subheading>Masukkan nama instansi, wilayah, dan bidang urusannya.</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nama Instansi / OPD</flux:label>
                <flux:input wire:model="name" placeholder="Cth: Dinas Kesehatan" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Kabupaten / Kota</flux:label>
                <flux:select wire:model="regency_id" variant="listbox" searchable placeholder="Pilih Kabupaten / Kota...">
                    @foreach ($this->regencyOptions as $reg)
                        <flux:select.option value="{{ $reg->id }}">{{ $reg->regency_name }} - {{ $reg->province_name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="regency_id" />
            </flux:field>

            <flux:field>
                <flux:label>Bidang Urusan</flux:label>
                <flux:select wire:model="selectedUrusans" variant="listbox" multiple searchable indicator="checkbox" placeholder="Pilih bidang urusan...">
                    @foreach ($this->urusanOptions as $urusan)
                        <flux:select.option value="{{ $urusan->id }}">{{ $urusan->code }} - {{ $urusan->urusan }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedUrusans" />
            </flux:field>

            <div class="flex justify-end space-x-2 pt-2">
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
                <flux:heading size="lg">Hapus OPD?</flux:heading>
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
