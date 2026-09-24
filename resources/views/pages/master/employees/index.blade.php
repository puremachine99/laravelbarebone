<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Actions\Users\InviteUser;
use App\Enums\EmployeeStatus;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

usesPagination();
layout('layouts.app');

state([
'search' =>'',
'filterDept' =>'',
]);

state([
'sortBy' =>'name',
'sortDirection' =>'asc',
]);

state([
'employee_id' => null,
'existingUserId' => null,
'isEdit' => false,
'deleteTargetId' => null,

'name' =>'',
'email' =>'',
'password' =>'',
'department_id' =>'',
'position_struktural_id' =>'',
'position_fungsional_id' =>'',
'employment_status' =>'',
'status' =>'active',
'status_date' =>'',
'status_reason' =>'',
'is_cltp' => false,
'userRoles' => [],

'selectedEmployee' => null,
'showEmployeeModal' => false,
'showDeleteModal' => false,
]);

$departmentOptions = computed(fn() => Department::orderBy('name')->get());
$positionOptions = computed(fn() => Position::orderBy('name')->get());
$allRoles = computed(fn() => Role::all());

$employees = computed(function () {
 $search = $this->search;
 $filterDept = $this->filterDept;

 return User::query()
 ->select('users.*')
 ->leftJoin('employees','employees.user_id','=','users.id')
 ->with(['employee.department','employee.positionFungsional','employee.positionStruktural','roles'])
 ->when($search, fn($q) =>
 $q->where(fn($q) =>
 $q->whereRaw('LOWER(users.name) LIKE ?', ['%' . strtolower($search) .'%'])
 ->orWhereRaw('LOWER(users.email) LIKE ?', ['%' . strtolower($search) .'%'])
 ->orWhereRaw('LOWER(employees.nis) LIKE ?', ['%' . strtolower($search) .'%'])
 )
 )
 ->when($filterDept, fn($q) => $q->where('employees.department_id', $filterDept))
 ->when($this->sortBy, function ($q) {
 $sortableOnUsers = ['name','email'];
 $column = in_array($this->sortBy, $sortableOnUsers)
 ?'users.' . $this->sortBy
 :'employees.' . $this->sortBy;
 $q->orderBy($column, $this->sortDirection);
 })
 ->paginate(10);
});

$statsStatus = computed(function () {
 $total = Employee::count() ?: 1;
 $statuses = [
'PKWTT' =>'green',
'PKWT' =>'yellow',
'Internship' =>'blue',
'Mitra Strategis' =>'purple',
'Bond Agreement' =>'orange',
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
'color' =>'red',
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
'top_level' => $top ? $top->education_level :'-',
'top_percentage' => $top ? round(($top->count / $total) * 100, 1) : 0,
'levels' => $levels,
 ];
});

$statsTenure = computed(function () {
 $startDates = Employee::whereNotNull('contract_start_date')->pluck('contract_start_date');
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
 $this->selectedEmployee = Employee::with(['user.invitedBy','department','positionFungsional','positionStruktural'])->find($id);
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

$updatedFilterDept = function () {
 $this->resetPage();
};

$resetForm = function () {
 $this->reset(['employee_id','existingUserId','isEdit','name','email','password','department_id','position_struktural_id','position_fungsional_id','employment_status','status','status_date','status_reason','is_cltp']);
 $this->status ='active';
 $this->userRoles = [];
 $this->resetValidation();
};

$save = function (InviteUser $inviteUser) {
 $isNew = is_null($this->employee_id) && is_null($this->existingUserId);

 $ignoreUserId = match (true) {
 $isNew => null,
 (bool) $this->existingUserId => $this->existingUserId,
 default => Employee::find($this->employee_id)?->user_id,
 };

 $this->validate([
'name' => ['required','string','max:255'],
'email' => ['required','email', Rule::unique('users','email')->ignore($ignoreUserId)],
'password' => ['nullable','string','min:8'],
'department_id' => ['nullable','exists:departments,id'],
'position_struktural_id' => ['nullable','exists:positions,id'],
'position_fungsional_id' => ['nullable','exists:positions,id'],
'employment_status' => ['nullable','in:PKWT,PKWTT,Internship,Mitra Strategis,Bond Agreement'],
'status' => ['required', Rule::enum(EmployeeStatus::class)],
'status_date' => ['nullable','date'],
'status_reason' => ['nullable','string','max:2000'],
 ]);

 $employeeData = [
'department_id' => $this->department_id ?: null,
'position_struktural_id' => $this->position_struktural_id ?: null,
'position_fungsional_id' => $this->position_fungsional_id ?: null,
'employment_status' => $this->employment_status ?: null,
'status' => $this->status,
'status_date' => $this->status !=='active' ? ($this->status_date ?: null) : null,
'status_reason' => $this->status !=='active' ? ($this->status_reason ?: null) : null,
'is_cltp' => (bool) $this->is_cltp,
 ];

 if ($isNew) {
 $user = $inviteUser->handle($this->name, $this->email, null, $this->userRoles, auth()->user());
 Employee::create(array_merge($employeeData, ['user_id' => $user->id]));
 $message = "Undangan berhasil dikirim ke {$this->email}.";
 } elseif ($this->existingUserId) {
 $user = User::findOrFail($this->existingUserId);
 $userData = [
'name' => $this->name,
'email' => $this->email,
 ];
 if ($this->password) {
 $userData['password'] = Hash::make($this->password);
 }
 $user->update($userData);
 $user->syncRoles($this->userRoles);
 Employee::create(array_merge($employeeData, ['user_id' => $user->id]));
 $message ='Profil karyawan berhasil dilengkapi.';
 } else {
 $employee = Employee::findOrFail($this->employee_id);
 $userData = [
'name' => $this->name,
'email' => $this->email,
 ];
 if ($this->password) {
 $userData['password'] = Hash::make($this->password);
 }
 $employee->user->update($userData);
 $employee->user->syncRoles($this->userRoles);
 $employee->update($employeeData);
 $message ='Data karyawan berhasil diperbarui!';
 }

 $this->resetForm();
 $this->showEmployeeModal = false;
 session()->flash('success', $message);
};

$edit = function (Employee $employee) {
 $this->resetValidation();
 $this->employee_id = $employee->id;
 $this->existingUserId = null;
 $this->name = $employee->user->name;
 $this->email = $employee->user->email;
 $this->password ='';
 $this->department_id = $employee->department_id ??'';
 $this->position_struktural_id = $employee->position_struktural_id ??'';
 $this->position_fungsional_id = $employee->position_fungsional_id ??'';
 $this->employment_status = $employee->employment_status ??'';
 $this->status = $employee->status?->value ??'active';
 $this->status_date = $employee->status_date?->format('Y-m-d') ??'';
 $this->status_reason = $employee->status_reason ??'';
 $this->is_cltp = $employee->is_cltp;
 $this->userRoles = $employee->user->roles->pluck('name')->toArray();
 $this->isEdit = true;
 $this->showEmployeeModal = true;
};

$completeProfile = function (User $user) {
 $this->resetValidation();
 $this->employee_id = null;
 $this->existingUserId = $user->id;
 $this->name = $user->name;
 $this->email = $user->email;
 $this->password ='';
 $this->department_id ='';
 $this->position_struktural_id ='';
 $this->position_fungsional_id ='';
 $this->employment_status ='';
 $this->status ='active';
 $this->status_date ='';
 $this->status_reason ='';
 $this->is_cltp = false;
 $this->userRoles = $user->roles->pluck('name')->toArray();
 $this->isEdit = true;
 $this->showEmployeeModal = true;
};

$resendInvite = function (InviteUser $inviteUser, int $userId) {
 $user = User::findOrFail($userId);

 if (! $user->isInvited()) {
 return;
 }

 $inviteUser->resend($user);

 session()->flash('success','Undangan berhasil dikirim ulang.');
};

$suspend = function (int $userId) {
 if ($userId === auth()->id()) {
 return;
 }

 User::whereKey($userId)->update(['status' => UserStatus::Suspended]);

 DB::table('sessions')->where('user_id', $userId)->delete();

 session()->flash('error','Pengguna berhasil dinonaktifkan dan sesi aktifnya diputus.');
};

$activate = function (int $userId) {
 User::whereKey($userId)->update(['status' => UserStatus::Active]);

 session()->flash('success','Pengguna berhasil diaktifkan kembali.');
};

$confirmDelete = function (int $userId) {
 $this->deleteTargetId = $userId;
 $this->showDeleteModal = true;
};

$delete = function () {
 if ($this->deleteTargetId && $this->deleteTargetId !== auth()->id()) {
 DB::table('sessions')->where('user_id', $this->deleteTargetId)->delete();
 User::findOrFail($this->deleteTargetId)->delete();
 }

 $this->deleteTargetId = null;
 $this->showDeleteModal = false;
 session()->flash('error','Karyawan berhasil dihapus.');
};

?>
<div x-data="{ openDrawer: false }" @open-drawer.window="openDrawer = true" @close-drawer.window="openDrawer = false" class="space-y-6">
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
 <!-- Card 1: Total Karyawan & Status -->
 <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-sm rounded-2xl">
 <div>
 <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">TOTAL KARYAWAN & STATUS</h3>
 <h2 class="text-2xl mt-1 font-extrabold text-base-content">{{ Employee::count() }} Karyawan</h2>
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

 <!-- Card 2: Pendidikan Terbanyak -->
 <div class="card bg-base-100 border border-base-300 p-6 space-y-4 flex flex-col justify-between shadow-sm rounded-2xl">
 <div>
 <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">PENDIDIKAN TERBANYAK</h3>
 <h2 class="text-xl mt-1 font-extrabold text-base-content">
 {{ $this->statsEducation['top_level'] }}
 </h2>
 <p class="text-xs text-base-content/40 mt-0.5">
 Merupakan tingkat pendidikan dari {{ $this->statsEducation['top_percentage'] }}% total karyawan.
 </p>
 </div>

 <div class="space-y-1.5 pt-2">
 @foreach ($this->statsEducation['levels']->take(3) as $lvl)
 <div class="flex items-center justify-between text-xs">
 <span class="font-medium text-base-content/70">{{ $lvl->education_level }}</span>
 <span class="text-base-content/50 font-semibold">{{ $lvl->count }} Karyawan</span>
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
 <h2 class="text-xl font-bold text-base-content">Data Master: Employees</h2>
 <p class="text-sm text-base-content/50 mt-1">Kelola daftar karyawan dalam organisasi.</p>
 </div>
 <x-button variant="primary" icon="o-plus" size="btn-sm" label="Karyawan" wire:click="resetForm(); $set('showEmployeeModal', true);" />
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
 <x-select wire:model.live="filterDept" :options="$this->departmentOptions" placeholder="Semua Dept." placeholder-value="" />
 </div>
 </div>

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th>ID</th>
 <th class="cursor-pointer py-3" wire:click="sort('name')">
 <div class="flex items-center gap-1">
 <span>Nama</span>
 @if ($sortBy ==='name')
 <x-icon name="{{ $sortDirection ==='asc' ?'o-chevron-up' :'o-chevron-down' }}" class="w-3 h-3" />
 @endif
 </div>
 </th>
 <th>Departemen</th>
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
 <th>Status Akun</th>
 <th>Status Kepegawaian</th>
 <th>Roles</th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->employees as $emp)
 <tr class="border-b border-base-300 hover:bg-base-200 {{ $emp->employee ?'cursor-pointer' :'' }}" @if ($emp->employee) wire:click="showDetail({{ $emp->employee->id }})" @endif>
 <td class="text-base-content/50">{{ $emp->id }}</td>
 <td class="font-semibold text-base-content py-3">{{ $emp->name }}</td>
 <td>{{ $emp->employee?->department?->name ??'-' }}</td>
 <td>{{ $emp->employee?->positionStruktural?->name ??'-' }}</td>
 <td>{{ $emp->employee?->positionFungsional?->name ??'-' }}</td>
 <td>{{ $emp->email }}</td>
 <td>
 <span class="badge badge-sm {{ match ($emp->status) {
 \App\Enums\UserStatus::Active =>'badge-success',
 \App\Enums\UserStatus::Invited =>'badge-warning',
 \App\Enums\UserStatus::Suspended =>'badge-error',
 } }}">
 {{ match ($emp->status) {
 \App\Enums\UserStatus::Active =>'Aktif',
 \App\Enums\UserStatus::Invited =>'Menunggu Aktivasi',
 \App\Enums\UserStatus::Suspended =>'Dinonaktifkan',
 } }}
 </span>
 </td>
 <td>
 <div class="flex items-center gap-1.5 flex-wrap">
 @if (! $emp->employee)
 <span class="badge badge-sm badge-ghost text-base-content/40 whitespace-nowrap">Belum ada profil</span>
 @else
 @if ($emp->employee->employment_status)
 <div class="badge badge-sm badge-outline {{ match($emp->employee->employment_status) {
'PKWTT' =>'badge-success',
'PKWT' =>'badge-warning',
'Internship' =>'badge-info',
'Mitra Strategis' =>'badge-primary',
'Bond Agreement' =>'badge-secondary',
 default =>'badge-ghost',
 } }}">
 {{ $emp->employee->employment_status }}
 </div>
 @endif

 @if ($emp->employee->is_cltp)
 <div class="badge badge-sm badge-error text-white">CLTP</div>
 @endif

 @if ($emp->employee->status && $emp->employee->status !== \App\Enums\EmployeeStatus::Active)
 <div class="badge badge-sm {{ $emp->employee->status->badgeClass() }}">
 {{ $emp->employee->status->label() }}
 </div>
 @endif

 @if (!$emp->employee->employment_status && !$emp->employee->is_cltp && $emp->employee->status === \App\Enums\EmployeeStatus::Active)
 <span>-</span>
 @endif
 @endif
 </div>
 </td>
 <td class="max-w-40">
 <div class="flex flex-wrap gap-1">
 @forelse ($emp->roles as $role)
 <span class="badge badge-sm badge-outline badge-primary whitespace-nowrap">{{ $role->name }}</span>
 @empty
 <span class="text-xs text-base-content/40">-</span>
 @endforelse
 </div>
 </td>
 <td class="text-right" wire:click.stop>
 <div class="flex gap-2 justify-end">
 @if ($emp->isInvited())
 <x-button label="Kirim Ulang" class="btn-ghost btn-xs text-warning" wire:click="resendInvite({{ $emp->id }})" />
 @elseif ($emp->isSuspended())
 <x-button label="Aktifkan" class="btn-ghost btn-xs text-success" wire:click="activate({{ $emp->id }})" />
 @elseif ($emp->id !== auth()->id())
 <x-button label="Nonaktifkan" class="btn-ghost btn-xs text-warning" wire:click="suspend({{ $emp->id }})" />
 @endif
 @if ($emp->employee)
 <x-button icon="o-eye" class="btn-ghost btn-xs text-base-content/50" wire:click="showDetail({{ $emp->employee->id }})" />
 <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="edit({{ $emp->employee->id }})" />
 @else
 <x-button icon="o-user-plus" label="Lengkapi Profil" class="btn-ghost btn-xs text-info" wire:click="completeProfile({{ $emp->id }})" />
 @endif
 @if ($emp->id !== auth()->id())
 <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete({{ $emp->id }})" />
 @endif
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="10" class="text-center text-base-content/50 py-8">
 Belum ada data yang ditampilkan.
 </td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>

 <div class="mt-4">
 {{ $this->employees->links() }}
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
 <h3 class="font-bold text-base-content text-lg">{{ $selectedEmployee->user->name }}</h3>
 <p class="text-sm text-base-content/50">{{ $selectedEmployee->user->email }}</p>
 </div>
 </div>
 <button @click="openDrawer = false" class="text-base-content/40 hover:text-base-content/50 dark:hover:text-base-content/30">
 <x-icon name="o-x-mark" class="w-6 h-6" />
 </button>
 </div>

 @if ($selectedEmployee->user->invited_at)
 <div class="text-xs text-base-content/50 bg-base-200/50 rounded-lg p-3">
 Diundang oleh <span class="font-medium text-base-content">{{ $selectedEmployee->user->invitedBy?->name ??'-' }}</span>
 pada {{ $selectedEmployee->user->invited_at->format('d M Y, H:i') }}
 </div>
 @endif

 <hr class="border-base-300 my-4" />

 <!-- Personal Info Section -->
 <div class="space-y-4">
 <h4 class="font-semibold text-base-content/50 uppercase tracking-wider text-[11px]">Informasi Pribadi</h4>
 
 <div class="grid grid-cols-2 gap-4 text-sm">
 <div>
 <span class="text-base-content/40 block text-xs">NIK</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->nik ??'-' }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">NPWP</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->npwp ??'-' }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Tanggal Lahir</span>
 <span class="font-medium text-base-content">
 {{ $selectedEmployee->birth_date ? $selectedEmployee->birth_date->format('d M Y') :'-' }}
 </span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Jenis Kelamin</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->gender ??'-' }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">No. Telepon</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->phone_number ??'-' }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Status Marital</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->marital_status ??'-' }}</span>
 </div>
 <div class="col-span-2">
 <span class="text-base-content/40 block text-xs">Tingkat Pendidikan</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->education_level ??'-' }}</span>
 </div>
 </div>
 </div>

 <hr class="border-base-300 my-4" />

 <!-- Job Info Section -->
 <div class="space-y-4">
 <h4 class="font-semibold text-base-content/50 uppercase tracking-wider text-[11px]">Informasi Kepegawaian</h4>
 
 <div class="grid grid-cols-2 gap-4 text-sm">
 <div>
 <span class="text-base-content/40 block text-xs">NIS (ID Karyawan)</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->nis ??'-' }}</span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Departemen</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->department?->name ??'-' }}</span>
 </div>
 <div class="col-span-2">
 <span class="text-base-content/40 block text-xs">Jabatan Struktural</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->positionStruktural?->name ??'-' }}</span>
 </div>
 <div class="col-span-2">
 <span class="text-base-content/40 block text-xs">Jabatan Fungsional</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->positionFungsional?->name ??'-' }}</span>
 </div>
 </div>
 </div>

 <hr class="border-base-300 my-4" />

 <!-- Contract Section -->
 <div class="space-y-4">
 <h4 class="font-semibold text-base-content/50 uppercase tracking-wider text-[11px]">Kontrak & Status Kerja</h4>
 
 <div class="grid grid-cols-2 gap-4 text-sm">
 <div>
 <span class="text-base-content/40 block text-xs">Mulai Kontrak</span>
 <span class="font-medium text-base-content">
 {{ $selectedEmployee->contract_start_date ? $selectedEmployee->contract_start_date->format('d M Y') :'-' }}
 </span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Selesai Kontrak</span>
 <span class="font-medium text-base-content">
 {{ $selectedEmployee->contract_end_date ? $selectedEmployee->contract_end_date->format('d M Y') :'-' }}
 </span>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Status Kepegawaian</span>
 <div class="mt-1">
 @if ($selectedEmployee->employment_status)
 <div class="badge badge-sm badge-outline {{ match($selectedEmployee->employment_status) {
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
 <span class="text-base-content">-</span>
 @endif
 </div>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Karyawan CLTP</span>
 <div class="mt-1">
 <div class="badge badge-sm {{ $selectedEmployee->is_cltp ?'badge-error text-white' :'badge-ghost' }}">
 {{ $selectedEmployee->is_cltp ?'Ya (CLTP)' :'Tidak' }}
 </div>
 </div>
 </div>
 <div>
 <span class="text-base-content/40 block text-xs">Status Aktif</span>
 <div class="mt-1">
 <div class="badge badge-sm {{ $selectedEmployee->status->badgeClass() }}">
 {{ $selectedEmployee->status->label() }}
 </div>
 </div>
 </div>
 @if ($selectedEmployee->status !== \App\Enums\EmployeeStatus::Active)
 <div>
 <span class="text-base-content/40 block text-xs">Tanggal Nonaktif</span>
 <span class="font-medium text-base-content">
 {{ $selectedEmployee->status_date ? $selectedEmployee->status_date->format('d M Y') :'-' }}
 </span>
 </div>
 <div class="col-span-2">
 <span class="text-base-content/40 block text-xs">Keterangan</span>
 <span class="font-medium text-base-content">{{ $selectedEmployee->status_reason ??'-' }}</span>
 </div>
 @endif
 </div>
 </div>
 </div>

 <!-- Drawer Footer -->
 <div class="p-6 bg-base-200/30 border-t border-base-300 flex justify-end gap-2">
 <x-button label="Tutup" @click="openDrawer = false" class="btn-ghost" />
 <x-button variant="primary" icon="o-pencil-square" label="Edit Karyawan" wire:click="edit('{{ $selectedEmployee->id }}')" />
 </div>
 @endif
 </div>
 </div>
 </div>

 <!-- Modals -->
 <x-modal wire:model="showEmployeeModal" title="{{ $existingUserId ?'Lengkapi Profil Karyawan' : ($isEdit ?'Edit Karyawan' :'Tambah Karyawan Baru') }}" subtitle="Isi data karyawan dengan lengkap dan benar." box-class="md:w-[560px]">
 <form wire:submit.prevent="save" class="space-y-4">
 <h4 class="font-bold text-base-content/50 text-xs uppercase">Informasi Dasar</h4>

 <x-input label="Nama Lengkap" wire:model="name" placeholder="Cth: Budi Santoso, S.AP." required />
 <x-input label="Email" type="email" wire:model="email" placeholder="email@domain.com" required />
 @if ($isEdit)
 <x-input label="Password Baru (kosongkan jika tidak diubah)" type="password" wire:model="password" placeholder="Kosongkan jika tidak diubah" />
 @else
 <p class="text-xs text-base-content/50 -mt-2">Karyawan akan menerima email undangan berisi link untuk membuat password sendiri dan mengaktifkan akunnya.</p>
 @endif

 <hr class="border-base-300 my-2" />
 <h4 class="font-bold text-base-content/50 text-xs uppercase">Struktur Organisasi</h4>

 <x-select label="Departemen" wire:model="department_id" :options="$this->departmentOptions" placeholder="-- Pilih Departemen --" placeholder-value="" />
 
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

 <x-select label="Status Aktif" wire:model.live="status" :options="\App\Enums\EmployeeStatus::options()" />

 @if ($status !=='active')
 <x-input label="Tanggal Nonaktif" type="date" wire:model="status_date" />
 <x-textarea label="Keterangan (opsional)" wire:model="status_reason" placeholder="Cth: Resign per akhir kontrak, mutasi ke perusahaan lain, dsb." rows="3" />
 @endif

 <hr class="border-base-300 my-2" />
 <h4 class="font-bold text-base-content/50 text-xs uppercase">Hak Akses (Roles)</h4>

 <div class="grid grid-cols-1 gap-2">
 @foreach ($this->allRoles as $role)
 <label class="flex items-center gap-3 p-3 rounded-lg border border-base-300 bg-base-100 hover:bg-base-200 cursor-pointer">
 <input type="checkbox" wire:model="userRoles" value="{{ $role->name }}" class="checkbox checkbox-sm checkbox-primary" />
 <span class="text-sm font-medium text-base-content/90">{{ $role->name }}</span>
 </label>
 @endforeach
 </div>

 <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
 <x-button label="Batal" wire:click="$set('showEmployeeModal', false)" class="btn-ghost" />
 <x-button label="Simpan Data" type="submit" class="btn-primary" />
 </div>
 </form>
 </x-modal>

 <!-- Confirmation Modal -->
 <x-modal wire:model="showDeleteModal" title="Hapus Pengguna?" subtitle="Menghapus pengguna ini akan menghilangkan akses login, profil karyawan, serta semua peran yang dimilikinya. Tindakan ini tidak bisa dibatalkan.">
 <div class="flex justify-end gap-2 pt-4">
 <x-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
 <x-button label="Ya, Hapus" wire:click="delete" class="btn-error text-white" />
 </div>
 </x-modal>
</div>
