<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use Flux\Flux;
use App\Models\Contact;
use App\Models\Region;
use App\Models\Regency;
use App\Models\Employee;
use Illuminate\Validation\Rule;

usesPagination();
layout('layouts.app');

state([
    'search' => '',
    'filterRegion' => '',
    'filterCategory' => '',
    'sortBy' => 'name',
    'sortDirection' => 'asc',
    'contact_id' => null,
    'name' => '',
    'sumber' => '',
    'region_id' => '',
    'pic_employee_id' => '',
    'category' => '',
    'regency_id' => '',
    'contact_position_id' => '',
    'phone' => '',
    'email' => '',
    'isEdit' => false,
    'deleteTargetId' => null,
    'sumberOptions' => [
        'SDS Umum',
        'SDS Wilayah',
        'Referal',
        'Visit/ Pitching',
        'Media Sosial Instansi',
        'Customer Service'
    ],
    'categoryOptions' => [
        'Kementerian / Lembaga',
        'Pemerintah Daerah',
        'Non Pemerintah'
    ],
]);

$regionOptions = computed(fn() => Region::orderBy('name')->get());

$contactPositionOptions = computed(fn() => App\Models\ContactPosition::orderBy('name')->get());

$regencyOptions = computed(function () {
    return Regency::join('provinces', 'provinces.id', '=', 'regencies.province_id')
        ->select('regencies.id', 'regencies.name as regency_name', 'provinces.name as province_name')
        ->orderBy('regencies.name')
        ->get();
});

$isSuperAdmin = computed(fn() => auth()->user()?->email === 'supernakama@smartid.co.id');

$employeeOptions = computed(function () {
    $filtered = Employee::with('user')
        ->whereHas('department', fn($q) => $q->where('name', 'Revenue and Growth'))
        ->orWhereHas('positionStruktural', fn($q) => $q->where('name', 'like', '%Partnership%'))
        ->orWhereHas('positionFungsional', fn($q) => $q->where('name', 'like', '%Partnership%'))
        ->get();
    if ($filtered->isEmpty()) {
        return Employee::with('user')->get();
    }
    return $filtered;
});

$contacts = computed(function () {
    $search = $this->search;
    $filterRegion = $this->filterRegion;
    $filterCategory = $this->filterCategory;

    return Contact::query()
        ->with(['region', 'regency.province', 'pic.user', 'contactPosition'])
        ->when($search, fn($q) => $q->whereRaw('LOWER(contacts.name) LIKE ?', ['%' . strtolower($search) . '%']))
        ->when($filterRegion, fn($q) => $q->where('contacts.region_id', $filterRegion))
        ->when($filterCategory, fn($q) => $q->where('contacts.category', $filterCategory))
        ->orderBy($this->sortBy, $this->sortDirection)
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

$updatedFilterRegion = function () {
    $this->resetPage();
};

$updatedFilterCategory = function () {
    $this->resetPage();
};

$resetForm = function () {
    $this->reset([
        'contact_id',
        'name',
        'sumber',
        'region_id',
        'pic_employee_id',
        'category',
        'regency_id',
        'contact_position_id',
        'phone',
        'email',
        'isEdit'
    ]);
    $this->resetValidation();
};

$editContact = function ($id) {
    $this->resetForm();
    $contact = Contact::findOrFail($id);
    $this->contact_id = $contact->id;
    $this->name = $contact->name;
    $this->sumber = $contact->sumber;
    $this->region_id = $contact->region_id;
    $this->pic_employee_id = $contact->pic_employee_id;
    $this->category = $contact->category;
    $this->regency_id = $contact->regency_id;
    $this->contact_position_id = $contact->contact_position_id;
    $this->phone = $contact->phone;
    $this->email = $contact->email;
    $this->isEdit = true;
    Flux::modal('contact-modal')->show();
};

$saveContact = function () {
    $rules = [
        'name' => ['required', 'string', 'max:255'],
        'sumber' => ['required', 'in:' . implode(',', $this->sumberOptions)],
        'region_id' => ['required', 'exists:regions,id'],
        'category' => ['required', 'in:' . implode(',', $this->categoryOptions)],
        'regency_id' => ['required', 'exists:regencies,id'],
        'contact_position_id' => ['nullable', 'exists:contact_positions,id'],
        'phone' => ['nullable', 'string', 'max:30'],
        'email' => ['nullable', 'email', 'max:150'],
    ];

    if ($this->isSuperAdmin) {
        $rules['pic_employee_id'] = ['required', 'exists:employees,id'];
    }

    $this->validate($rules);

    $picId = $this->pic_employee_id;
    if (!$this->isSuperAdmin) {
        $employee = Employee::where('user_id', auth()->id())->first();
        $picId = $employee ? $employee->id : null;
    }

    Contact::updateOrCreate(
        ['id' => $this->contact_id],
        [
            'name' => $this->name,
            'sumber' => $this->sumber,
            'region_id' => $this->region_id,
            'pic_employee_id' => $picId,
            'category' => $this->category,
            'regency_id' => $this->regency_id,
            'contact_position_id' => $this->contact_position_id,
            'phone' => $this->phone,
            'email' => $this->email,
        ]
    );

    $isNew = is_null($this->contact_id);
    $this->resetForm();
    Flux::modal('contact-modal')->close();
    Flux::toast(
        variant: 'success',
        heading: $isNew ? 'Kontak ditambahkan' : 'Kontak diperbarui',
        text: 'Data berhasil disimpan.'
    );
};

$confirmDelete = function ($id) {
    $this->deleteTargetId = $id;
    Flux::modal('confirm-delete')->show();
};

$deleteContact = function ($id = null) {
    $targetId = $id ?: $this->deleteTargetId;
    if ($targetId) {
        Contact::findOrFail($targetId)->delete();
        Flux::toast(variant: 'danger', text: 'Kontak berhasil dihapus.');
    }
    Flux::modal('confirm-delete')->close();
    $this->reset(['deleteTargetId']);
};

?>

<div class="space-y-6">
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Manajemen Kontak</flux:heading>
                <flux:subheading>Kelola data kontak dari berbagai sumber kemitraan.</flux:subheading>
            </div>
            <flux:modal.trigger name="contact-modal">
                <flux:button variant="primary" icon="plus" size="sm" wire:click="resetForm">Tambah Kontak</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:separator variant="subtle" />

        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama kontak..." icon="magnifying-glass" clearable />
            </div>
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="filterRegion" placeholder="Semua Region">
                    <flux:select.option value="">Semua Region</flux:select.option>
                    @foreach ($this->regionOptions as $reg)
                        <flux:select.option value="{{ $reg->id }}">{{ $reg->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="filterCategory" placeholder="Semua Kategori">
                    <flux:select.option value="">Semua Kategori</flux:select.option>
                    @foreach ($categoryOptions as $cat)
                        <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <flux:table :paginate="$this->contacts" striped hoverable>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Nama</flux:table.column>
                <flux:table.column>Sumber</flux:table.column>
                <flux:table.column>Region</flux:table.column>
                <flux:table.column>Kabupaten/Kota</flux:table.column>
                <flux:table.column>Kategori</flux:table.column>
                <flux:table.column>Jabatan</flux:table.column>
                <flux:table.column>No HP</flux:table.column>
                <flux:table.column>Email</flux:table.column>
                <flux:table.column>PIC</flux:table.column>
                <flux:table.column align="right">Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->contacts as $contact)
                    <flux:table.row :key="$contact->id">
                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">{{ $contact->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="pill" color="blue">{{ $contact->sumber }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $contact->region?->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $contact->regency?->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $contact->category }}</flux:table.cell>
                        <flux:table.cell>{{ $contact->contactPosition?->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $contact->phone ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $contact->email ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="pill" color="zinc">{{ $contact->pic?->user?->name ?? '-' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="right">
                            <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom" wire:click="editContact({{ $contact->id }})" />
                            <flux:button variant="danger" size="sm" icon="trash" inset="top bottom" wire:click="confirmDelete({{ $contact->id }})" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10" class="text-center text-zinc-500 py-8">
                            Belum ada data kontak.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="contact-modal" class="md:w-[500px]">
        <form wire:submit="saveContact" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEdit ? 'Edit Kontak' : 'Tambah Kontak' }}</flux:heading>
                <flux:subheading>Silakan isi informasi kontak dengan lengkap.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:field class="col-span-2">
                    <flux:label>Nama Lengkap</flux:label>
                    <flux:input wire:model="name" placeholder="Cth: John Doe" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Sumber Kontak</flux:label>
                    <flux:select wire:model="sumber" placeholder="Pilih Sumber">
                        <flux:select.option value="">Pilih Sumber</flux:select.option>
                        @foreach ($sumberOptions as $opt)
                            <flux:select.option value="{{ $opt }}">{{ $opt }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="sumber" />
                </flux:field>

                <flux:field>
                    <flux:label>Region</flux:label>
                    <flux:select wire:model="region_id" placeholder="Pilih Region">
                        <flux:select.option value="">Pilih Region</flux:select.option>
                        @foreach ($this->regionOptions as $reg)
                            <flux:select.option value="{{ $reg->id }}">{{ $reg->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="region_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Kategori Instansi</flux:label>
                    <flux:select wire:model="category" placeholder="Pilih Kategori">
                        <flux:select.option value="">Pilih Kategori</flux:select.option>
                        @foreach ($categoryOptions as $cat)
                            <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="category" />
                </flux:field>

                <flux:field>
                    <flux:label>Kabupaten / Kota</flux:label>
                    <flux:select wire:model="regency_id" variant="listbox" searchable placeholder="Pilih Kabupaten / Kota...">
                        <flux:select.option value="">Pilih Kabupaten / Kota</flux:select.option>
                        @foreach ($this->regencyOptions as $reg)
                            <flux:select.option value="{{ $reg->id }}">{{ $reg->regency_name }} - {{ $reg->province_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="regency_id" />
                </flux:field>

                <flux:field class="col-span-2">
                    <flux:label>Jabatan</flux:label>
                    <flux:select wire:model="contact_position_id" placeholder="Pilih Jabatan" searchable>
                        <flux:select.option value="">Pilih Jabatan</flux:select.option>
                        @foreach ($this->contactPositionOptions as $pos)
                            <flux:select.option value="{{ $pos->id }}">{{ $pos->name }} ({{ $pos->eselon ?? '-' }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="contact_position_id" />
                </flux:field>

                <flux:field>
                    <flux:label>No HP</flux:label>
                    <flux:input wire:model="phone" placeholder="Cth: 08123456789" />
                    <flux:error name="phone" />
                </flux:field>

                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input wire:model="email" type="email" placeholder="Cth: name@example.com" />
                    <flux:error name="email" />
                </flux:field>

                @if ($this->isSuperAdmin)
                    <flux:field class="col-span-2">
                        <flux:label>PIC (Superadmin Only)</flux:label>
                        <flux:select wire:model="pic_employee_id" variant="listbox" searchable placeholder="Pilih PIC...">
                            <flux:select.option value="">Pilih PIC</flux:select.option>
                            @foreach ($this->employeeOptions as $emp)
                                <flux:select.option value="{{ $emp->id }}">{{ $emp->user?->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="pic_employee_id" />
                    </flux:field>
                @endif
            </div>

            <div class="flex justify-end space-x-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-delete" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Hapus Kontak?</flux:heading>
                <flux:text class="mt-1">Tindakan ini tidak bisa dibatalkan.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteContact()">Ya, Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
