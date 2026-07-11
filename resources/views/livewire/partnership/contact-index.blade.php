<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Models\Contact;
use App\Models\Region;
use App\Models\Regency;
use App\Models\Province;
use App\Models\Opd;
use App\Models\Employee;
use Illuminate\Validation\Rule;

usesPagination();
layout('layouts.app');

state([
    'search' => '',
    'filterRegion' => '',
    'filterCategory' => '',
    'filterKelompokJabatan' => '',
    'sortBy' => 'name',
    'sortDirection' => 'asc',
    'contact_id' => null,
    'name' => '',
    'sumber' => '',
    'region_id' => '',
    'pic_employee_id' => '',
    'category' => '',
    'province_id' => '',
    'regency_id' => '',
    'opd_id' => '',
    'contact_position_id' => '',
    'phone' => '',
    'email' => '',
    'isEdit' => false,
    'deleteTargetId' => null,
    'sumberOptions' => ['SDS Umum', 'SDS Wilayah', 'Referal', 'Visit/ Pitching', 'Media Sosial Instansi', 'Customer Service'],
    'categoryOptions' => ['Kementerian / Lembaga', 'Pemerintah Daerah', 'Non Pemerintah'],
    'showContactModal' => false,
    'showDeleteModal' => false,
]);

$regionOptions = computed(fn() => Region::orderBy('name')->get());

$contactPositionOptions = computed(fn() => App\Models\ContactPosition::orderBy('name')->get());

$kelompokJabatanOptions = computed(fn() => App\Models\ContactPosition::whereNotNull('kelompok_jabatan')->where('kelompok_jabatan', '<>', '')->distinct()->orderBy('kelompok_jabatan')->pluck('kelompok_jabatan'));

$provinceOptions = computed(fn() => Province::orderBy('name')->get());

$regencyOptions = computed(function () {
    if (!$this->province_id) {
        return collect();
    }
    return Regency::where('province_id', $this->province_id)->orderBy('name')->get();
});

$opdOptions = computed(function () {
    if (!$this->regency_id) {
        return collect();
    }
    return Opd::where('regency_id', $this->regency_id)->orderBy('name')->get();
});

$updatedProvinceId = function ($value) {
    $this->regency_id = '';
    $this->opd_id = '';
};

$updatedRegencyId = function ($value) {
    $this->opd_id = '';
};

$isSuperAdmin = computed(fn() =>
    auth()->user()?->email === 'supernakama@smartid.co.id' ||
    auth()->user()->hasRole('Super Admin')
);

// PMO: only their own (PIC'd) contacts. SPV (Partnership Admin): contacts within their region ("team"). Super Admin: everything.
$viewerScope = computed(function () {
    $user = auth()->user();
    $employee = $user->employee;
    $isSuper = $this->isSuperAdmin;
    $isSpv = !$isSuper && $user->hasRole('Partnership Admin');

    return [
        'employee' => $employee,
        'isSuper' => $isSuper,
        'isSpv' => $isSpv,
        'spvRegionId' => $isSpv ? $employee?->region_id : null,
    ];
});

$employeeOptions = computed(function () {
    $filtered = Employee::with('user')->whereHas('department', fn($q) => $q->where('name', 'Revenue and Growth'))->orWhereHas('positionStruktural', fn($q) => $q->where('name', 'like', '%Partnership%'))->orWhereHas('positionFungsional', fn($q) => $q->where('name', 'like', '%Partnership%'))->get();
    if ($filtered->isEmpty()) {
        return Employee::with('user')->get();
    }
    return $filtered;
});

$contacts = computed(function () {
    $search = $this->search;
    $filterRegion = $this->filterRegion;
    $filterCategory = $this->filterCategory;
    $filterKelompok = $this->filterKelompokJabatan;

    ['employee' => $employee, 'isSuper' => $isSuper, 'isSpv' => $isSpv, 'spvRegionId' => $spvRegionId] = $this->viewerScope;

    return Contact::query()
        ->with(['region', 'regency.province', 'opd', 'pic.user', 'contactPosition'])
        ->when($isSpv && $spvRegionId, fn($q) => $q->where('pmo_contacts.region_id', $spvRegionId))
            ->when(!$isSuper && !$isSpv && $employee, fn($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id))
        ->when($search, fn($q) => $q->whereRaw('LOWER(pmo_contacts.name) LIKE ?', ['%' . strtolower($search) . '%']))
        ->when($filterRegion, fn($q) => $q->where('pmo_contacts.region_id', $filterRegion))
        ->when($filterCategory, fn($q) => $q->where('pmo_contacts.category', $filterCategory))
        ->when($filterKelompok, function ($q) use ($filterKelompok) {
            $q->whereHas('contactPosition', fn($qp) => $qp->where('kelompok_jabatan', $filterKelompok));
        })
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

$updatedFilterKelompokJabatan = function () {
    $this->resetPage();
};

$resetForm = function () {
    $this->reset(['contact_id', 'name', 'sumber', 'region_id', 'pic_employee_id', 'category', 'province_id', 'regency_id', 'opd_id', 'contact_position_id', 'phone', 'email', 'isEdit']);
    $this->resetValidation();
};

$showCreate = function () {
    $this->resetForm();
    $employee = \App\Models\Employee::where('user_id', auth()->id())->first();
    $isSuper = auth()->user()?->email === 'supernakama@smartid.co.id' || auth()->user()->hasRole('Super Admin');
    if (!$isSuper && $employee) {
        $assignedRegionId = DB::table('pmo_employee_regions')->where('employee_id', $employee->id)->value('region_id');
        if ($assignedRegionId) {
            $this->region_id = $assignedRegionId;
        }
    }
    $this->showContactModal = true;
};

$editContact = function ($id) {
    $this->resetForm();
    $contact = Contact::with('regency')->findOrFail($id);
    $this->contact_id = $contact->id;
    $this->name = $contact->name;
    $this->sumber = $contact->sumber;
    
    $employee = \App\Models\Employee::where('user_id', auth()->id())->first();
    $isSuper = auth()->user()?->email === 'supernakama@smartid.co.id' || auth()->user()->hasRole('Super Admin');
    if (!$isSuper && $employee) {
        $assignedRegionId = DB::table('pmo_employee_regions')->where('employee_id', $employee->id)->value('region_id');
        $this->region_id = $assignedRegionId ?: $contact->region_id;
    } else {
        $this->region_id = $contact->region_id;
    }

    $this->pic_employee_id = $contact->pic_employee_id;
    $this->category = $contact->category;
    if ($contact->regency) {
        $this->province_id = $contact->regency->province_id;
    }
    $this->regency_id = $contact->regency_id;
    $this->opd_id = $contact->opd_id;
    $this->contact_position_id = $contact->contact_position_id;
    $this->phone = $contact->phone;
    $this->email = $contact->email;
    $this->isEdit = true;
    $this->showContactModal = true;
};

$saveContact = function () {
    $rules = [
        'name' => ['required', 'string', 'max:255'],
        'sumber' => ['required', 'in:' . implode(',', $this->sumberOptions)],
        'region_id' => ['required', 'exists:regions,id'],
        'category' => ['required', 'in:' . implode(',', $this->categoryOptions)],
        'province_id' => ['required', 'exists:provinces,id'],
        'regency_id' => ['required', 'exists:regencies,id'],
        'opd_id' => ['nullable', 'exists:pmo_opds,id'],
        'contact_position_id' => ['nullable', 'exists:pmo_contact_positions,id'],
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
            'opd_id' => $this->opd_id ?: null,
            'contact_position_id' => $this->contact_position_id,
            'phone' => $this->phone,
            'email' => $this->email,
        ],
    );

    $isNew = is_null($this->contact_id);
    $this->resetForm();
    $this->showContactModal = false;
    session()->flash('success', $isNew ? 'Kontak berhasil ditambahkan!' : 'Kontak berhasil diperbarui!');
};

$confirmDelete = function ($id) {
    $this->deleteTargetId = $id;
    $this->showDeleteModal = true;
};

$deleteContact = function ($id = null) {
    $targetId = $id ?: $this->deleteTargetId;
    if ($targetId) {
        Contact::findOrFail($targetId)->delete();
        session()->flash('error', 'Kontak berhasil dihapus.');
    }
    $this->showDeleteModal = false;
    $this->reset(['deleteTargetId']);
};

$statsCategory = computed(function () {
    ['employee' => $employee, 'isSuper' => $isSuper, 'isSpv' => $isSpv, 'spvRegionId' => $spvRegionId] = $this->viewerScope;

    $total =
        Contact::query()
            ->when($isSpv && $spvRegionId, fn($q) => $q->where('pmo_contacts.region_id', $spvRegionId))
            ->when(!$isSuper && !$isSpv && $employee, fn($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id))
            ->count() ?:
        1;

    $categories = Contact::query()
        ->when($isSpv && $spvRegionId, fn($q) => $q->where('pmo_contacts.region_id', $spvRegionId))
            ->when(!$isSuper && !$isSpv && $employee, fn($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id))
        ->selectRaw('category, count(*) as count')
        ->groupBy('category')
        ->get();

    $colors = [
        'Kementerian / Lembaga' => 'blue',
        'Pemerintah Daerah' => 'emerald',
        'Non Pemerintah' => 'amber',
    ];

    return $categories->map(function ($cat) use ($total, $colors) {
        $name = $cat->category ?: 'Non Pemerintah';
        return [
            'name' => $name,
            'count' => $cat->count,
            'percentage' => round(($cat->count / $total) * 100, 1),
            'color' => $colors[$name] ?? 'zinc',
        ];
    });
});

$statsRegion = computed(function () {
    ['employee' => $employee, 'isSuper' => $isSuper, 'isSpv' => $isSpv, 'spvRegionId' => $spvRegionId] = $this->viewerScope;

    $total =
        Contact::query()
            ->when($isSpv && $spvRegionId, fn($q) => $q->where('pmo_contacts.region_id', $spvRegionId))
            ->when(!$isSuper && !$isSpv && $employee, fn($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id))
            ->whereNotNull('region_id')
            ->count() ?:
        1;

    $regions = Contact::query()
        ->when($isSpv && $spvRegionId, fn($q) => $q->where('pmo_contacts.region_id', $spvRegionId))
            ->when(!$isSuper && !$isSpv && $employee, fn($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id))
        ->whereNotNull('region_id')
        ->selectRaw('region_id, count(*) as count')
        ->groupBy('region_id')
        ->with('region')
        ->orderByDesc('count')
        ->get();

    $top = $regions->first();

    return [
        'top_name' => $top?->region?->name ?? '-',
        'top_percentage' => $top ? round(($top->count / $total) * 100, 1) : 0,
        'regions' => $regions,
    ];
});

$statsProvince = computed(function () {
    ['employee' => $employee, 'isSuper' => $isSuper, 'isSpv' => $isSpv, 'spvRegionId' => $spvRegionId] = $this->viewerScope;

    $total = Contact::query()
        ->when($isSpv && $spvRegionId, fn($q) => $q->where('pmo_contacts.region_id', $spvRegionId))
            ->when(!$isSuper && !$isSpv && $employee, fn($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id))
        ->join('regencies', 'pmo_contacts.regency_id', '=', 'regencies.id')
        ->join('provinces', 'regencies.province_id', '=', 'provinces.id')
        ->count() ?: 1;

    $provinces = Contact::query()
        ->when($isSpv && $spvRegionId, fn($q) => $q->where('pmo_contacts.region_id', $spvRegionId))
            ->when(!$isSuper && !$isSpv && $employee, fn($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id))
        ->join('regencies', 'pmo_contacts.regency_id', '=', 'regencies.id')
        ->join('provinces', 'regencies.province_id', '=', 'provinces.id')
        ->selectRaw('provinces.name as province_name, count(*) as count')
        ->groupBy('provinces.name')
        ->orderByDesc('count')
        ->get();

    return [
        'total' => $total,
        'provinces' => $provinces,
    ];
});

$statsPosition = computed(function () {
    ['employee' => $employee, 'isSuper' => $isSuper, 'isSpv' => $isSpv, 'spvRegionId' => $spvRegionId] = $this->viewerScope;

    $total =
        Contact::query()
            ->when($isSpv && $spvRegionId, fn($q) => $q->where('pmo_contacts.region_id', $spvRegionId))
            ->when(!$isSuper && !$isSpv && $employee, fn($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id))
            ->whereNotNull('contact_position_id')
            ->count() ?:
        1;

    $positions = Contact::query()
        ->when($isSpv && $spvRegionId, fn($q) => $q->where('pmo_contacts.region_id', $spvRegionId))
            ->when(!$isSuper && !$isSpv && $employee, fn($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id))
        ->whereNotNull('contact_position_id')
        ->selectRaw('contact_position_id, count(*) as count')
        ->groupBy('contact_position_id')
        ->with('contactPosition')
        ->orderByDesc('count')
        ->get();

    $top = $positions->first();

    return [
        'top_name' => $top?->contactPosition?->name ?? '-',
        'top_percentage' => $top ? round(($top->count / $total) * 100, 1) : 0,
        'positions' => $positions,
    ];
});

$contactsCount = computed(function () {
    ['employee' => $employee, 'isSuper' => $isSuper, 'isSpv' => $isSpv, 'spvRegionId' => $spvRegionId] = $this->viewerScope;

    return Contact::query()
        ->when($isSpv && $spvRegionId, fn($q) => $q->where('pmo_contacts.region_id', $spvRegionId))
            ->when(!$isSuper && !$isSpv && $employee, fn($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id))
        ->count();
});

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

    <!-- Mini Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Total Kontak & Kategori -->
        <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-sm rounded-2xl">
            <div>
                <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">TOTAL KONTAK & KATEGORI</h3>
                <h2 class="text-2xl mt-1 font-extrabold text-base-content">{{ $this->contactsCount }} Kontak
                </h2>
            </div>

            <!-- Stacked Horizontal Bar Chart -->
            <div class="h-4 w-full bg-base-200 rounded-full overflow-hidden flex">
                @foreach ($this->statsCategory as $cat)
                    @if ($cat['count'] > 0)
                        <div class="h-full bg-{{ $cat['color'] }}-500" style="width: {{ $cat['percentage'] }}%"
                            title="{{ $cat['name'] }}: {{ $cat['count'] }} ({{ $cat['percentage'] }}%)"></div>
                    @endif
                @endforeach
            </div>

            <!-- Legend Grid -->
            <div class="grid grid-cols-1 gap-y-1 text-xs">
                @foreach ($this->statsCategory as $cat)
                    @if ($cat['count'] > 0)
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-{{ $cat['color'] }}-500 inline-block"></span>
                            <span class="text-base-content/70 truncate">{{ $cat['name'] }}
                                ({{ $cat['count'] }})
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Card 2: Region Terbanyak / Sebaran Provinsi -->
        @if ($this->isSuperAdmin)
            <div
                class="card bg-base-100 border border-base-300 p-6 space-y-4 flex flex-col justify-between shadow-sm rounded-2xl">
                <div>
                    <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">REGION TERBANYAK</h3>
                    <h2 class="text-xl mt-1 font-extrabold text-base-content">
                        {{ $this->statsRegion['top_name'] }}
                    </h2>
                    <p class="text-xs text-base-content/40 mt-0.5">
                        Merupakan asal region dari {{ $this->statsRegion['top_percentage'] }}% total kontak.
                    </p>
                </div>

                <div class="space-y-1.5 pt-2">
                    @foreach ($this->statsRegion['regions']->take(3) as $reg)
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-base-content/70">{{ $reg->region?->name ?? 'Lainnya' }}</span>
                            <span class="text-base-content/50 font-semibold">{{ $reg->count }} Kontak</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div
                class="card bg-base-100 border border-base-300 p-6 space-y-4 flex flex-col justify-between shadow-sm rounded-2xl">
                <div>
                    <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">SEBARAN PROVINSI</h3>
                    <h2 class="text-xl mt-1 font-extrabold text-base-content">
                        {{ $this->statsProvince['provinces']->first()?->province_name ?? '-' }}
                    </h2>
                    <p class="text-xs text-base-content/40 mt-0.5">
                        Provinsi dengan kontak terbanyak saat ini.
                    </p>
                </div>

                <div class="space-y-2 pt-2 overflow-y-auto max-h-[120px] pr-1">
                    @foreach ($this->statsProvince['provinces']->take(4) as $prov)
                        @php
                            $percentage = round(($prov->count / $this->statsProvince['total']) * 100, 1);
                        @endphp
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-base-content/70 truncate max-w-[150px]">{{ $prov->province_name }}</span>
                                <span class="text-base-content/50 font-semibold">{{ $prov->count }} ({{ $percentage }}%)</span>
                            </div>
                            <div class="w-full bg-base-200 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-primary h-full rounded-full" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Card 3: Jabatan Terbanyak -->
        <div
            class="card bg-base-100 border border-base-300 p-6 space-y-4 flex flex-col justify-between shadow-sm rounded-2xl">
            <div>
                <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">JABATAN TERBANYAK</h3>
                <h2 class="text-xl mt-1 font-extrabold text-base-content">
                    {{ $this->statsPosition['top_name'] }}
                </h2>
                <p class="text-xs text-base-content/40 mt-0.5">
                    Merupakan jabatan dari {{ $this->statsPosition['top_percentage'] }}% total kontak.
                </p>
            </div>

            <div class="space-y-1.5 pt-2">
                @foreach ($this->statsPosition['positions']->take(3) as $pos)
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-base-content/70 truncate max-w-[180px]"
                            title="{{ $pos->contactPosition?->name ?? 'Lainnya' }}">
                            {{ $pos->contactPosition?->name ?? 'Lainnya' }}
                        </span>
                        <span class="text-base-content/50 font-semibold">{{ $pos->count }} Kontak</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300 p-6 space-y-6 shadow-sm rounded-2xl">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-base-content">Manajemen Kontak</h2>
                <p class="text-sm text-base-content/50 mt-1">Kelola data kontak dari berbagai sumber kemitraan.</p>
            </div>
            <x-button variant="primary" icon="o-plus" size="btn-sm" label="Tambah Kontak"
                wire:click="showCreate" />
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="w-full sm:w-48">
                <x-input wire:model.live.debounce.300ms="search" placeholder="Cari nama kontak..."
                    icon="o-magnifying-glass" clearable />
            </div>
            <div class="w-full sm:w-48">
                <x-select wire:model.live="filterRegion" :options="$this->regionOptions" placeholder="Semua Region"
                    placeholder-value="" />
            </div>
            <div class="w-full sm:w-48">
                @php
                    $jabatanOpts = $this->kelompokJabatanOptions->map(fn($k) => ['id' => $k, 'name' => $k]);
                @endphp
                <x-select wire:model.live="filterKelompokJabatan" :options="$jabatanOpts" placeholder="Kelompok Jabatan"
                    placeholder-value="" />
            </div>
            <div class="w-full sm:w-48">
                @php
                    $catOpts = collect($categoryOptions)->map(fn($c) => ['id' => $c, 'name' => $c]);
                @endphp
                <x-select wire:model.live="filterCategory" :options="$catOpts" placeholder="Semua Kategori"
                    placeholder-value="" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th class="cursor-pointer py-3" wire:click="sort('name')">
                            <div class="flex items-center gap-1">
                                <span>Nama</span>
                                @if ($sortBy === 'name')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}"
                                        class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                        <th>Sumber</th>
                        <th>Region</th>
                        <th>Kabupaten/Kota</th>
                        <th>Instansi</th>
                        <th>Kategori</th>
                        <th>Jabatan</th>
                        <th>No HP</th>
                        <th>PIC</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->contacts as $contact)
                        <tr class="border-b border-base-300 hover:bg-base-200">
                            <td class="font-semibold text-base-content py-3">
                                {{ $contact->name }}
                                @if ($contact->is_ql)
                                    <span class="badge badge-success badge-xs font-bold ml-1 text-white text-[9px] rounded">QL</span>
                                @endif
                            </td>
                            <td>
                                <div class="badge badge-sm badge-info badge-soft">{{ $contact->sumber }}</div>
                            </td>
                            <td>{{ $contact->region?->id ?? '-' }}</td>
                            <td>{{ $contact->regency?->name ?? '-' }}</td>
                            <td class="max-w-[200px] truncate" title="{{ $contact->opd?->name ?? '-' }}">
                                {{ $contact->opd?->name ?? '-' }}</td>
                            <td>{{ $contact->category }}</td>
                            <td>{{ $contact->contactPosition?->name ?? '-' }}</td>
                            <td>{{ $contact->phone ?? '-' }}</td>
                            <td>
                                @php
                                    $fullName = $contact->pic?->user?->name ?? '-';
                                    $displayName = str($fullName)->words(2, '...');
                                    $isLongName = count(explode(' ', trim($fullName))) > 2;
                                @endphp

                                @if ($isLongName)
                                    <div class="tooltip tooltip-top" data-tip="{{ $fullName }}">
                                        <div class="badge badge-sm badge-neutral cursor-help">{{ $displayName }}</div>
                                    </div>
                                @else
                                    <div class="badge badge-sm badge-neutral">{{ $fullName }}</div>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex gap-2 justify-end">
                                    <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50"
                                        wire:click="editContact({{ $contact->id }})" />
                                    <x-button icon="o-trash" class="btn-ghost btn-xs text-error"
                                        wire:click="confirmDelete({{ $contact->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-base-content/50 py-8">
                                Belum ada data kontak.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->contacts->links() }}
        </div>
    </div>

    <!-- Contact Form Modal -->
    <x-modal wire:model="showContactModal" title="{{ $isEdit ? 'Edit Kontak' : 'Tambah Kontak' }}"
        subtitle="Silakan isi informasi kontak dengan lengkap.">
        <form wire:submit.prevent="saveContact" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="col-span-2">
                    <x-input label="Nama Lengkap" wire:model="name" placeholder="Cth: John Doe" required />
                </div>

                @php
                    $sumberOpts = collect($sumberOptions)->map(fn($o) => ['id' => $o, 'name' => $o]);
                    $catOptsForm = collect($categoryOptions)->map(fn($o) => ['id' => $o, 'name' => $o]);
                @endphp

                <x-select label="Sumber Kontak" wire:model="sumber" :options="$sumberOpts" placeholder="Pilih Sumber" />
                <x-select label="Region" wire:model="region_id" :options="$this->regionOptions" placeholder="Pilih Region" :disabled="!$this->isSuperAdmin" />

                <x-select label="Kategori Instansi" wire:model="category" :options="$catOptsForm"
                    placeholder="Pilih Kategori" />
                <x-select label="Provinsi" wire:model.live="province_id" :options="$this->provinceOptions"
                    placeholder="Pilih Provinsi..." />

                <x-select label="Kabupaten / Kota" wire:model.live="regency_id" :options="$this->regencyOptions"
                    placeholder="Pilih Kabupaten / Kota..." :disabled="!$province_id" />

                <div class="col-span-2">
                    <x-select label="Instansi (OPD)" wire:model="opd_id" :options="$this->opdOptions"
                        placeholder="Pilih Instansi..." :disabled="!$regency_id" />
                </div>

                @php
                    $posOpts = $this->contactPositionOptions->map(
                        fn($p) => ['id' => $p->id, 'name' => $p->name . ' (' . ($p->eselon ?? '-') . ')'],
                    );
                @endphp
                <div class="col-span-2">
                    <x-select label="Jabatan" wire:model="contact_position_id" :options="$posOpts"
                        placeholder="Pilih Jabatan" />
                </div>

                <x-input label="No HP" wire:model="phone" placeholder="Cth: 08123456789" />
                <x-input label="Email" wire:model="email" type="email" placeholder="Cth: name@example.com" />

                @if ($this->isSuperAdmin)
                    @php
                        $picOpts = $this->employeeOptions->map(fn($e) => ['id' => $e->id, 'name' => $e->user?->name]);
                    @endphp
                    <div class="col-span-2">
                        <x-select label="PIC (Superadmin Only)" wire:model="pic_employee_id" :options="$picOpts"
                            placeholder="Pilih PIC..." />
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                <x-button label="Batal" wire:click="$set('showContactModal', false)" class="btn-ghost" />
                <x-button label="Simpan" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Confirmation Modal -->
    <x-modal wire:model="showDeleteModal" title="Hapus Kontak?" subtitle="Tindakan ini tidak bisa dibatalkan.">
        <div class="flex justify-end gap-2 pt-4">
            <x-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
            <x-button label="Ya, Hapus" wire:click="deleteContact" class="btn-error text-white" />
        </div>
    </x-modal>
</div>
