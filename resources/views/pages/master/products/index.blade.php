<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use Flux\Flux;
use App\Models\Division;
use App\Models\ServiceCenter;
use App\Models\ProductCategory;
use App\Models\Output;
use Illuminate\Validation\Rule;

usesPagination();
layout('layouts.app');

state([
    'search' => '',
    'filterDivision' => '',
    'filterServiceCenter' => '',
]);

state([
    'sortBy' => 'generated_code',
    'sortDirection' => 'asc',
]);

state([
    'product_id' => null,
    'division_id' => '',
    'service_center_id' => '',
    'category_code' => '',
    'output_name' => '',
    'pelaksana' => '',
    'status' => true,
    'isEdit' => false,
    'deleteTargetId' => null,
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

$categories = computed(fn() => ProductCategory::orderBy('code')->get());

$products = computed(function () {
    $search = $this->search;
    $filterDivision = $this->filterDivision;
    $filterServiceCenter = $this->filterServiceCenter;

    return Output::query()
        ->with(['division', 'serviceCenter'])
        ->when($search, fn($q) => $q->whereRaw('LOWER(output_name) LIKE ?', ['%' . strtolower($search) . '%'])
                                    ->orWhereRaw('LOWER(generated_code) LIKE ?', ['%' . strtolower($search) . '%']))
        ->when($filterDivision, fn($q) => $q->where('division_id', $filterDivision))
        ->when($filterServiceCenter, fn($q) => $q->where('service_center_id', $filterServiceCenter))
        ->orderBy($this->sortBy, $this->sortDirection)
        ->paginate(10);
});

$previewCode = computed(function () {
    if (!$this->division_id || !$this->service_center_id || !$this->category_code) {
        return 'Akan di-generate otomatis';
    }

    $div = Division::find($this->division_id);
    $sc = ServiceCenter::find($this->service_center_id);

    if (!$div || !$sc) {
        return 'Akan di-generate otomatis';
    }

    $existing = Output::where('division_id', $this->division_id)
        ->where('service_center_id', $this->service_center_id)
        ->where('category_code', $this->category_code)
        ->when($this->product_id, fn($q) => $q->where('id', '!=', $this->product_id))
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
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }
};

$updatedSearch = function () {
    $this->resetPage();
};

$updatedFilterDivision = function () {
    $this->filterServiceCenter = '';
    $this->resetPage();
};

$updatedFilterServiceCenter = function () {
    $this->resetPage();
};

$updatedDivisionId = function () {
    $this->service_center_id = '';
};

$resetForm = function () {
    $this->reset(['product_id', 'division_id', 'service_center_id', 'category_code', 'output_name', 'pelaksana', 'status', 'isEdit']);
    $this->resetValidation();
};

$save = function () {
    $this->validate([
        'division_id' => ['required', 'exists:divisions,id'],
        'service_center_id' => ['required', 'exists:service_centers,id'],
        'category_code' => ['required', 'exists:product_categories,code'],
        'output_name' => ['required', 'string', 'max:255'],
        'pelaksana' => ['required', 'string', 'max:255'],
        'status' => ['required', 'boolean'],
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
    Flux::modal('product-modal')->close();
    Flux::toast(
        variant: 'success',
        heading: $isNew ? 'Output ditambahkan' : 'Output diperbarui',
        text: $isNew ? 'Data output baru berhasil disimpan.' : 'Perubahan data output berhasil disimpan.',
    );
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
    Flux::modal('product-modal')->show();
};

$confirmDelete = function (string $id) {
    $this->deleteTargetId = $id;
    Flux::modal('confirm-delete')->show();
};

$delete = function () {
    Output::findOrFail($this->deleteTargetId)->delete();
    $this->deleteTargetId = null;
    Flux::modal('confirm-delete')->close();
    Flux::toast(variant: 'danger', text: 'Output berhasil dihapus.');
};

?>
<div>
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Data Master: Output / Produk</flux:heading>
                <flux:subheading>Kelola daftar output divisi, pusat layanan, kategori produk, dan kode output otomatis.</flux:subheading>
            </div>

            <flux:modal.trigger name="product-modal">
                <flux:button variant="primary" icon="plus" wire:click="resetForm">Output</flux:button>
            </flux:modal.trigger>
        </div>

        <div class="flex gap-3 flex-wrap md:flex-nowrap">
            <div class="flex-1 min-w-[200px]">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode atau nama output..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>
            <div class="w-64">
                <flux:select wire:model.live="filterDivision" variant="listbox" searchable placeholder="Semua Divisi...">
                    <flux:select.option value="">Semua Divisi</flux:select.option>
                    @foreach ($this->divisions as $div)
                        <flux:select.option value="{{ $div->id }}">{{ $div->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-64">
                <flux:select wire:model.live="filterServiceCenter" variant="listbox" searchable placeholder="Semua Pusat Layanan...">
                    <flux:select.option value="">Semua Pusat Layanan</flux:select.option>
                    @foreach ($this->filterServiceCenterOptions as $sc)
                        <flux:select.option value="{{ $sc->id }}">{{ $sc->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <flux:table :paginate="$this->products" striped hoverable>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'generated_code'" :direction="$sortDirection"
                    wire:click="sort('generated_code')">
                    Kode Output
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'output_name'" :direction="$sortDirection"
                    wire:click="sort('output_name')">
                    Nama Output / Produk
                </flux:table.column>
                <flux:table.column>
                    Divisi & Pusat Layanan
                </flux:table.column>
                <flux:table.column>
                    Pelaksana
                </flux:table.column>
                <flux:table.column>
                    Status
                </flux:table.column>
                <flux:table.column align="right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->products as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <flux:badge color="blue" variant="pill">{{ $item->generated_code }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell class="font-medium">{{ $item->output_name }}</flux:table.cell>

                        <flux:table.cell>
                            {{ $item->division?->name }}
                            <flux:text size="sm" class="text-zinc-400 block">{{ $item->serviceCenter?->name }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>{{ $item->pelaksana ?? '-' }}</flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" variant="pill" color="{{ $item->status ? 'green' : 'red' }}">
                                {{ $item->status ? 'Aktif' : 'Non Aktif' }}
                            </flux:badge>
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
                        <flux:table.cell colspan="6">
                            <flux:text class="text-center">Belum ada data yang ditampilkan.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="product-modal" class="md:w-[750px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEdit ? 'Edit Data Output' : 'Tambah Output Baru' }}</flux:heading>
                <flux:subheading>Masukkan informasi divisi, pusat layanan, kategori, dan detail output.</flux:subheading>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Divisi</flux:label>
                    <flux:select wire:model.live="division_id" variant="listbox" searchable placeholder="Pilih Divisi...">
                        @foreach ($this->divisions as $div)
                            <flux:select.option value="{{ $div->id }}">{{ $div->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="division_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Pusat Layanan</flux:label>
                    <flux:select wire:model.live="service_center_id" variant="listbox" searchable placeholder="Pilih Layanan...">
                        @foreach ($this->formServiceCenterOptions as $sc)
                            <flux:select.option value="{{ $sc->id }}">{{ $sc->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="service_center_id" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Kode Produk / Kategori</flux:label>
                    <flux:select wire:model.live="category_code" variant="listbox" searchable placeholder="Pilih Produk...">
                        @foreach ($this->categories as $cat)
                            <flux:select.option value="{{ $cat->code }}">{{ $cat->code }} - {{ $cat->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="category_code" />
                </flux:field>

                <flux:field>
                    <flux:label>Status Output</flux:label>
                    <flux:select wire:model="status">
                        <flux:select.option value="1">Aktif</flux:select.option>
                        <flux:select.option value="0">Non Aktif</flux:select.option>
                    </flux:select>
                    <flux:error name="status" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Output</flux:label>
                <flux:input wire:model="output_name" placeholder="Insert Output Name" required />
                <flux:error name="output_name" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Pelaksana</flux:label>
                    <flux:input wire:model="pelaksana" placeholder="Insert Pelaksana" required />
                    <flux:error name="pelaksana" />
                </flux:field>

                <flux:field>
                    <flux:label>Kode Output (Pratinjau)</flux:label>
                    <flux:input readonly value="{{ $this->previewCode }}" class="bg-zinc-50 dark:bg-zinc-800 font-mono text-blue-600 dark:text-blue-400" />
                </flux:field>
            </div>

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
                <flux:heading size="lg">Hapus Output?</flux:heading>
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
