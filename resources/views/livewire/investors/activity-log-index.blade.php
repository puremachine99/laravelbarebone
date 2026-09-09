<?php

use App\Enums\ActivityLogAction;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoTarget;
use Illuminate\Support\Facades\Cache;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\usesPagination;

usesPagination();
layout('layouts.app');

state([
    'filterActor' => '',
    'filterBulan' => '',
    'filterTahun' => '',
    'filterTimeStart' => '',
    'filterTimeEnd' => '',

    'bulanOptions' => [['id' => 1, 'name' => 'January'], ['id' => 2, 'name' => 'February'], ['id' => 3, 'name' => 'March'], ['id' => 4, 'name' => 'April'], ['id' => 5, 'name' => 'May'], ['id' => 6, 'name' => 'June'], ['id' => 7, 'name' => 'July'], ['id' => 8, 'name' => 'August'], ['id' => 9, 'name' => 'September'], ['id' => 10, 'name' => 'October'], ['id' => 11, 'name' => 'November'], ['id' => 12, 'name' => 'December']],

    'metricLabels' => [
        'leads' => 'Leads',
        'ql' => 'QL',
        'pitching' => 'Pitching',
        'closing' => 'Closing',
        'nilai_closing' => 'Deal Value',
    ],

    // Field FK internal — udah terwakili di kolom Owner/Subject, gak perlu diulang di kolom Changes.
    'hiddenChangeKeys' => ['employee_id', 'fo_contact_id'],
]);

$isAdminFo = computed(
    fn() => auth()
        ->user()
        ->hasRole(['Investor Admin', 'Super Admin']),
);

$currentEmployee = computed(
    fn() => Employee::with('user')
        ->where('user_id', auth()->id())
        ->first(),
);

$updatedFilterActor = function () {
    $this->resetPage();
};

$updatedFilterBulan = function () {
    $this->resetPage();
};

$updatedFilterTahun = function () {
    $this->resetPage();
};

$updatedFilterTimeStart = function () {
    $this->resetPage();
};

$updatedFilterTimeEnd = function () {
    $this->resetPage();
};

$actorOptions = computed(function () {
    return Cache::remember('activity-log:actor-options', 60, function () {
        return Employee::with('user')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereHas('department', fn($d) => $d->where('name', 'Departemen Finance'))->whereHas('user.roles', fn($r) => $r->whereIn('name', ['Funding Officer', 'Investor Admin']));
                })->orWhereHas('user.roles', fn($r) => $r->where('name', 'Super Admin'));
            })
            ->get()
            ->filter(fn($e) => $e->user)
            ->map(fn($e) => ['id' => $e->user->id, 'name' => $e->user->name])
            ->values()
            ->all();
    });
});

$tahunOptions = computed(function () {
    return Cache::remember('activity-log:tahun-options', 60, function () {
        $yearExpression = ActivityLog::query()->getConnection()->getDriverName() === 'sqlite' ? "CAST(strftime('%Y', created_at) AS INTEGER)" : 'CAST(EXTRACT(YEAR FROM created_at) AS INTEGER)';

        return ActivityLog::query()
            ->selectRaw("{$yearExpression} as year")
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn($year) => ['id' => (int) $year, 'name' => (string) (int) $year])
            ->values()
            ->all();
    });
});

$logs = computed(function () {
    $isAdmin = $this->isAdminFo;
    $ownEmployeeId = $this->currentEmployee?->id;

    return ActivityLog::query()
        ->with('causer')
        ->when(!$isAdmin, fn($q) => $q->where('owner_employee_id', $ownEmployeeId))
        ->when($isAdmin && $this->filterActor, fn($q) => $q->where('causer_id', $this->filterActor))
        ->when($this->filterBulan, fn($q) => $q->whereMonth('created_at', (int) $this->filterBulan))
        ->when($this->filterTahun, fn($q) => $q->whereYear('created_at', (int) $this->filterTahun))
        ->when($this->filterTimeStart, fn($q) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($this->filterTimeStart)))
        ->when($this->filterTimeEnd, fn($q) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($this->filterTimeEnd)))
        ->latest('id')
        ->paginate(15);
});

// Nama karyawan pemilik data (funnel/kontak/target-nya siapa) — diresolve per halaman biar gak N+1,
// diambil dari owner_employee_id kolom (bukan relasi loggable) karena subjeknya bisa udah dihapus.
$ownerNamesById = computed(function () {
    $employeeIds = $this->logs->pluck('owner_employee_id')->filter()->unique();

    return Employee::with('user')->whereIn('id', $employeeIds)->get()->keyBy('id')->map(fn($employee) => $employee->user?->name);
});

$moduleLabel = function ($loggableType) {
    return match ($loggableType) {
        FoFunnel::class => 'Funnel',
        FoContact::class => 'Contact',
        FoTarget::class => 'Target',
        default => class_basename($loggableType),
    };
};

$subjectLabel = function ($log) {
    return match ($log->loggable_type) {
        FoFunnel::class => $log->loggable?->contact?->nama ?? "Funnel #{$log->loggable_id}",
        FoContact::class => $log->loggable?->nama ?? ($log->changes['before']['nama'] ?? ($log->changes['after']['nama'] ?? "Contact #{$log->loggable_id}")),
        FoTarget::class => $this->metricLabels[$log->changes['before']['metric_key'] ?? ($log->changes['after']['metric_key'] ?? null)] ?? 'Target',
        default => "#{$log->loggable_id}",
    };
};

$formatLogValue = function ($value) {
    if ($value === null || $value === '' || $value === []) {
        return '-';
    }

    if (is_array($value)) {
        return implode(', ', array_map(fn($item) => is_scalar($item) ? (string) $item : json_encode($item), $value));
    }

    if (is_numeric($value) && str_contains((string) $value, '.')) {
        return number_format((float) $value, 0, ',', '.');
    }

    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    return (string) $value;
};

$formatChanges = function ($log) {
    $before = $log->changes['before'] ?? null;
    $after = $log->changes['after'] ?? null;
    $keys = array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? [])));
    $parts = [];

    foreach ($keys as $key) {
        if (in_array($key, $this->hiddenChangeKeys, true)) {
            continue;
        }

        $b = $before[$key] ?? null;
        $a = $after[$key] ?? null;

        if ($b === $a) {
            continue;
        }

        $label = ucfirst(str_replace('_', ' ', $key));

        $parts[] = match (true) {
            $before === null => "{$label}: " . $this->formatLogValue($a),
            $after === null => "{$label}: " . $this->formatLogValue($b),
            default => "{$label}: " . $this->formatLogValue($b) . ' → ' . $this->formatLogValue($a),
        };
    }

    return $parts === [] ? '-' : implode(', ', $parts);
};

?>

<div class="space-y-6">
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <div class="flex items-center gap-2">
            <h2 class="text-xl font-bold text-base-content">Activity Log</h2>
            <span class="badge badge-warning badge-sm">Beta</span>
        </div>
        <p class="text-sm text-base-content/50 mt-1">
            Read-only history of changes across Funnel, Contacts, and Target — who did what, and when.
        </p>
    </div>

    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            @if ($this->isAdminFo)
                <div class="w-full sm:w-56">
                    <x-select label="Actor" wire:model.live="filterActor" :options="$this->actorOptions" placeholder="All Actors"
                        placeholder-value="" />
                </div>
            @endif
            <div class="w-full sm:w-44">
                <x-select label="Month" wire:model.live="filterBulan" :options="$bulanOptions" placeholder="All Months"
                    placeholder-value="" />
            </div>
            <div class="w-full sm:w-32">
                <x-select label="Year" wire:model.live="filterTahun" :options="$this->tahunOptions" placeholder="All Years"
                    placeholder-value="" />
            </div>
            <div class="w-full sm:w-56">
                <x-input label="From" type="datetime-local" wire:model.live="filterTimeStart"
                    class="datetime-input-fix" />
            </div>
            <div class="w-full sm:w-56">
                <x-input label="To" type="datetime-local" wire:model.live="filterTimeEnd"
                    class="datetime-input-fix" />
            </div>

        </div>

        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th class="whitespace-nowrap">Time</th>
                        <th>Actor</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Owner (FO)</th>
                        <th>Subject</th>
                        <th>Changes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->logs as $log)
                        <tr class="border-b border-base-300 align-top">
                            <td class="whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $log->causer?->name ?? '-' }}</td>
                            <td>
                                <span
                                    class="badge badge-sm badge-outline">{{ $this->moduleLabel($log->loggable_type) }}</span>
                            </td>
                            <td>{{ $log->action->label() }}</td>
                            <td>{{ $this->ownerNamesById[$log->owner_employee_id] ?? '-' }}</td>
                            <td class="max-w-[180px] truncate" title="{{ $this->subjectLabel($log) }}">
                                {{ $this->subjectLabel($log) }}</td>
                            <td class="max-w-[320px] text-xs">{{ $this->formatChanges($log) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-base-content/50 py-8">No activity recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $this->logs->links() }}
        </div>
    </div>
</div>
