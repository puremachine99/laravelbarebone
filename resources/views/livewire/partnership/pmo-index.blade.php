<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\Region;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

usesPagination();

state(['perPage' => 10]);
$updatedPerPage = fn () => $this->resetPage();
layout('layouts.app');

state([
'search' =>'',
'filterRegion' =>'',
]);

state([
'sortBy' =>'name',
'sortDirection' =>'asc',
]);

state([
'employee_id' => null,
'isEdit' => false,
'deleteTargetId' => null,

'name' =>'',
'email' =>'',
'password' =>'',
'region_id' =>'',
'position_struktural_id' =>'',
'position_fungsional_id' =>'',
'employment_status' =>'',
'is_cltp' => false,

'selectedEmployee' => null,
'showEmployeeModal' => false,
'showDeleteModal' => false,
]);

$regionOptions = computed(fn() => Region::orderBy('name')->get());
$positionOptions = computed(fn() => Position::orderBy('name')->get());
$isSuperAdmin = computed(fn() => auth()->user()?->can('modify-master-data'));

$employees = computed(function () {
 $search = $this->search;
 $filterRegion = $this->filterRegion;

 return Employee::query()
 ->with(['user','department','positionFungsional','positionStruktural','region'])
 ->join('users','users.id','=','employees.user_id')
 ->select('employees.*')
 ->whereHas('department', fn($q) => $q->where('name','Departemen Revenue and Growth'))
 ->when($search, fn($q) =>
 $q->where(fn($q) =>
 $q->whereRaw('LOWER(users.name) LIKE ?', ['%' . strtolower($search) .'%'])
 ->orWhereRaw('LOWER(users.email) LIKE ?', ['%' . strtolower($search) .'%'])
 )
 )
 ->when($filterRegion, fn($q) => $q->where('employees.region_id', $filterRegion))
 ->when($this->sortBy, function ($q) {
 $sortableOnUsers = ['name','email'];
 $column = in_array($this->sortBy, $sortableOnUsers)
 ?'users.' . $this->sortBy
 :'employees.' . $this->sortBy;
 $q->orderBy($column, $this->sortDirection);
 })
 ->paginate(\App\Support\PerPage::resolve($this->perPage));
});

$statsStatus = computed(function () {
 $total = Employee::whereHas('department', fn($q) => $q->where('name','Departemen Revenue and Growth'))->count() ?: 1;
 $statuses = [
'PKWTT' =>'green',
'PKWT' =>'yellow',
'Internship' =>'blue',
'Mitra Strategis' =>'purple',
'Bond Agreement' =>'orange',
 ];

 $result = [];
 foreach ($statuses as $status => $color) {
 $count = Employee::whereHas('department', fn($q) => $q->where('name','Departemen Revenue and Growth'))
 ->where('employment_status', $status)
 ->count();
 $result[$status] = [
'count' => $count,
'percentage' => round(($count / $total) * 100, 1),
'color' => $color,
 ];
 }

 $cltpCount = Employee::whereHas('department', fn($q) => $q->where('name','Departemen Revenue and Growth'))
 ->where('is_cltp', true)
 ->count();
 $result['CLTP'] = [
'count' => $cltpCount,
'percentage' => round(($cltpCount / $total) * 100, 1),
'color' =>'red',
 ];

 return $result;
});

$statsRegionDistribution = computed(function () {
 $total = Employee::whereHas('department', fn($q) => $q->where('name','Departemen Revenue and Growth'))->count() ?: 1;
 $regions = Employee::whereHas('department', fn($q) => $q->where('name','Departemen Revenue and Growth'))
 ->whereNotNull('region_id')
 ->selectRaw('region_id, count(*) as count')
 ->groupBy('region_id')
 ->with('region')
 ->orderByDesc('count')
 ->get();

 $top = $regions->first();

 return [
'top_name' => $top && $top->region ? $top->region->name :'-',
'top_percentage' => $top ? round(($top->count / $total) * 100, 1) : 0,
'regions' => $regions,
 ];
});

$statsTenure = computed(function () {
 $startDates = Employee::whereHas('department', fn($q) => $q->where('name','Departemen Revenue and Growth'))
 ->whereNotNull('contract_start_date')
 ->pluck('contract_start_date');

 if ($startDates->isEmpty()) {
 return ['avg' => 0,'min' => 0,'max' => 0];
 }

 $tenures = $startDates->map(fn($date) => round(Carbon::parse($date)->diffInDays(now()) / 365.25, 1));

 return [
'avg' => round($tenures->average(), 1),
'min' => $tenures->min(),
'max' => $tenures->max(),
 ];
});

$showDetail = function ($id) {
 $this->selectedEmployee = Employee::with(['user','department','positionFungsional','positionStruktural','region'])->find($id);
 $this->js('$dispatch("open-drawer")');
};

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

$updatedFilterRegion = function () {
 $this->resetPage();
};

$resetForm = function () {
 $this->reset(['employee_id','isEdit','name','email','password','region_id','position_struktural_id','position_fungsional_id','employment_status','is_cltp']);
 $this->resetValidation();
};

$save = function () {
 if (!$this->isSuperAdmin) {
 abort(403,'Unauthorized action.');
 }
 $this->validate([
'name' => ['required','string','max:255'],
'email' => ['required','email', Rule::unique('users','email')->ignore(
 $this->employee_id
 ? Employee::find($this->employee_id)?->user_id
 : null
 )],
'password' => [$this->isEdit ?'nullable' :'required','string','min:8'],
'region_id' => ['nullable','exists:regions,id'],
'position_struktural_id' => ['nullable','exists:positions,id'],
'position_fungsional_id' => ['nullable','exists:positions,id'],
'employment_status' => ['nullable','in:PKWT,PKWTT,Internship,Mitra Strategis,Bond Agreement'],
 ]);

 $isNew = is_null($this->employee_id);

 $userData = [
'name' => $this->name,
'email' => $this->email,
 ];
 if ($this->password) {
 $userData['password'] = Hash::make($this->password);
 }

 $dept = Department::where('name','Departemen Revenue and Growth')->first();

 $employeeData = [
'department_id' => $dept?->id ?: 6,
'region_id' => $this->region_id ?: null,
'position_struktural_id' => $this->position_struktural_id ?: null,
'position_fungsional_id' => $this->position_fungsional_id ?: null,
'employment_status' => $this->employment_status ?: null,
'is_cltp' => $this->is_cltp,
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
 $this->showEmployeeModal = false;
 session()->flash('success', $isNew ?'Karyawan berhasil ditambahkan!' :'Data karyawan berhasil diperbarui!');
};

$edit = function (Employee $employee) {
 if (!$this->isSuperAdmin) {
 abort(403,'Unauthorized action.');
 }
 $this->resetValidation();
 $this->employee_id = $employee->id;
 $this->name = $employee->user->name;
 $this->email = $employee->user->email;
 $this->password ='';
 $this->region_id = $employee->region_id ??'';
 $this->position_struktural_id = $employee->position_struktural_id ??'';
 $this->position_fungsional_id = $employee->position_fungsional_id ??'';
 $this->employment_status = $employee->employment_status ??'';
 $this->is_cltp = $employee->is_cltp;
 $this->isEdit = true;
 $this->showEmployeeModal = true;
};

$confirmDelete = function (string $id) {
 if (!$this->isSuperAdmin) {
 abort(403,'Unauthorized action.');
 }
 $this->deleteTargetId = $id;
 $this->showDeleteModal = true;
};

$delete = function () {
 if (!$this->isSuperAdmin) {
 abort(403,'Unauthorized action.');
 }
 Employee::findOrFail($this->deleteTargetId)->delete();
 $this->deleteTargetId = null;
 $this->showDeleteModal = false;
 session()->flash('error','Karyawan berhasil dihapus.');
};

?>
<div x-data="{ openDrawer: false }" @open-drawer.window="openDrawer = true" @close-drawer.window="openDrawer = false" class="space-y-6">
 <x-nakama.flash />

 <!-- Mini Dashboard -->
 <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
 <!-- Card 1: Total Karyawan & Status -->
 <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-sm rounded-2xl">
 <div>
 <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">TOTAL TIM PARTNERSHIP</h3>
 <h2 class="text-2xl mt-1 font-extrabold text-base-content">{{ Employee::whereHas('department', fn($q) => $q->where('name','Departemen Revenue and Growth'))->count() }} Karyawan</h2>
 </div>
 
 <!-- Stacked Horizontal Bar Chart -->
 <div class="h-4 w-full bg-base-200 rounded-full overflow-hidden flex">
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
 <span class="text-base-content/70 truncate">{{ $status }} ({{ $info['count'] }})</span>
 </div>
 @endif
 @endforeach
 </div>
 </div>

 <!-- Card 2: Wilayah Terbanyak -->
 <div class="card bg-base-100 border border-base-300 p-6 space-y-4 flex flex-col justify-between shadow-sm rounded-2xl">
 <div>
 <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">WILAYAH / REGION UTAMA</h3>
 <h2 class="text-xl mt-1 font-extrabold text-base-content">
 {{ $this->statsRegionDistribution['top_name'] }}
 </h2>
 <p class="text-xs text-base-content/40 mt-0.5">
 Menjadi focus kerja bagi {{ $this->statsRegionDistribution['top_percentage'] }}% total tim sales/partnership.
 </p>
 </div>

 <div class="space-y-1.5 pt-2">
 @foreach ($this->statsRegionDistribution['regions']->take(3) as $reg)
 <div class="flex items-center justify-between text-xs">
 <span class="font-medium text-base-content/70">{{ $reg->region?->name ??'Tanpa Wilayah' }}</span>
 <span class="text-base-content/50 font-semibold">{{ $reg->count }} Orang</span>
 </div>
 @endforeach
 </div>
 </div>

 <!-- Card 3: Profil Lama Kerja -->
 <div class="card bg-base-100 border border-base-300 p-6 space-y-4 flex flex-col justify-between shadow-sm rounded-2xl">
 <div>
 <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">RATA-RATA LAMA KERJA</h3>
 <h2 class="text-xl mt-1 font-extrabold text-base-content">{{ $this->statsTenure['avg'] }} Tahun</h2>
 <p class="text-xs text-base-content/40 mt-0.5">
 Rentang lama kerja berkisar dari {{ $this->statsTenure['min'] }} hingga {{ $this->statsTenure['max'] }} tahun.
 </p>
 </div>

 <div class="flex items-center justify-between text-xs border-t border-base-300 pt-3">
 <div>
 <span class="text-base-content/40 block uppercase tracking-wider text-[10px]">Paling Baru</span>
 <span class="font-semibold text-sm text-base-content">{{ $this->statsTenure['min'] }} Thn</span>
 </div>
 <div>
 <span class="text-base-content/40 block uppercase tracking-wider text-[10px] text-right">Paling Lama</span>
 <span class="font-semibold text-sm block text-right text-base-content">{{ $this->statsTenure['max'] }} Thn</span>
 </div>
 </div>
 </div>
 </div>

 <!-- Main Card & Table -->
 <div class="card bg-base-100 border border-base-300 p-6 space-y-6 shadow-sm rounded-2xl">
 <div class="flex items-center justify-between">
 <div>
 <h2 class="text-xl font-bold text-base-content">Partnership Employees (Revenue & Growth)</h2>
 <p class="text-sm text-base-content/50 mt-1">Kelola daftar PMO Pratama dan tim sales berdasarkan pembagian wilayah region.</p>
 </div>
 @if ($this->isSuperAdmin)
 <x-button variant="primary" icon="o-plus" size="btn-sm" label="Tambah PMO" wire:click="resetForm(); $set('showEmployeeModal', true);" />
 @endif
 </div>

 <div class="flex gap-3">
 <div class="flex-1">
 <x-input
 wire:model.live.debounce.300ms="search"
 placeholder="Cari nama atau email (Klik baris tabel untuk melihat detail)..."
 icon="o-magnifying-glass"
 clearable
 />
 </div>
 <div class="w-48">
 <x-select wire:model.live="filterRegion" :options="$this->regionOptions" placeholder="Semua Wilayah" placeholder-value="" />
 </div>
 </div>

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th class="cursor-pointer py-3" wire:click="sort('name')">
 <div class="flex items-center gap-1">
 <span>Nama</span>
 @if ($sortBy ==='name')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th>Wilayah / Region</th>
 <th>Jabatan Struktural</th>
 <th>Jabatan Fungsional</th>
 <th class="cursor-pointer" wire:click="sort('email')">
 <div class="flex items-center gap-1">
 <span>Email</span>
 @if ($sortBy ==='email')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th>Status</th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->employees as $emp)
 <tr class="border-b border-base-300 hover:bg-base-200 cursor-pointer" wire:click="showDetail('{{ $emp->id }}')">
 <td class="font-semibold text-base-content py-3">{{ $emp->user->name }}</td>
 <td>
 @if ($emp->region)
 <div class="badge badge-sm badge-info badge-outline">{{ $emp->region->name }}</div>
 @else
 <span class="text-base-content/40 italic text-xs">Belum disematkan</span>
 @endif
 </td>
 <td>{{ $emp->positionStruktural?->name ??'-' }}</td>
 <td>{{ $emp->positionFungsional?->name ??'-' }}</td>
 <td>{{ $emp->user->email }}</td>
 <td>
 <div class="flex items-center gap-1.5 flex-wrap">
 @if ($emp->employment_status)
 <div class="badge badge-sm badge-outline {{ match($emp->employment_status) {
'PKWTT' =>'badge-success',
'PKWT' =>'badge-warning',
'Internship' =>'badge-info',
'Mitra Strategis' =>'badge-primary',
'Bond Agreement' =>'badge-secondary',
 default =>'badge-ghost',
 } }}">
 {{ $emp->employment_status }}
 </div>
 @endif

 @if ($emp->is_cltp)
 <div class="badge badge-sm badge-error text-white">CLTP</div>
 @endif
 </div>
 </td>
 <td class="text-right" wire:click.stop>
 <div class="flex gap-2 justify-end">
 <x-button icon="o-eye" class="btn-ghost btn-xs text-base-content/50" wire:click="showDetail('{{ $emp->id }}')" />
 @if ($this->isSuperAdmin)
 <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="edit('{{ $emp->id }}')" />
 <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete('{{ $emp->id }}')" />
 @endif
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="7" class="text-center text-base-content/50 py-8">
 Belum ada data yang ditampilkan.
 </td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>

 <div class="mt-4">
 <x-pagination-footer :paginator="$this->employees" />
 </div>
 </div>

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
 class="w-screen max-w-md bg-base-100 shadow-2xl border-l border-base-300 flex flex-col justify-between">
 
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
 <h3 class="text-lg font-bold text-base-content">{{ $selectedEmployee->user->name }}</h3>
 <p class="text-sm text-base-content/50">{{ $selectedEmployee->user->email }}</p>
 </div>
 </div>
 <button @click="openDrawer = false" class="text-base-content/40 hover:text-base-content/50 dark:hover:text-base-content/30">
 <x-icon name="o-x-mark" class="w-6 h-6" />
 </button>
 </div>

 <hr class="border-base-300" />

 <!-- Personal Info Section -->
 <div class="space-y-4">
 <h4 class="font-bold text-base-content/50 uppercase tracking-wider text-[11px]">Informasi Pribadi</h4>
 
 <div class="grid grid-cols-2 gap-4 text-sm">
 <div>
 <span class="text-base-content/40 block text-xs">NIK</span>
 <span class="font-medium text-base-content/90">{{ $selectedEmployee->nik ??'-' }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">NPWP</span>
 <span class="font-medium text-base-content/90">{{ $selectedEmployee->npwp ??'-' }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Tanggal Lahir</span>
 <span class="font-medium text-base-content/90">
 {{ $selectedEmployee->birth_date ? $selectedEmployee->birth_date->format('d M Y') :'-' }}
 </span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Jenis Kelamin</span>
 <span class="font-medium text-base-content/90">{{ $selectedEmployee->gender ??'-' }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">No. Telepon</span>
 <span class="font-medium text-base-content/90">{{ $selectedEmployee->phone_number ??'-' }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Status Marital</span>
 <span class="font-medium text-base-content/90">{{ $selectedEmployee->marital_status ??'-' }}</span>
 </div>
 <div class="col-span-2">
 <span class="text-base-content/40 block text-xs">Tingkat Pendidikan</span>
 <span class="font-medium text-base-content/90">{{ $selectedEmployee->education_level ??'-' }}</span>
 </div>
 </div>
 </div>

 <hr class="border-base-300" />

 <!-- Job Info Section -->
 <div class="space-y-4">
 <h4 class="font-bold text-base-content/50 uppercase tracking-wider text-[11px]">Informasi Kepegawaian & Wilayah</h4>
 
 <div class="grid grid-cols-2 gap-4 text-sm">
 <div>
 <span class="text-base-content/40 block text-xs">NIS (ID Karyawan)</span>
 <span class="font-medium text-base-content/90">{{ $selectedEmployee->nis ??'-' }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Wilayah / Region Kerja</span>
 <div class="mt-1">
 @if ($selectedEmployee->region)
 <div class="badge badge-info text-white">{{ $selectedEmployee->region->name }}</div>
 @else
 <span class="text-base-content/40 italic text-xs">Belum disematkan</span>
 @endif
 </div>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Departemen</span>
 <span class="font-medium text-base-content/90">{{ $selectedEmployee->department?->name ??'-' }}</span>
 </div>
 <div class="col-span-2">
 <span class="text-base-content/40 block text-xs">Jabatan Struktural</span>
 <span class="font-medium text-base-content/90">{{ $selectedEmployee->positionStruktural?->name ??'-' }}</span>
 </div>
 <div class="col-span-2">
 <span class="text-base-content/40 block text-xs">Jabatan Fungsional</span>
 <span class="font-medium text-base-content/90">{{ $selectedEmployee->positionFungsional?->name ??'-' }}</span>
 </div>
 </div>
 </div>

 <hr class="border-base-300" />

 <!-- Contract Section -->
 <div class="space-y-4">
 <h4 class="font-bold text-base-content/50 uppercase tracking-wider text-[11px]">Kontrak & Status Kerja</h4>
 
 <div class="grid grid-cols-2 gap-4 text-sm">
 <div>
 <span class="text-base-content/40 block text-xs">Mulai Kontrak</span>
 <span class="font-medium text-base-content/90">
 {{ $selectedEmployee->contract_start_date ? $selectedEmployee->contract_start_date->format('d M Y') :'-' }}
 </span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Selesai Kontrak</span>
 <span class="font-medium text-base-content/90">
 {{ $selectedEmployee->contract_end_date ? $selectedEmployee->contract_end_date->format('d M Y') :'-' }}
 </span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Status Kepegawaian</span>
 <div class="mt-1">
 @if ($selectedEmployee->employment_status)
 <div class="badge badge-outline {{ match($selectedEmployee->employment_status) {
'PKWTT' =>'badge-success',
'PKWT' =>'badge-warning',
'Internship' =>'badge-info',
'Mitra Strategis' =>'badge-primary',
'Bond Agreement' =>'badge-secondary',
 default =>'badge-ghost',
 } }}">
 {{ $selectedEmployee->employment_status }}
 </div>
 @else
 <span>-</span>
 @endif
 </div>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Karyawan CLTP</span>
 <div class="mt-1">
 <div class="badge {{ $selectedEmployee->is_cltp ?'badge-error text-white' :'badge-neutral' }}">
 {{ $selectedEmployee->is_cltp ?'Ya (CLTP)' :'Tidak' }}
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Drawer Footer -->
 <div class="p-6 bg-base-200/30 border-t border-base-300 flex justify-end gap-2">
 <x-button label="Tutup" @click="openDrawer = false" class="btn-ghost" />
 @if ($this->isSuperAdmin)
 <x-button variant="primary" icon="o-pencil-square" label="Edit Karyawan" wire:click="edit('{{ $selectedEmployee->id }}')" />
 @endif
 </div>
 @endif
 </div>
 </div>
 </div>

 <!-- Modals -->
 <x-modal wire:model="showEmployeeModal" title="{{ $isEdit ?'Edit Karyawan Partnership' :'Tambah Karyawan Partnership Baru' }}" subtitle="Tugaskan PMO dan sales di departemen Revenue & Growth dengan penempatan wilayah region.">
 <form wire:submit.prevent="save" class="space-y-4">
 <h4 class="font-bold text-base-content/50 text-xs uppercase">Informasi Dasar</h4>

 <x-input label="Nama Lengkap" wire:model="name" placeholder="Cth: Budi Santoso, S.AP." required />
 <x-input label="Email" type="email" wire:model="email" placeholder="email@domain.com" required />
 <x-input label="{{ $isEdit ?'Password Baru (kosongkan jika tidak diubah)' :'Password' }}" type="password" wire:model="password" placeholder="{{ $isEdit ?'Kosongkan jika tidak diubah' :'Min. 8 karakter' }}" />

 <hr class="border-base-300 my-2" />
 <h4 class="font-bold text-base-content/50 text-xs uppercase">Wilayah Kerja & Jabatan</h4>

 <x-select label="Wilayah / Region Penempatan" wire:model="region_id" :options="$this->regionOptions" placeholder="-- Pilih Wilayah / Region --" placeholder-value="" />
 
 @php
 $structOpts = $this->positionOptions->where('type','Struktural')->map(fn($p) => ['id' => $p->id,'name' => $p->name]);
 $fungOpts = $this->positionOptions->where('type','Fungsional')->map(fn($p) => ['id' => $p->id,'name' => $p->name]);
 @endphp
 <x-select label="Jabatan Struktural" wire:model="position_struktural_id" :options="$structOpts" placeholder="-- Pilih Jabatan Struktural --" placeholder-value="" />
 <x-select label="Jabatan Fungsional" wire:model="position_fungsional_id" :options="$fungOpts" placeholder="-- Pilih Jabatan Fungsional --" placeholder-value="" />

 <hr class="border-base-300 my-2" />
 <h4 class="font-bold text-base-content/50 text-xs uppercase">Status Kepegawaian</h4>

 @php
 $statusOpts = [
 ['id' =>'PKWTT','name' =>'PKWTT'],
 ['id' =>'PKWT','name' =>'PKWT'],
 ['id' =>'Internship','name' =>'Internship'],
 ['id' =>'Mitra Strategis','name' =>'Mitra Strategis'],
 ['id' =>'Bond Agreement','name' =>'Bond Agreement'],
 ];
 @endphp
 <x-select label="Status Kepegawaian" wire:model="employment_status" :options="$statusOpts" placeholder="-- Pilih Status --" placeholder-value="" />
 <x-checkbox label="Karyawan CLTP" wire:model="is_cltp" />

 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showEmployeeModal', false)" class="btn-ghost" />
 <x-button label="Simpan Data" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>

 <!-- Confirmation Modal -->
 <x-nakama.confirm-modal wire:model="showDeleteModal" title="Hapus Karyawan?"
     subtitle="Tindakan ini tidak bisa dibatalkan."
     confirm="delete" confirm-label="Ya, Hapus" cancel-label="Batal" />
</div>
