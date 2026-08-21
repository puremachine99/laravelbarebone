<?php

use App\Enums\ActivityLogAction;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\FoContact;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\usesPagination;

usesPagination();
layout('layouts.app');

state([
    'search' => '',
    'filterEmployee' => '',
    'sortBy' => 'nama',
    'sortDirection' => 'asc',
    'contact_id' => null,
    'nama' => '',
    'profesi' => '',
    'no_telepon' => '',
    'employee_id' => '',
    'catatan' => '',
    'isEdit' => false,
    'deleteTargetId' => null,
    'showContactModal' => false,
    'showDeleteModal' => false,
]);

$employeeOptions = computed(function () {
  
    return Employee::with('user')
        ->whereHas('department', fn ($q) => $q->where('name', 'Departemen Finance'))
        ->whereHas('user.roles', fn ($q) => $q->whereIn('name', ['Funding Officer', 'Investor Admin']))
        ->get();
});


$isAdminFo = computed(fn () => auth()->user()->hasRole(['Investor Admin', 'Super Admin']));

$currentEmployee = computed(fn () => Employee::with('user')->where('user_id', auth()->id())->first());

$contacts = computed(function () {
    $search = $this->search;
    $filterEmployee = $this->filterEmployee;
    $isAdmin = $this->isAdminFo;
    $ownEmployeeId = $this->currentEmployee?->id;

    return FoContact::query()
        ->with('employee.user')

        ->when(! $isAdmin, fn ($q) => $q->where('employee_id', $ownEmployeeId))
        ->when($search, function ($q) use ($search) {
            $q->where(function ($query) use ($search) {
                $query->whereRaw('LOWER(nama) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereRaw('LOWER(profesi) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhere('no_telepon', 'like', '%'.$search.'%');
            });
        })
        ->when($isAdmin && $filterEmployee, fn ($q) => $q->where('employee_id', $filterEmployee))
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

$updatedFilterEmployee = function () {
    $this->resetPage();
};

$resetForm = function () {
    $this->reset(['contact_id', 'nama', 'profesi', 'no_telepon', 'employee_id', 'catatan', 'isEdit']);
    $this->resetValidation();
};

$showCreate = function () {
    $this->resetForm();

    if (! $this->isAdminFo) {
        $this->employee_id = $this->currentEmployee?->id;
    }

    $this->showContactModal = true;
};

$editContact = function ($id) {
    $this->resetForm();
    $contact = FoContact::findOrFail($id);

    if (! $this->isAdminFo && $contact->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $this->contact_id = $contact->id;
    $this->nama = $contact->nama;
    $this->profesi = $contact->profesi;
    $this->no_telepon = $contact->no_telepon;
    $this->employee_id = $contact->employee_id;
    $this->catatan = $contact->catatan;
    $this->isEdit = true;
    $this->showContactModal = true;
};

$saveContact = function () {
    $this->validate([
        'nama' => ['required', 'string', 'max:255'],
        'profesi' => ['nullable', 'string', 'max:255'],
        'no_telepon' => ['nullable', 'regex:/^[0-9]{9,15}$/'],
        'employee_id' => ['nullable', 'exists:employees,id'],
        'catatan' => ['nullable', 'string'],
    ], [
        'nama.required' => 'Nama kontak wajib diisi.',
        'no_telepon.regex' => 'No HP harus berupa angka, 9-15 digit.',
    ]);

    if ($this->contact_id && ! $this->isAdminFo) {
        $existing = FoContact::find($this->contact_id);

        if (! $existing || $existing->employee_id !== $this->currentEmployee?->id) {
            abort(403);
        }
    }

    $employeeId = $this->isAdminFo
        ? ($this->employee_id ?: null)
        : $this->currentEmployee?->id;

    $isNew = is_null($this->contact_id);
    $before = $isNew ? null : FoContact::find($this->contact_id)?->only(['nama', 'profesi', 'no_telepon', 'employee_id', 'catatan']);

    $contact = FoContact::updateOrCreate(
        ['id' => $this->contact_id],
        [
            'nama' => $this->nama,
            'profesi' => $this->profesi ?: null,
            'no_telepon' => $this->no_telepon ?: null,
            'employee_id' => $employeeId,
            'catatan' => $this->catatan ?: null,
        ],
    );

    ActivityLog::record(
        $contact,
        $isNew ? ActivityLogAction::ContactCreated : ActivityLogAction::ContactUpdated,
        $before,
        $contact->only(['nama', 'profesi', 'no_telepon', 'employee_id', 'catatan']),
    );

    $this->resetForm();
    $this->showContactModal = false;
    session()->flash('success', $isNew ? 'Kontak berhasil ditambahkan!' : 'Kontak berhasil diperbarui!');
};

$confirmDelete = function ($id) {
    $contact = FoContact::findOrFail($id);

    if (! $this->isAdminFo && $contact->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $this->deleteTargetId = $id;
    $this->showDeleteModal = true;
};

$deleteContact = function ($id = null) {
    $targetId = $id ?: $this->deleteTargetId;

    if ($targetId) {
        $contact = FoContact::findOrFail($targetId);

        if (! $this->isAdminFo && $contact->employee_id !== $this->currentEmployee?->id) {
            abort(403);
        }

        ActivityLog::record($contact, ActivityLogAction::ContactDeleted, before: $contact->only([
            'nama', 'profesi', 'no_telepon', 'employee_id', 'catatan',
        ]));

        $contact->delete();
        session()->flash('error', 'Kontak berhasil dihapus.');
    }
    $this->showDeleteModal = false;
    $this->reset(['deleteTargetId']);
};

$contactsCount = computed(function () {
    return FoContact::query()
        ->when(! $this->isAdminFo, fn ($q) => $q->where('employee_id', $this->currentEmployee?->id))
        ->count();
});

$statsPic = computed(function () {
    $isAdmin = $this->isAdminFo;
    $ownEmployeeId = $this->currentEmployee?->id;

    $base = fn () => FoContact::query()
        ->whereNotNull('employee_id')
        ->when(! $isAdmin, fn ($q) => $q->where('employee_id', $ownEmployeeId));

    $total = $base()->count() ?: 1;

    $pics = $base()
        ->selectRaw('employee_id, count(*) as count')
        ->groupBy('employee_id')
        ->with('employee.user')
        ->orderByDesc('count')
        ->get();

    $top = $pics->first();

    return [
        'top_name' => $top?->employee?->user?->name ?? '-',
        'top_percentage' => $top ? round(($top->count / $total) * 100, 1) : 0,
        'pics' => $pics,
    ];
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
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Card 1: Total Kontak -->
        <div class="card bg-base-100 border border-base-300 p-6 space-y-2 shadow-sm rounded-2xl">
            <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">TOTAL CONTACTS</h3>
            <h2 class="text-2xl mt-1 font-extrabold text-base-content">{{ $this->contactsCount }} Contacts</h2>
            <p class="text-xs text-base-content/40 mt-0.5">Total recorded contacts of prospective investors/funding partners.</p>
        </div>

        <!-- Card 2: PIC Terbanyak -->
        <div class="card bg-base-100 border border-base-300 p-6 space-y-4 flex flex-col justify-between shadow-sm rounded-2xl">
            <div>
                <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">TOP PIC</h3>
                <h2 class="text-xl mt-1 font-extrabold text-base-content">{{ $this->statsPic['top_name'] }}</h2>
                <p class="text-xs text-base-content/40 mt-0.5">
                    Holds {{ $this->statsPic['top_percentage'] }}% of all contacts with an assigned PIC.
                </p>
            </div>

            <div class="space-y-1.5 pt-2">
                @foreach ($this->statsPic['pics']->take(3) as $row)
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-base-content/70">{{ $row->employee?->user?->name ?? '-' }}</span>
                        <span class="text-base-content/50 font-semibold">{{ $row->count }} Contacts</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300 p-6 space-y-6 shadow-sm rounded-2xl">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-base-content">FO Contacts</h2>
                <p class="text-sm text-base-content/50 mt-1">Manage contact data for prospective investors/funding partners.</p>
            </div>
            <x-button variant="primary" icon="o-plus" size="btn-sm" label="Add Contact" wire:click="showCreate" />
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="w-full sm:w-64">
                <x-input wire:model.live.debounce.300ms="search" placeholder="Search name, profession, phone number..." icon="o-magnifying-glass" clearable />
            </div>
            @if ($this->isAdminFo)
                <div class="w-full sm:w-56">
                    @php $picFilterOpts = $this->employeeOptions->map(fn ($e) => ['id' => $e->id, 'name' => $e->user?->name]); @endphp
                    <x-select wire:model.live="filterEmployee" :options="$picFilterOpts" placeholder="All PICs" placeholder-value="" />
                </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th class="cursor-pointer py-3" wire:click="sort('nama')">
                            <div class="flex items-center gap-1">
                                <span>Name</span>
                                @if ($sortBy === 'nama')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                        <th>PIC (FO)</th>
                        <th>Profession / Institution</th>
                        <th class="cursor-pointer" wire:click="sort('no_telepon')">
                            <div class="flex items-center gap-1">
                                <span>Phone</span>
                                @if ($sortBy === 'no_telepon')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                        <th>Note</th>
                        <th class="cursor-pointer" wire:click="sort('created_at')">
                            <div class="flex items-center gap-1">
                                <span>Created At</span>
                                @if ($sortBy === 'created_at')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->contacts as $contact)
                        <tr class="border-b border-base-300 hover:bg-base-200">
                            <td class="font-semibold text-base-content py-3">{{ $contact->nama }}</td>
                            <td>
                                @if ($contact->employee?->user?->name)
                                    <div class="flex items-center gap-2">
                                        <x-user-avatar :user="$contact->employee->user" size="size-6" />
                                        <span class="text-sm">{{ $contact->employee->user->name }}</span>
                                    </div>
                                @else
                                    <span class="text-base-content/40">-</span>
                                @endif
                            </td>
                            <td class="max-w-[220px] truncate" title="{{ $contact->profesi ?? '-' }}">{{ $contact->profesi ?? '-' }}</td>
                            <td>
                                @if ($contact->no_telepon)
                                    <div class="flex items-center gap-1.5">
                                        <x-icon name="o-phone" class="w-3.5 h-3.5 text-base-content/40" />
                                        <span>{{ $contact->no_telepon }}</span>
                                    </div>
                                @else
                                    <span class="text-base-content/40">-</span>
                                @endif
                            </td>
                            <td class="max-w-[200px] truncate text-base-content/50" title="{{ $contact->catatan ?? '-' }}">
                                {{ $contact->catatan ?? '-' }}
                            </td>
                            <td class="text-base-content/50 whitespace-nowrap">{{ $contact->created_at?->format('d M Y H:i') }}</td>
                            <td class="text-right">
                                <div class="flex gap-2 justify-end">
                                    <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="editContact({{ $contact->id }})" />
                                    <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete({{ $contact->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-base-content/50 py-8">
                                No contacts recorded yet.
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
    <x-modal wire:model="showContactModal" title="{{ $isEdit ? 'Edit Contact' : 'Add Contact' }}" subtitle="Please fill in the contact information completely.">
        <form wire:submit.prevent="saveContact" class="space-y-4">
            <x-input label="Full Name" wire:model="nama" placeholder="e.g. John Doe" icon="o-user" required />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input label="Profession / Institution" wire:model="profesi" placeholder="e.g. Director at PT ABC" icon="o-briefcase" maxlength="255" required/>
                <x-input label="Phone" wire:model="no_telepon" type="text" inputmode="numeric" x-mask="99999999999999" placeholder="e.g. 08123456789" icon="o-phone" hint="Numbers only, 9-15 digits." required />
            </div>

            @if ($this->isAdminFo)
                @php $picFormOpts = $this->employeeOptions->map(fn ($e) => ['id' => $e->id, 'name' => $e->user?->name]); @endphp
                <x-select label="PIC (Funding Officer)" wire:model="employee_id" :options="$picFormOpts" icon="o-user-circle" placeholder="Select PIC..." />
            @else
                <x-input label="PIC (Funding Officer)" value="{{ $this->currentEmployee?->user?->name ?? '-' }}" icon="o-user-circle" readonly hint="This contact will automatically be recorded under your name." />
            @endif

            <x-textarea label="Note" wire:model="catatan" placeholder="Additional notes about this contact" rows="3" />

            <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                <x-button label="Cancel" wire:click="$set('showContactModal', false)" class="btn-ghost" />
                <x-button label="Save" type="submit" class="btn-primary" spinner="saveContact" />
            </div>
        </form>
    </x-modal>

    <!-- Confirmation Modal -->
    <x-modal wire:model="showDeleteModal" title="Delete Contact?" subtitle="The contact will be removed from the active list, but related pipeline history will be kept.">
        <div class="flex justify-end gap-2 pt-4">
            <x-button label="Cancel" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
            <x-button label="Yes, Delete" wire:click="deleteContact" class="btn-error text-white" />
        </div>
    </x-modal>
</div>
