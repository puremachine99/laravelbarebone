<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use Flux\Flux;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

usesPagination();
layout('layouts.app');

state([
    'search' => '',
    'filterDept' => '',
]);

state([
    'sortBy' => 'name',
    'sortDirection' => 'asc',
]);

state([
    'employee_id' => null,
    'isEdit' => false,
    'deleteTargetId' => null,

    'name' => '',
    'email' => '',
    'password' => '',
    'department_id' => '',
    'position_struktural_id' => '',
    'position_fungsional_id' => '',
    'employment_status' => '',
    'is_cltp' => false,

    'selectedEmployee' => null,
]);

$departmentOptions = computed(fn() => Department::orderBy('name')->get());
$positionOptions = computed(fn() => Position::orderBy('name')->get());

$employees = computed(function () {
    $search     = $this->search;
    $filterDept = $this->filterDept;

    return Employee::query()
        ->with(['user', 'department', 'positionFungsional', 'positionStruktural'])
        ->join('users', 'users.id', '=', 'employees.user_id')
        ->select('employees.*')
        ->when($search, fn($q) =>
            $q->where(fn($q) =>
                $q->whereRaw('LOWER(users.name) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(users.email) LIKE ?', ['%' . strtolower($search) . '%'])
            )
        )
        ->when($filterDept, fn($q) => $q->where('employees.department_id', $filterDept))
        ->when($this->sortBy, function ($q) {
            $sortableOnUsers = ['name', 'email'];
            $column = in_array($this->sortBy, $sortableOnUsers)
                ? 'users.' . $this->sortBy
                : 'employees.' . $this->sortBy;
            $q->orderBy($column, $this->sortDirection);
        })
        ->paginate(10);
});

$statsStatus = computed(function () {
    $total = Employee::count() ?: 1;
    $statuses = [
        'PKWTT' => 'green',
        'PKWT' => 'yellow',
        'Internship' => 'blue',
        'Mitra Strategis' => 'purple',
        'Bond Agreement' => 'orange',
    ];

    $result = [];
    foreach ($statuses as $status => $color) {
        $count = Employee::where('employment_status', $status)->count();
        $result[$status] = [
            'count' => $count,
            'percentage' => round(($count / $total) * 100, 1),
            'color' => $color,
        ];
    }

    $cltpCount = Employee::where('is_cltp', true)->count();
    $result['CLTP'] = [
        'count' => $cltpCount,
        'percentage' => round(($cltpCount / $total) * 100, 1),
        'color' => 'red',
    ];

    return $result;
});

$statsEducation = computed(function () {
    $total = Employee::whereNotNull('education_level')->count() ?: 1;
    $levels = Employee::whereNotNull('education_level')
        ->selectRaw('education_level, count(*) as count')
        ->groupBy('education_level')
        ->orderByDesc('count')
        ->get();

    $top = $levels->first();

    return [
        'top_level' => $top ? $top->education_level : '-',
        'top_percentage' => $top ? round(($top->count / $total) * 100, 1) : 0,
        'levels' => $levels,
    ];
});

$statsAge = computed(function () {
    $birthDates = Employee::whereNotNull('birth_date')->pluck('birth_date');
    if ($birthDates->isEmpty()) {
        return ['avg' => 0, 'min' => 0, 'max' => 0];
    }

    $ages = $birthDates->map(fn($date) => Carbon::parse($date)->age);

    return [
        'avg' => (int) $ages->average(),
        'min' => $ages->min(),
        'max' => $ages->max(),
    ];
});

$showDetail = function ($id) {
    $this->selectedEmployee = Employee::with(['user', 'department', 'positionFungsional', 'positionStruktural'])->find($id);
    $this->js('$dispatch("open-drawer")');
};

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

$updatedFilterDept = function () {
    $this->resetPage();
};

$resetForm = function () {
    $this->reset(['employee_id', 'isEdit', 'name', 'email', 'password', 'department_id', 'position_struktural_id', 'position_fungsional_id', 'employment_status', 'is_cltp']);
    $this->resetValidation();
};

$save = function () {
    $this->validate([
        'name'                   => ['required', 'string', 'max:255'],
        'email'                  => ['required', 'email', Rule::unique('users', 'email')->ignore(
            $this->employee_id
                ? Employee::find($this->employee_id)?->user_id
                : null
        )],
        'password'               => [$this->isEdit ? 'nullable' : 'required', 'string', 'min:8'],
        'department_id'          => ['nullable', 'exists:departments,id'],
        'position_struktural_id' => ['nullable', 'exists:positions,id'],
        'position_fungsional_id' => ['nullable', 'exists:positions,id'],
        'employment_status'      => ['nullable', 'in:PKWT,PKWTT,Internship,Mitra Strategis,Bond Agreement'],
    ]);

    $isNew = is_null($this->employee_id);

    $userData = [
        'name'  => $this->name,
        'email' => $this->email,
    ];
    if ($this->password) {
        $userData['password'] = Hash::make($this->password);
    }

    $employeeData = [
        'department_id'          => $this->department_id ?: null,
        'position_struktural_id' => $this->position_struktural_id ?: null,
        'position_fungsional_id' => $this->position_fungsional_id ?: null,
        'employment_status'      => $this->employment_status ?: null,
        'is_cltp'                => $this->is_cltp,
    ];

    if ($isNew) {
        $userData['password'] = Hash::make($this->password);
        $user = \App\Models\User::create($userData);
        Employee::create(array_merge($employeeData, ['user_id' => $user->id]));
    } else {
        $employee = Employee::findOrFail($this->employee_id);
        $employee->user->update($userData);
        $employee->update($employeeData);
    }

    $this->resetForm();
    Flux::modal('employee-modal')->close();
    Flux::toast(
        variant: 'success',
        heading: $isNew ? 'Karyawan ditambahkan' : 'Data karyawan diperbarui',
        text:    $isNew ? 'Data baru berhasil disimpan.' : 'Perubahan berhasil disimpan.',
    );
};

$edit = function (Employee $employee) {
    $this->resetValidation();
    $this->employee_id            = $employee->id;
    $this->name                   = $employee->user->name;
    $this->email                  = $employee->user->email;
    $this->password               = '';
    $this->department_id          = $employee->department_id ?? '';
    $this->position_struktural_id = $employee->position_struktural_id ?? '';
    $this->position_fungsional_id = $employee->position_fungsional_id ?? '';
    $this->employment_status      = $employee->employment_status ?? '';
    $this->is_cltp                = $employee->is_cltp;
    $this->isEdit                 = true;
    Flux::modal('employee-modal')->show();
};

$confirmDelete = function (string $id) {
    $this->deleteTargetId = $id;
    Flux::modal('confirm-delete')->show();
};

$delete = function () {
    Employee::findOrFail($this->deleteTargetId)->delete();
    $this->deleteTargetId = null;
    Flux::modal('confirm-delete')->close();
    Flux::toast(variant: 'danger', text: 'Karyawan berhasil dihapus.');
};

?>
<div x-data="{ openDrawer: false }" @open-drawer.window="openDrawer = true" @close-drawer.window="openDrawer = false" class="space-y-6">
    <!-- Mini Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Total Karyawan & Status -->
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="sm" class="text-zinc-500 font-medium">TOTAL KARYAWAN & STATUS</flux:heading>
                <flux:heading size="xl" class="mt-1 font-bold">{{ Employee::count() }} Karyawan</flux:heading>
            </div>
            
            <!-- Stacked Horizontal Bar Chart -->
            <div class="h-4 w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden flex">
                @foreach ($this->statsStatus as $status => $info)
                    @if ($info['count'] > 0)
                        <div class="h-full bg-{{ $info['color'] }}-500" 
                             style="width: {{ $info['percentage'] }}%" 
                             title="{{ $status }}: {{ $info['count'] }} ({{ $info['percentage'] }}%)"></div>
                    @endif
                @endforeach
            </div>

            <!-- Legend Grid -->
            <div class="grid grid-cols-2 gap-x-2 gap-y-1 text-xs">
                @foreach ($this->statsStatus as $status => $info)
                    @if ($info['count'] > 0)
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-{{ $info['color'] }}-500 inline-block"></span>
                            <span class="text-zinc-600 dark:text-zinc-300 truncate">{{ $status }} ({{ $info['count'] }})</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </flux:card>

        <!-- Card 2: Pendidikan Terbanyak -->
        <flux:card class="space-y-4 flex flex-col justify-between">
            <div>
                <flux:heading size="sm" class="text-zinc-500 font-medium">PENDIDIKAN TERBANYAK</flux:heading>
                <flux:heading size="xl" class="mt-1 font-bold">
                    {{ $this->statsEducation['top_level'] }}
                </flux:heading>
                <flux:subheading class="mt-0.5">
                    Merupakan tingkat pendidikan dari {{ $this->statsEducation['top_percentage'] }}% total karyawan.
                </flux:subheading>
            </div>

            <div class="space-y-1.5 pt-2">
                @foreach ($this->statsEducation['levels']->take(3) as $lvl)
                    <div class="flex items-center justify-between text-xs">
                        <flux:text class="font-medium">{{ $lvl->education_level }}</flux:text>
                        <flux:text class="text-zinc-500">{{ $lvl->count }} Karyawan</flux:text>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <!-- Card 3: Profil Usia -->
        <flux:card class="space-y-4 flex flex-col justify-between">
            <div>
                <flux:heading size="sm" class="text-zinc-500 font-medium">RATA-RATA USIA</flux:heading>
                <flux:heading size="xl" class="mt-1 font-bold">{{ $this->statsAge['avg'] }} Tahun</flux:heading>
                <flux:subheading class="mt-0.5">
                    Rentang usia berkisar dari {{ $this->statsAge['min'] }} hingga {{ $this->statsAge['max'] }} tahun.
                </flux:subheading>
            </div>

            <div class="flex items-center justify-between text-xs border-t border-zinc-100 dark:border-zinc-800 pt-3">
                <div>
                    <flux:text class="text-zinc-400 block uppercase tracking-wider text-[10px]">Termuda</flux:text>
                    <flux:text class="font-semibold text-sm">{{ $this->statsAge['min'] }} Thn</flux:text>
                </div>
                <div>
                    <flux:text class="text-zinc-400 block uppercase tracking-wider text-[10px] text-right">Tertua</flux:text>
                    <flux:text class="font-semibold text-sm block text-right">{{ $this->statsAge['max'] }} Thn</flux:text>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Main Card & Table -->
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg" class="font-bold">Data Master: Employees</flux:heading>
                <flux:subheading>Kelola daftar karyawan dalam organisasi.</flux:subheading>
            </div>
            <flux:modal.trigger name="employee-modal">
                <flux:button variant="primary" icon="plus" wire:click="resetForm">Karyawan</flux:button>
            </flux:modal.trigger>
        </div>

        <div class="flex gap-3">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari nama atau email (Klik baris tabel untuk melihat detail)..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>
            <div class="w-48">
                <flux:select wire:model.live="filterDept">
                    <flux:select.option value="">Semua Dept.</flux:select.option>
                    @foreach ($this->departmentOptions as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <flux:table :paginate="$this->employees" striped hoverable>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection"
                    wire:click="sort('name')">Nama</flux:table.column>
                <flux:table.column>Departemen</flux:table.column>
                <flux:table.column>Jabatan Struktural</flux:table.column>
                <flux:table.column>Jabatan Fungsional</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection"
                    wire:click="sort('email')">Email</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Gender</flux:table.column>
                <flux:table.column align="right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->employees as $emp)
                    <flux:table.row :key="$emp->id" class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:click="showDetail('{{ $emp->id }}')">
                        <flux:table.cell class="font-medium">{{ $emp->user->name }}</flux:table.cell>

                        <flux:table.cell>
                            {{ $emp->department?->name ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $emp->positionStruktural?->name ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $emp->positionFungsional?->name ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell>{{ $emp->user->email }}</flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @if ($emp->employment_status)
                                    <flux:badge variant="pill" color="{{ match($emp->employment_status) {
                                        'PKWTT'           => 'green',
                                        'PKWT'            => 'yellow',
                                        'Internship'      => 'blue',
                                        'Mitra Strategis' => 'purple',
                                        'Bond Agreement'  => 'orange',
                                        default           => 'zinc',
                                    } }}">
                                        {{ $emp->employment_status }}
                                    </flux:badge>
                                @endif

                                @if ($emp->is_cltp)
                                    <flux:badge variant="pill" color="red">CLTP</flux:badge>
                                @endif

                                @if (!$emp->employment_status && !$emp->is_cltp)
                                    <flux:text>-</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>{{ $emp->gender ?? '-' }}</flux:table.cell>

                        <flux:table.cell align="right" wire:click.stop>
                            <flux:button variant="ghost" size="sm" icon="eye" inset="top bottom"
                                wire:click="showDetail('{{ $emp->id }}')" />
                            <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom"
                                wire:click="edit('{{ $emp->id }}')" />
                            <flux:button variant="danger" size="sm" icon="trash" inset="top bottom"
                                wire:click="confirmDelete('{{ $emp->id }}')" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">
                            <flux:text class="text-center">Belum ada data yang ditampilkan.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <!-- Slide-over Drawer Detail Karyawan -->
    <div x-show="openDrawer" class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
        <!-- Backdrop -->
        <div x-show="openDrawer" 
             x-transition:enter="ease-in-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-zinc-900/50 backdrop-blur-sm transition-opacity" 
             @click="openDrawer = false"></div>

        <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
            <!-- Slide-over Panel -->
            <div x-show="openDrawer" 
                 x-transition:enter="transform transition ease-in-out duration-300"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 class="w-screen max-w-md bg-white dark:bg-zinc-900 shadow-2xl border-l border-zinc-200 dark:border-zinc-800 flex flex-col justify-between">
                
                @if ($selectedEmployee)
                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- Drawer Header -->
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-lg">
                                    {{ substr($selectedEmployee->user->name, 0, 2) }}
                                </div>
                                <div>
                                    <flux:heading size="lg" class="font-bold">{{ $selectedEmployee->user->name }}</flux:heading>
                                    <flux:text size="sm" class="text-zinc-500">{{ $selectedEmployee->user->email }}</flux:text>
                                </div>
                            </div>
                            <button @click="openDrawer = false" class="text-zinc-400 hover:text-zinc-500 dark:hover:text-zinc-300">
                                <flux:icon.x-mark class="w-6 h-6" />
                            </button>
                        </div>

                        <flux:separator variant="subtle" />

                        <!-- Personal Info Section -->
                        <div class="space-y-4">
                            <flux:heading size="md" class="font-semibold text-zinc-500 uppercase tracking-wider text-[11px]">Informasi Pribadi</flux:heading>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">NIK</flux:text>
                                    <flux:text class="font-medium">{{ $selectedEmployee->nik ?? '-' }}</flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">NPWP</flux:text>
                                    <flux:text class="font-medium">{{ $selectedEmployee->npwp ?? '-' }}</flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">Tanggal Lahir</flux:text>
                                    <flux:text class="font-medium">
                                        {{ $selectedEmployee->birth_date ? $selectedEmployee->birth_date->format('d M Y') : '-' }}
                                    </flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">Jenis Kelamin</flux:text>
                                    <flux:text class="font-medium">{{ $selectedEmployee->gender ?? '-' }}</flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">No. Telepon</flux:text>
                                    <flux:text class="font-medium">{{ $selectedEmployee->phone_number ?? '-' }}</flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">Status Marital</flux:text>
                                    <flux:text class="font-medium">{{ $selectedEmployee->marital_status ?? '-' }}</flux:text>
                                </div>
                                <div class="col-span-2">
                                    <flux:text class="text-zinc-400 block text-xs">Tingkat Pendidikan</flux:text>
                                    <flux:text class="font-medium">{{ $selectedEmployee->education_level ?? '-' }}</flux:text>
                                </div>
                            </div>
                        </div>

                        <flux:separator variant="subtle" />

                        <!-- Job Info Section -->
                        <div class="space-y-4">
                            <flux:heading size="md" class="font-semibold text-zinc-500 uppercase tracking-wider text-[11px]">Informasi Kepegawaian</flux:heading>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">NIS (ID Karyawan)</flux:text>
                                    <flux:text class="font-medium">{{ $selectedEmployee->nis ?? '-' }}</flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">Departemen</flux:text>
                                    <flux:text class="font-medium">{{ $selectedEmployee->department?->name ?? '-' }}</flux:text>
                                </div>
                                <div class="col-span-2">
                                    <flux:text class="text-zinc-400 block text-xs">Jabatan Struktural</flux:text>
                                    <flux:text class="font-medium">{{ $selectedEmployee->positionStruktural?->name ?? '-' }}</flux:text>
                                </div>
                                <div class="col-span-2">
                                    <flux:text class="text-zinc-400 block text-xs">Jabatan Fungsional</flux:text>
                                    <flux:text class="font-medium">{{ $selectedEmployee->positionFungsional?->name ?? '-' }}</flux:text>
                                </div>
                            </div>
                        </div>

                        <flux:separator variant="subtle" />

                        <!-- Contract Section -->
                        <div class="space-y-4">
                            <flux:heading size="md" class="font-semibold text-zinc-500 uppercase tracking-wider text-[11px]">Kontrak & Status Kerja</flux:heading>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">Mulai Kontrak</flux:text>
                                    <flux:text class="font-medium">
                                        {{ $selectedEmployee->contract_start_date ? $selectedEmployee->contract_start_date->format('d M Y') : '-' }}
                                    </flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">Selesai Kontrak</flux:text>
                                    <flux:text class="font-medium">
                                        {{ $selectedEmployee->contract_end_date ? $selectedEmployee->contract_end_date->format('d M Y') : '-' }}
                                    </flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">Status Kepegawaian</flux:text>
                                    <div class="mt-1">
                                        @if ($selectedEmployee->employment_status)
                                            <flux:badge variant="pill" color="{{ match($selectedEmployee->employment_status) {
                                                'PKWTT'           => 'green',
                                                'PKWT'            => 'yellow',
                                                'Internship'      => 'blue',
                                                'Mitra Strategis' => 'purple',
                                                'Bond Agreement'  => 'orange',
                                                default           => 'zinc',
                                            } }}">
                                                {{ $selectedEmployee->employment_status }}
                                            </flux:badge>
                                        @else
                                            <flux:text>-</flux:text>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <flux:text class="text-zinc-400 block text-xs">Karyawan CLTP</flux:text>
                                    <div class="mt-1">
                                        <flux:badge variant="pill" color="{{ $selectedEmployee->is_cltp ? 'red' : 'zinc' }}">
                                            {{ $selectedEmployee->is_cltp ? 'Ya (CLTP)' : 'Tidak' }}
                                        </flux:badge>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Drawer Footer -->
                    <div class="p-6 bg-zinc-50 dark:bg-zinc-800/30 border-t border-zinc-200 dark:border-zinc-800 flex justify-end gap-2">
                        <flux:button variant="ghost" @click="openDrawer = false">Tutup</flux:button>
                        <flux:button variant="primary" icon="pencil-square" wire:click="edit('{{ $selectedEmployee->id }}')">Edit Karyawan</flux:button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Modals -->
    <flux:modal name="employee-modal" class="md:w-[560px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEdit ? 'Edit Karyawan' : 'Tambah Karyawan Baru' }}</flux:heading>
                <flux:subheading>Isi data karyawan dengan lengkap dan benar.</flux:subheading>
            </div>

            <flux:separator variant="subtle" />
            <flux:heading size="sm" class="text-zinc-500">Informasi Dasar</flux:heading>

            <flux:field>
                <flux:label>Nama Lengkap</flux:label>
                <flux:input wire:model="name" placeholder="Cth: Budi Santoso, S.AP." required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input type="email" wire:model="email" placeholder="email@domain.com" required />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ $isEdit ? 'Password Baru (kosongkan jika tidak diubah)' : 'Password' }}</flux:label>
                <flux:input type="password" wire:model="password"
                    placeholder="{{ $isEdit ? 'Kosongkan jika tidak diubah' : 'Min. 8 karakter' }}" />
                <flux:error name="password" />
            </flux:field>

            <flux:separator variant="subtle" />
            <flux:heading size="sm" class="text-zinc-500">Struktur Organisasi</flux:heading>

            <flux:field>
                <flux:label>Departemen</flux:label>
                <flux:select wire:model="department_id">
                    <flux:select.option value="">-- Pilih Departemen --</flux:select.option>
                    @foreach ($this->departmentOptions as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="department_id" />
            </flux:field>

            <flux:field>
                <flux:label>Jabatan Struktural</flux:label>
                <flux:select wire:model="position_struktural_id">
                    <flux:select.option value="">-- Pilih Jabatan Struktural --</flux:select.option>
                    @foreach ($this->positionOptions->where('type', 'Struktural') as $pos)
                        <flux:select.option value="{{ $pos->id }}">{{ $pos->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="position_struktural_id" />
            </flux:field>

            <flux:field>
                <flux:label>Jabatan Fungsional</flux:label>
                <flux:select wire:model="position_fungsional_id">
                    <flux:select.option value="">-- Pilih Jabatan Fungsional --</flux:select.option>
                    @foreach ($this->positionOptions->where('type', 'Fungsional') as $pos)
                        <flux:select.option value="{{ $pos->id }}">{{ $pos->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="position_fungsional_id" />
            </flux:field>

            <flux:separator variant="subtle" />
            <flux:heading size="sm" class="text-zinc-500">Status Kepegawaian</flux:heading>

            <flux:field>
                <flux:label>Status Kepegawaian</flux:label>
                <flux:select wire:model="employment_status">
                    <flux:select.option value="">-- Pilih Status --</flux:select.option>
                    <flux:select.option value="PKWTT">PKWTT</flux:select.option>
                    <flux:select.option value="PKWT">PKWT</flux:select.option>
                    <flux:select.option value="Internship">Internship</flux:select.option>
                    <flux:select.option value="Mitra Strategis">Mitra Strategis</flux:select.option>
                    <flux:select.option value="Bond Agreement">Bond Agreement</flux:select.option>
                </flux:select>
                <flux:error name="employment_status" />
            </flux:field>

            <flux:field>
                <flux:checkbox wire:model="is_cltp" label="Karyawan CLTP" />
                <flux:error name="is_cltp" />
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
                <flux:heading size="lg">Hapus Karyawan?</flux:heading>
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
