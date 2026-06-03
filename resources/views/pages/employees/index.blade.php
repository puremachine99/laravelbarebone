<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use Flux\Flux;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

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
<div>
    <flux:card class="space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Data Master: Employees</flux:heading>
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
                    placeholder="Cari nama atau email..."
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
                    <flux:table.row :key="$emp->id">
                        <flux:table.cell>{{ $emp->user->name }}</flux:table.cell>

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

                        <flux:table.cell align="right">
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

