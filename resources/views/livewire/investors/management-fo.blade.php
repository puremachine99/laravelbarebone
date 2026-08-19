<?php

use App\Enums\ActivityLogAction;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoTarget;
use App\Notifications\FoTargetUpdatedNotification;
use App\Support\Money;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;
use function Livewire\Volt\uses;

layout('layouts.app');
uses(\Mary\Traits\Toast::class);

state([
    'activePeriod' => 'tahunan',
    'targetSettingEmployeeId' => null,
    'targetInputs' => [],
    'targetInputsSnapshot' => [],
]);

$metricsData = [
    ['key' => 'leads', 'label' => 'Leads', 'icon' => 'o-user-plus', 'type' => 'count'],
    ['key' => 'ql', 'label' => 'QL', 'icon' => 'o-check-badge', 'type' => 'count'],
    ['key' => 'pitching', 'label' => 'Pitching', 'icon' => 'o-presentation-chart-line', 'type' => 'count'],
    ['key' => 'closing', 'label' => 'Closing', 'icon' => 'o-trophy', 'type' => 'count'],
    ['key' => 'nilai_closing', 'label' => 'Deal Value', 'icon' => 'o-banknotes', 'type' => 'currency'],
];

state(['metrics' => $metricsData]);

// Halaman ini boleh dilihat semua FO / Admin FO; cuma Admin FO / Super Admin yang boleh ubah target.
$canManageTargets = computed(function () {
    return auth()->user()->hasRole(['Investor Admin', 'Super Admin']);
});

// Isi ulang buffer input target (dan snapshot-nya) sesuai FO yang lagi dipilih & tab periode aktif, biar
// form gak "nyangkut" nilai sebelumnya dan biar saveTargets() bisa tau metrik mana aja yang beneran diubah.
// Nilai yang ditampilkan mengikuti tab aktif (tahunan/bulanan/harian), hasil pecahan dari yearly_target.
$loadTargetInputs = function () {
    $values = collect($this->metrics)
        ->mapWithKeys(function ($metric) {
            $yearly = $this->yearlyTargetFor($this->targetSettingEmployeeId, $metric['key']);
            $value = $this->splitTarget($yearly)[$this->activePeriod];
            $value = $metric['type'] === 'currency' ? round($value) : round($value, 2);

            return [$metric['key'] => (string) $value];
        })
        ->all();

    $this->targetInputs = $values;
    $this->targetInputsSnapshot = $values;
};

mount(function () {
    abort_unless(auth()->user()->hasRole(['Investor Admin', 'Super Admin', 'Funding Officer']), 403);

    $this->targetSettingEmployeeId = $this->foEmployees[0]['id'] ?? null;
    $this->loadTargetInputs();
});

$updatedTargetSettingEmployeeId = function () {
    $this->loadTargetInputs();
};

// Ganti tab periode → buffer input harus ikut ganti ke nilai pecahan periode itu juga.
$updatedActivePeriod = function () {
    $this->loadTargetInputs();
};

// Daftar Funding Officer (+ Admin FO) yang bisa diatur target-nya.
$foEmployees = computed(function () {
    return Employee::with('user')
        ->whereHas('department', fn ($q) => $q->where('name', 'Departemen Finance'))
        ->whereHas('user.roles', fn ($q) => $q->whereIn('name', ['Funding Officer', 'Investor Admin']))
        ->get()
        ->map(fn ($e) => ['id' => $e->id, 'name' => $e->user?->name])
        ->sortBy('name')
        ->values()
        ->all();
});

$suggestedYearlyTarget = function ($employeeId, $metricKey) {
    $firstActivity = FoFunnel::where('employee_id', $employeeId)->min('tgl_leads');

    if (! $firstActivity) {
        return 0;
    }

    $dataSpanDays = max(90, abs(now()->diffInDays($firstActivity)));
    $annualizeFactor = 365 / $dataSpanDays;

    $allTimeTotal = match ($metricKey) {
        'leads' => FoFunnel::where('employee_id', $employeeId)->count(),
        'ql' => FoFunnel::where('employee_id', $employeeId)->whereNotNull('tgl_qualified')->count(),
        'pitching' => FoFunnelActivity::where('stage', 'pitching')
            ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId)->stageAtLeast('pitching'))
            ->pluck('fo_funnel_id')
            ->unique()
            ->count(),
        'closing' => FoFunnel::where('employee_id', $employeeId)->whereIn('stage', FoFunnel::CLOSED_STAGES)->count(),
        'nilai_closing' => (float) FoFunnel::where('employee_id', $employeeId)
            ->whereIn('stage', FoFunnel::CLOSED_STAGES)
            ->sum('nominal_investasi'),
        default => 0,
    };

    return round($allTimeTotal * $annualizeFactor);
};

// Rekomendasi
$splitTarget = function ($yearly) {
    return [
        'tahunan' => $yearly,
        'bulanan' => $yearly / 12,
        'harian' => $yearly / 12 / 25,
    ];
};

// Kebalikan dari splitTarget() — dipakai saat user ngedit target langsung dari tab Bulanan/Harian,
// biar yearly_target (satu-satunya yang disimpan di DB) tetap jadi sumber kebenaran yang konsisten.
$toYearly = function ($value, $period) {
    if ($value === null) {
        return null;
    }

    return match ($period) {
        'bulanan' => $value * 12,
        'harian' => $value * 12 * 25,
        default => $value,
    };
};

// Parse nilai mentah dari input form sesuai tipe metrik. Currency pakai Money::normalize (input bermasking,
// tanpa desimal). Metrik hitungan (count) bisa berupa pecahan waktu diedit dari tab Bulanan/Harian, jadi
// gak boleh lewat Money::normalize (itu bakal ngestrip titik desimalnya dan bikin nilainya salah total).
$parseInputValue = function ($value, $type) {
    if ($type === 'currency') {
        return Money::normalize($value);
    }

    if ($value === null || $value === '' || ! is_numeric($value)) {
        return null;
    }

    return (float) $value;
};

// Target 
$yearlyTargetFor = function ($employeeId, $metricKey) {
    $saved = FoTarget::where('employee_id', $employeeId)
        ->where('year', now()->year)
        ->where('metric_key', $metricKey)
        ->value('yearly_target');

    return $saved !== null ? (float) $saved : $this->suggestedYearlyTarget($employeeId, $metricKey);
};

$targetFor = function ($employeeId, $metricKey) {
    $yearly = $this->yearlyTargetFor($employeeId, $metricKey);

    return $this->splitTarget($yearly)[$this->activePeriod];
};

// Rentang tanggal buat tab periode yang lagi aktif (tahun berjalan / bulan berjalan / hari ini).
$periodRange = function () {
    return match ($this->activePeriod) {
        'bulanan' => [now()->startOfMonth(), now()->endOfMonth()],
        'harian' => [now()->startOfDay(), now()->endOfDay()],
        default => [now()->startOfYear(), now()->endOfYear()],
    };
};

// Realisasi = data funnel yang beneran sudah terjadi di rentang tanggal tertentu, milik FO tersebut.
// Dipisah dari periode aktif (tahunan/bulanan/harian) biar bisa dipakai ulang buat rekap bulanan custom.
$realisasiInRange = function ($employeeId, $metricKey, $start, $end) {
    return match ($metricKey) {
        'leads' => FoFunnel::where('employee_id', $employeeId)
            ->whereBetween('tgl_leads', [$start, $end])
            ->count(),
        'ql' => FoFunnel::where('employee_id', $employeeId)
            
            ->whereNotNull('tgl_qualified')
            ->whereBetween('tgl_qualified', [$start, $end])
            ->count(),
        'pitching' => FoFunnelActivity::where('stage', 'pitching')
            ->whereBetween('tanggal', [$start, $end])
            ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId)->stageAtLeast('pitching'))
            ->pluck('fo_funnel_id')
            ->unique()
            ->count(),
        'won' => FoFunnel::where('employee_id', $employeeId)
            ->stageAtLeast('won')
            ->whereBetween('tgl_won', [$start, $end])
            ->count(),
        'closing' => FoFunnel::where('employee_id', $employeeId)
            ->whereIn('stage', FoFunnel::CLOSED_STAGES)
            ->whereBetween('tgl_closing', [$start, $end])
            ->count(),
        'nilai_closing' => (float) FoFunnel::where('employee_id', $employeeId)
            ->whereIn('stage', FoFunnel::CLOSED_STAGES)
            ->whereBetween('tgl_closing', [$start, $end])
            ->sum('nominal_investasi'),
        default => 0,
    };
};

$realisasiFor = function ($employeeId, $metricKey) {
    [$start, $end] = $this->periodRange();

    return $this->realisasiInRange($employeeId, $metricKey, $start, $end);
};

// Proyeksi cuma relevan buat Closing & Nilai Closing — funnel yang masih diproses (Pitching/Follow Up, belum
// Won/Lost) dengan target tanggal closing jatuh di periode ini. Estimasi "kemungkinan bakal closing".
$proyeksiFor = function ($employeeId, $metricKey) {
    [$start, $end] = $this->periodRange();

    return match ($metricKey) {
        'closing' => FoFunnel::where('employee_id', $employeeId)
            ->whereIn('stage', ['pitching', 'followup'])
            ->whereBetween('proyeksi_close_date', [$start, $end])
            ->count(),
        'nilai_closing' => (float) FoFunnel::where('employee_id', $employeeId)
            ->whereIn('stage', ['pitching', 'followup'])
            ->whereBetween('proyeksi_close_date', [$start, $end])
            ->sum('proyeksi_amount'),
        default => null,
    };
};

// Catat siapa yang ubah, target siapa, metrik apa, nilai sebelum & sesudah — buat audit trail.
// employee_id & metric_key disimpan di payload-nya sendiri (bukan cuma diambil dari relasi $target)
// karena reset target beneran hapus baris FoTarget-nya — relasi loggable bakal null setelah itu,
// tapi histori "target siapa yang direset" tetap harus kebaca.
$logTargetChange = function ($target, $employeeId, $metricKey, $oldValue, $newValue) {
    ActivityLog::record(
        $target,
        ActivityLogAction::TargetUpdated,
        $oldValue !== null ? ['employee_id' => $employeeId, 'metric_key' => $metricKey, 'yearly_target' => $oldValue] : null,
        $newValue !== null ? ['employee_id' => $employeeId, 'metric_key' => $metricKey, 'yearly_target' => $newValue] : null,
    );
};

// Persist satu metrik target (dipanggil per-metrik dari saveTargets() saat tombol Simpan ditekan).
// $value di sini udah berupa angka tahunan bersih (hasil parseInputValue() + toYearly() dari saveTargets()).
// Return array from/to sukses (dipakai buat detail notifikasi FO), atau false kalau nilainya invalid,
// biar saveTargets() bisa kumpulin nilai yang invalid tanpa spam toast per-field.
$updateYearlyTarget = function ($employeeId, $metricKey, $value) {
    abort_unless(auth()->user()->hasRole(['Investor Admin', 'Super Admin']), 403);

    if ($value === null || $value < 0) {
        return false;
    }

    $existing = FoTarget::where('employee_id', $employeeId)
        ->where('year', now()->year)
        ->where('metric_key', $metricKey)
        ->first();

    $target = FoTarget::updateOrCreate(
        ['employee_id' => $employeeId, 'year' => now()->year, 'metric_key' => $metricKey],
        ['yearly_target' => $value],
    );

    $this->logTargetChange($target, $employeeId, $metricKey, $existing?->yearly_target, $value);

    return ['from' => $existing?->yearly_target, 'to' => $value];
};

// Tombol "Simpan Target" — cuma metrik yang nilainya beneran berubah dari snapshot terakhir yang di-submit,
// biar gak nulis FoTarget/FoTargetLog buat metrik yang gak disentuh user. Input di form ngikutin tab periode
// aktif (tahunan/bulanan/harian), jadi sebelum disimpan nilainya dikonversi balik ke tahunan dulu — apa pun
// tab yang lagi dipakai buat ngedit, yearly_target di DB yang jadi sumber kebenaran tunggalnya.
$saveTargets = function () {
    abort_unless(auth()->user()->hasRole(['Investor Admin', 'Super Admin']), 403);

    if (! $this->targetSettingEmployeeId) {
        return;
    }

    $changed = false;
    $hasInvalid = false;
    $changes = [];

    foreach ($this->metrics as $metric) {
        $key = $metric['key'];
        $rawValue = $this->targetInputs[$key] ?? null;

        if ((string) $rawValue === (string) ($this->targetInputsSnapshot[$key] ?? null)) {
            continue;
        }

        $changed = true;

        $yearlyValue = $this->toYearly($this->parseInputValue($rawValue, $metric['type']), $this->activePeriod);

        $result = $this->updateYearlyTarget($this->targetSettingEmployeeId, $key, $yearlyValue);

        if ($result) {
            $changes[] = [
                'label' => $metric['label'],
                'type' => $metric['type'],
                // Pakai nilai yang tadinya tampil di form (bisa jadi saran otomatis, bukan cuma target
                // tersimpan di DB), biar FO lihat perbandingan yang sama persis kayak yang Admin lihat.
                // Dikonversi ke tahunan juga biar konsisten sama $result['to'] yang selalu dalam satuan tahunan.
                'from' => $this->toYearly($this->parseInputValue($this->targetInputsSnapshot[$key] ?? null, $metric['type']), $this->activePeriod),
                'to' => $result['to'],
            ];
        } else {
            $hasInvalid = true;
        }
    }

    $this->loadTargetInputs();

    if ($changes !== []) {
        $employee = Employee::find($this->targetSettingEmployeeId);

        if ($employee) {
            FoTargetUpdatedNotification::sendToOwner($employee, $changes, now()->year);
        }
    }

    if ($hasInvalid) {
        $this->error('Ada nilai target yang gak valid, sebagian gak tersimpan.');
    } elseif ($changed) {
        $this->success('Target tahunan diperbarui dan tersimpan.');
    } else {
        $this->success('Gak ada perubahan buat disimpan.');
    }
};

// Cek apakah metrik ini punya target manual tersimpan (buat nampilin tombol reset di UI atau nggak).
$hasSavedTarget = function ($employeeId, $metricKey) {
    return FoTarget::where('employee_id', $employeeId)
        ->where('year', now()->year)
        ->where('metric_key', $metricKey)
        ->exists();
};

// Hapus target manual, balik ke saran otomatis dari data historis.
$resetYearlyTarget = function ($employeeId, $metricKey) {
    abort_unless(auth()->user()->hasRole(['Investor Admin', 'Super Admin']), 403);

    $existing = FoTarget::where('employee_id', $employeeId)
        ->where('year', now()->year)
        ->where('metric_key', $metricKey)
        ->first();

    if (! $existing) {
        return;
    }

    $oldValue = $existing->yearly_target;
    $existing->delete();

    $this->logTargetChange($existing, $employeeId, $metricKey, $oldValue, null);

    $this->loadTargetInputs();

    $this->success('Target di-reset, balik ke saran otomatis dari data historis.');
};

// Riwayat perubahan target — terbaru duluan, dipakai buat audit "siapa ubah apa".
// employee_id diambil dari payload changes (bukan relasi loggable), karena reset target beneran
// hapus baris FoTarget-nya — relasi ke situ udah gak ada meski logn-nya sendiri tetap harus kebaca.
$targetChangeLogs = computed(function () {
    $logs = ActivityLog::with('causer')
        ->where('loggable_type', FoTarget::class)
        ->where('action', ActivityLogAction::TargetUpdated)
        ->whereYear('created_at', now()->year)
        ->latest()
        ->limit(30)
        ->get();

    $employeeIds = $logs
        ->map(fn ($log) => $log->changes['before']['employee_id'] ?? $log->changes['after']['employee_id'] ?? null)
        ->filter()
        ->unique();

    $employeesById = Employee::with('user')->whereIn('id', $employeeIds)->get()->keyBy('id');

    return $logs->map(function ($log) use ($employeesById) {
        $employeeId = $log->changes['before']['employee_id'] ?? $log->changes['after']['employee_id'] ?? null;
        $log->targetOwnerName = $employeesById->get($employeeId)?->user?->name;

        return $log;
    });
});

// Rekap performa 6 bulan terakhir buat FO yang lagi dipilih di dropdown "Atur Target Tahunan".
$monthlyRekap = computed(function () {
    $employeeId = $this->targetSettingEmployeeId;

    if (! $employeeId) {
        return [];
    }

    return collect(range(5, 0))->map(function ($monthsAgo) use ($employeeId) {
        $start = now()->subMonths($monthsAgo)->startOfMonth();
        $end = now()->subMonths($monthsAgo)->endOfMonth();

        return [
            'label' => $start->translatedFormat('M Y'),
            'leads' => $this->realisasiInRange($employeeId, 'leads', $start, $end),
            'ql' => $this->realisasiInRange($employeeId, 'ql', $start, $end),
            'pitching' => $this->realisasiInRange($employeeId, 'pitching', $start, $end),
            'won' => $this->realisasiInRange($employeeId, 'won', $start, $end),
            'closing' => $this->realisasiInRange($employeeId, 'closing', $start, $end),
            'nilai_closing' => $this->realisasiInRange($employeeId, 'nilai_closing', $start, $end),
        ];
    })->values()->all();
});

// Ranking semua FO tahun berjalan — buat bandingin performa antar FO, bukan cuma satu-satu.
$foRanking = computed(function () {
    $start = now()->startOfYear();
    $end = now()->endOfYear();

    return collect($this->foEmployees)->map(function ($fo) use ($start, $end) {
        $nilaiClosing = $this->realisasiInRange($fo['id'], 'nilai_closing', $start, $end);
        $target = $this->yearlyTargetFor($fo['id'], 'nilai_closing');

        return [
            'name' => $fo['name'],
            'won' => $this->realisasiInRange($fo['id'], 'won', $start, $end),
            'closing' => $this->realisasiInRange($fo['id'], 'closing', $start, $end),
            'nilai_closing' => $nilaiClosing,
            'target' => $target,
            'achievement' => $target > 0 ? round($nilaiClosing / $target * 100, 1) : null,
        ];
    })->sortByDesc('nilai_closing')->values()->all();
});

?>

<div class="space-y-4">
    <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
        <h2 class="text-lg font-bold text-base-content">FO Management</h2>
        <p class="text-xs text-base-content/50">
            Set & monitor Funding Officer work targets — edit from the Yearly, Monthly, or Daily tab, everything else adjusts automatically.
        </p>
        <div class="badge badge-warning badge-outline badge-sm mt-1.5">Suggested target based on historical data · can be edited manually · Realization & Projection from real funnel data</div>
    </div>

    <!-- Period (full width) -->
    <div class="card bg-base-100 border border-base-300 p-3 rounded-xl">
        <div role="tablist" class="tabs tabs-boxed tabs-sm w-fit">
            <a role="tab" wire:click="$set('activePeriod', 'tahunan')" @class(['tab', 'tab-active' => $activePeriod === 'tahunan'])>Yearly</a>
            <a role="tab" wire:click="$set('activePeriod', 'bulanan')" @class(['tab', 'tab-active' => $activePeriod === 'bulanan'])>Monthly</a>
            <a role="tab" wire:click="$set('activePeriod', 'harian')" @class(['tab', 'tab-active' => $activePeriod === 'harian'])>Daily</a>
        </div>
    </div>

    @php
        $periodLabels = ['tahunan' => 'Yearly', 'bulanan' => 'Monthly', 'harian' => 'Daily'];
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
        <!-- Left: target setting -->
        <div class="card bg-base-100 border border-base-300 p-4 rounded-xl md:col-span-4">
            <div class="text-sm font-semibold text-base-content mb-3">Set {{ $periodLabels[$activePeriod] }} Target</div>

            <x-select wire:model.live="targetSettingEmployeeId" :options="$this->foEmployees" class="select-sm mb-3" />

            @if ($this->canManageTargets)
                <form wire:submit="saveTargets" class="space-y-3">
                    @foreach ($metrics as $metric)
                        <div class="flex items-end gap-1">
                            @if ($metric['type'] === 'currency')
                                <x-input
                                    :label="$metric['label']"
                                    type="text"
                                    inputmode="numeric"
                                    x-mask:dynamic="$money($input, ',', '.', 0)"
                                    wire:model="targetInputs.{{ $metric['key'] }}"
                                    class="input-sm"
                                    wire:key="target-input-{{ $targetSettingEmployeeId }}-{{ $metric['key'] }}"
                                />
                            @else
                                <x-input
                                    :label="$metric['label']"
                                    type="number"
                                    step="any"
                                    wire:model="targetInputs.{{ $metric['key'] }}"
                                    class="input-sm"
                                    wire:key="target-input-{{ $targetSettingEmployeeId }}-{{ $metric['key'] }}-{{ $activePeriod }}"
                                />
                            @endif
                            @if ($this->hasSavedTarget($targetSettingEmployeeId, $metric['key']))
                                <x-button
                                    icon="o-arrow-path"
                                    class="btn-ghost btn-xs text-base-content/50 mb-1.5"
                                    tooltip="Reset to auto suggestion"
                                    wire:click="resetYearlyTarget({{ $targetSettingEmployeeId }}, '{{ $metric['key'] }}')"
                                />
                            @endif
                        </div>
                    @endforeach
                    <div class="text-[11px] text-base-content/40">A value without a reset button = auto suggestion from historical data, not manually set yet. Changes are only saved after clicking Save.</div>
                    <x-button label="Save Target" type="submit" icon="o-check" class="btn-primary btn-sm w-full mt-1" spinner="saveTargets" />
                </form>
            @else
                <div class="space-y-2">
                    @foreach ($metrics as $metric)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-base-content/60">{{ $metric['label'] }}</span>
                            <span class="font-medium text-base-content">
                                @if ($metric['type'] === 'currency')
                                    <x-currency-abbr :value="$this->targetFor($targetSettingEmployeeId, $metric['key'])" />
                                @else
                                    {{ number_format($this->targetFor($targetSettingEmployeeId, $metric['key']), $activePeriod === 'harian' ? 1 : 0) }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                    <div class="text-[11px] text-base-content/40 pt-1">Only FO Admin / Super Admin can change the target.</div>
                </div>
            @endif
        </div>

        <!-- Right: current data -->
        <div class="md:col-span-8 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                @foreach ($this->foEmployees as $fo)
                    <div class="card bg-base-100 border border-base-300 p-3 rounded-xl">
                        <div class="font-semibold text-sm text-base-content truncate mb-2" title="{{ $fo['name'] }}">{{ $fo['name'] }}</div>

                        <div class="space-y-1">
                            @foreach ($metrics as $metric)
                                @php
                                    $hasProyeksi = in_array($metric['key'], ['closing', 'nilai_closing'], true);
                                    $isCurrency = $metric['type'] === 'currency';
                                    $target = $this->targetFor($fo['id'], $metric['key']);
                                    $proyeksi = $hasProyeksi ? $this->proyeksiFor($fo['id'], $metric['key']) : null;
                                    $realisasi = $this->realisasiFor($fo['id'], $metric['key']);
                                    $gap = $realisasi - $target;
                                    $fmt = fn ($v) => $isCurrency ? Money::abbreviate($v) : number_format($v, $activePeriod === 'harian' ? 1 : 0);
                                    $exactSuffix = fn ($v) => $isCurrency ? ' Value: '.Money::exact($v).'.' : '';
                                @endphp
                                <div class="flex items-center justify-between gap-1 text-xs">
                                    <span class="text-base-content/50 shrink-0">{{ $metric['label'] }}</span>
                                    <span class="text-right">
                                        <span class="tooltip tooltip-left" data-tip="Realization — what has actually happened in this period. {{ $gap >= 0 ? 'Green = target reached/exceeded.' : 'Red = still below target.' }}{{ $exactSuffix($realisasi) }}">
                                            <span class="font-medium {{ $gap >= 0 ? 'text-success' : 'text-error' }}">{{ $fmt($realisasi) }}</span>
                                        </span>
                                        @if ($hasProyeksi)
                                            <span class="tooltip tooltip-left" data-tip="Projection — additional estimate from funnels still in progress (Pitching/Follow Up), not yet a confirmed closing.{{ $exactSuffix($proyeksi) }}">
                                                <span class="text-warning">({{ $fmt($proyeksi) }})</span>
                                            </span>
                                        @endif
                                        <span class="tooltip tooltip-left" data-tip="Target — the {{ $periodLabels[$activePeriod] }} target figure (manual if already set by an admin, or an auto suggestion from historical data if not).{{ $exactSuffix($target) }}">
                                            <span class="text-base-content/30">/ {{ $fmt($target) }}</span>
                                        </span>
                                    </span>
                                </div>
                            @endforeach

                            @php $wonRealisasi = $this->realisasiFor($fo['id'], 'won'); @endphp
                            <div class="flex items-center justify-between gap-1 text-xs">
                                <span class="text-base-content/50 shrink-0">Won</span>
                                <span class="tooltip tooltip-left" data-tip="Won — a prospective investor who has verbally committed, but the money hasn't necessarily been received yet (see Closing).">
                                    <span class="font-medium text-base-content">{{ number_format($wonRealisasi, $activePeriod === 'harian' ? 1 : 0) }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-xs text-base-content/40 px-1">Format: Realization <span class="text-warning">(Projection)</span> / Target — green/red shows whether the target has been exceeded</div>

            <!-- All FO Summary -->
            <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
                <div class="text-xs font-semibold text-base-content/50 mb-2">Total All FO</div>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    @foreach ($metrics as $metric)
                        @php
                            $totalTarget = collect($this->foEmployees)->sum(fn ($fo) => $this->targetFor($fo['id'], $metric['key']));
                            $totalRealisasi = collect($this->foEmployees)->sum(fn ($fo) => $this->realisasiFor($fo['id'], $metric['key']));
                        @endphp
                        <div>
                            <span class="text-base-content/50">{{ $metric['label'] }}</span>
                            <span class="font-semibold text-base-content ms-1">
                                @if ($metric['type'] === 'currency')
                                    <x-currency-abbr :value="$totalRealisasi" />
                                @else
                                    {{ number_format($totalRealisasi, 0) }}
                                @endif
                            </span>
                            <span class="text-base-content/40">
                                / @if ($metric['type'] === 'currency')
                                    <x-currency-abbr :value="$totalTarget" />
                                @else
                                    {{ number_format($totalTarget, 0) }}
                                @endif
                            </span>
                        </div>
                    @endforeach

                    @php $totalWon = collect($this->foEmployees)->sum(fn ($fo) => $this->realisasiFor($fo['id'], 'won')); @endphp
                    <div>
                        <span class="text-base-content/50">Won</span>
                        <span class="font-semibold text-base-content ms-1">{{ number_format($totalWon, 0) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Last 6 Months Recap (FO selected in the left dropdown) -->
    <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
        <div class="text-sm font-semibold text-base-content mb-1">Last 6 Months Recap — {{ collect($this->foEmployees)->firstWhere('id', $targetSettingEmployeeId)['name'] ?? '-' }}</div>
        <p class="text-xs text-base-content/50 mb-3">Monthly realization trend, not a snapshot of the active period above.</p>
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>Month</th>
                        @foreach ($metrics as $metric)
                            <th class="text-right">{{ $metric['label'] }}</th>
                        @endforeach
                        <th class="text-right">Won</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->monthlyRekap as $row)
                        <tr class="border-b border-base-300">
                            <td class="font-medium text-base-content">{{ $row['label'] }}</td>
                            @foreach ($metrics as $metric)
                                <td class="text-right">
                                    @if ($metric['type'] === 'currency')
                                        <x-currency-abbr :value="$row[$metric['key']]" />
                                    @else
                                        {{ number_format($row[$metric['key']]) }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-right">{{ number_format($row['won']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($metrics) + 2 }}" class="text-center text-base-content/50 py-4">Select an FO in the left panel first.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- All FO Ranking (current year) -->
    <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
        <div class="text-sm font-semibold text-base-content mb-1">FO Ranking This Year</div>
        <p class="text-xs text-base-content/50 mb-3">Compared by current-year Deal Value vs. the yearly target.</p>
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>#</th>
                        <th>FO Name</th>
                        <th class="text-right">Won</th>
                        <th class="text-right">Closing</th>
                        <th class="text-right">Deal Value</th>
                        <th class="text-right">Target</th>
                        <th class="text-right">Achievement</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->foRanking as $i => $fo)
                        <tr class="border-b border-base-300">
                            <td>{{ $i + 1 }}</td>
                            <td class="font-medium text-base-content">{{ $fo['name'] }}</td>
                            <td class="text-right">{{ number_format($fo['won']) }}</td>
                            <td class="text-right">{{ number_format($fo['closing']) }}</td>
                            <td class="text-right"><x-currency-abbr :value="$fo['nilai_closing']" /></td>
                            <td class="text-right text-base-content/40"><x-currency-abbr :value="$fo['target']" /></td>
                            <td class="text-right">
                                @if ($fo['achievement'] !== null)
                                    <span class="badge badge-sm {{ $fo['achievement'] >= 100 ? 'badge-success' : 'badge-warning' }} badge-outline">{{ $fo['achievement'] }}%</span>
                                @else
                                    <span class="text-base-content/30">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-base-content/50 py-4">No FO data yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Target Change History (audit trail) -->
    <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
        <div class="text-sm font-semibold text-base-content mb-1">Target Change History</div>
        <p class="text-xs text-base-content/50 mb-3">Last 30 changes this year — who changed it, whose target, which metric, from what to what.</p>
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>Time</th>
                        <th>Changed By</th>
                        <th>Target Owner</th>
                        <th>Metric</th>
                        <th class="text-right">From</th>
                        <th class="text-right">To</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->targetChangeLogs as $log)
                        @php
                            $logMetricKey = $log->changes['before']['metric_key'] ?? $log->changes['after']['metric_key'] ?? null;
                            $logOldValue = $log->changes['before']['yearly_target'] ?? null;
                            $logNewValue = $log->changes['after']['yearly_target'] ?? null;
                            $logMetric = collect($metrics)->firstWhere('key', $logMetricKey);
                            $logIsCurrency = $logMetric && $logMetric['type'] === 'currency';
                        @endphp
                        <tr class="border-b border-base-300">
                            <td class="whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $log->causer?->name ?? '-' }}</td>
                            <td>{{ $log->targetOwnerName ?? '-' }}</td>
                            <td>{{ $logMetric['label'] ?? $logMetricKey }}</td>
                            <td class="text-right text-base-content/50">
                                @if (is_null($logOldValue))
                                    Auto suggestion
                                @elseif ($logIsCurrency)
                                    <x-currency-abbr :value="$logOldValue" />
                                @else
                                    {{ number_format($logOldValue) }}
                                @endif
                            </td>
                            <td class="text-right font-medium text-base-content">
                                @if (is_null($logNewValue))
                                    Auto suggestion (reset)
                                @elseif ($logIsCurrency)
                                    <x-currency-abbr :value="$logNewValue" />
                                @else
                                    {{ number_format($logNewValue) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-base-content/50 py-4">No target changes this year yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
