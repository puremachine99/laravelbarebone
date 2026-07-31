<?php

use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoTarget;
use App\Models\FoTargetLog;
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
    ['key' => 'nilai_closing', 'label' => 'Nilai Closing', 'icon' => 'o-banknotes', 'type' => 'currency'],
];

state(['metrics' => $metricsData]);

// Halaman ini boleh dilihat semua FO / Admin FO; cuma Admin FO / Super Admin yang boleh ubah target.
$canManageTargets = computed(function () {
    return auth()->user()->hasRole(['Investor Admin', 'Super Admin']);
});

// Isi ulang buffer input target (dan snapshot-nya) sesuai FO yang lagi dipilih, biar form gak "nyangkut"
// nilai FO sebelumnya dan biar saveTargets() bisa tau metrik mana aja yang beneran diubah user.
$loadTargetInputs = function () {
    $values = collect($this->metrics)
        ->mapWithKeys(fn ($metric) => [
            $metric['key'] => (string) $this->yearlyTargetFor($this->targetSettingEmployeeId, $metric['key']),
        ])
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
            ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId))
            ->pluck('fo_funnel_id')
            ->unique()
            ->count(),
        'closing' => FoFunnel::where('employee_id', $employeeId)->whereIn('stage', ['won', 'akad'])->count(),
        'nilai_closing' => (float) FoFunnel::where('employee_id', $employeeId)
            ->whereIn('stage', ['won', 'akad'])
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
            ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId))
            ->pluck('fo_funnel_id')
            ->unique()
            ->count(),
        'closing' => FoFunnel::where('employee_id', $employeeId)
            ->whereIn('stage', ['won', 'akad'])
            ->whereBetween('tgl_closing', [$start, $end])
            ->count(),
        'nilai_closing' => (float) FoFunnel::where('employee_id', $employeeId)
            ->whereIn('stage', ['won', 'akad'])
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
$logTargetChange = function ($employeeId, $metricKey, $oldValue, $newValue) {
    FoTargetLog::create([
        'employee_id' => $employeeId,
        'changed_by_user_id' => auth()->id(),
        'year' => now()->year,
        'metric_key' => $metricKey,
        'old_value' => $oldValue,
        'new_value' => $newValue,
    ]);
};

// Persist satu metrik target (dipanggil per-metrik dari saveTargets() saat tombol Simpan ditekan).
// Return bool sukses/gagal biar saveTargets() bisa kumpulin nilai yang invalid tanpa spam toast per-field.
$updateYearlyTarget = function ($employeeId, $metricKey, $value) {
    abort_unless(auth()->user()->hasRole(['Investor Admin', 'Super Admin']), 403);

    // Input nominal (nilai_closing) dikirim dari input bermasking (mis. "3.500.000.000") — normalize dulu.
    // Aman juga dipakai buat metrik hitungan biasa (leads, ql, dst) karena isinya cuma digit tanpa pemisah.
    $value = Money::normalize($value);

    if ($value === null || $value < 0) {
        return false;
    }

    $existing = FoTarget::where('employee_id', $employeeId)
        ->where('year', now()->year)
        ->where('metric_key', $metricKey)
        ->first();

    FoTarget::updateOrCreate(
        ['employee_id' => $employeeId, 'year' => now()->year, 'metric_key' => $metricKey],
        ['yearly_target' => $value],
    );

    $this->logTargetChange($employeeId, $metricKey, $existing?->yearly_target, $value);

    return true;
};

// Tombol "Simpan Target" — cuma metrik yang nilainya beneran berubah dari snapshot terakhir yang di-submit,
// biar gak nulis FoTarget/FoTargetLog buat metrik yang gak disentuh user.
$saveTargets = function () {
    abort_unless(auth()->user()->hasRole(['Investor Admin', 'Super Admin']), 403);

    if (! $this->targetSettingEmployeeId) {
        return;
    }

    $changed = false;
    $hasInvalid = false;

    foreach ($this->metrics as $metric) {
        $key = $metric['key'];
        $newValue = $this->targetInputs[$key] ?? null;

        if ((string) $newValue === (string) ($this->targetInputsSnapshot[$key] ?? null)) {
            continue;
        }

        $changed = true;

        if (! $this->updateYearlyTarget($this->targetSettingEmployeeId, $key, $newValue)) {
            $hasInvalid = true;
        }
    }

    $this->loadTargetInputs();

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

    $existing->delete();

    $this->logTargetChange($employeeId, $metricKey, $existing->yearly_target, null);

    $this->loadTargetInputs();

    $this->success('Target di-reset, balik ke saran otomatis dari data historis.');
};

// Riwayat perubahan target — terbaru duluan, dipakai buat audit "siapa ubah apa".
$targetChangeLogs = computed(function () {
    return FoTargetLog::with(['employee.user', 'changedBy'])
        ->where('year', now()->year)
        ->latest()
        ->limit(30)
        ->get();
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
        <h2 class="text-lg font-bold text-base-content">Management FO</h2>
        <p class="text-xs text-base-content/50">
            Atur & pantau target kerja Funding Officer — target tahunan otomatis dipecah ke bulanan & harian.
        </p>
        <div class="badge badge-warning badge-outline badge-sm mt-1.5">Saran target dari data historis · bisa diubah manual · Realisasi & Proyeksi dari data funnel asli</div>
    </div>

    <!-- Periode (full width) -->
    <div class="card bg-base-100 border border-base-300 p-3 rounded-xl">
        <div role="tablist" class="tabs tabs-boxed tabs-sm w-fit">
            <a role="tab" wire:click="$set('activePeriod', 'tahunan')" @class(['tab', 'tab-active' => $activePeriod === 'tahunan'])>Tahunan</a>
            <a role="tab" wire:click="$set('activePeriod', 'bulanan')" @class(['tab', 'tab-active' => $activePeriod === 'bulanan'])>Bulanan</a>
            <a role="tab" wire:click="$set('activePeriod', 'harian')" @class(['tab', 'tab-active' => $activePeriod === 'harian'])>Harian</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
        <!-- Kiri: setting target -->
        <div class="card bg-base-100 border border-base-300 p-4 rounded-xl md:col-span-4">
            <div class="text-sm font-semibold text-base-content mb-3">Atur Target Tahunan</div>

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
                                    wire:model="targetInputs.{{ $metric['key'] }}"
                                    class="input-sm"
                                    wire:key="target-input-{{ $targetSettingEmployeeId }}-{{ $metric['key'] }}"
                                />
                            @endif
                            @if ($this->hasSavedTarget($targetSettingEmployeeId, $metric['key']))
                                <x-button
                                    icon="o-arrow-path"
                                    class="btn-ghost btn-xs text-base-content/50 mb-1.5"
                                    tooltip="Reset ke saran otomatis"
                                    wire:click="resetYearlyTarget({{ $targetSettingEmployeeId }}, '{{ $metric['key'] }}')"
                                />
                            @endif
                        </div>
                    @endforeach
                    <div class="text-[11px] text-base-content/40">Angka tanpa tombol reset = saran otomatis dari data historis, belum diisi manual. Perubahan baru tersimpan setelah klik Simpan.</div>
                    <x-button label="Simpan Target" type="submit" icon="o-check" class="btn-primary btn-sm w-full mt-1" spinner="saveTargets" />
                </form>
            @else
                <div class="space-y-2">
                    @foreach ($metrics as $metric)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-base-content/60">{{ $metric['label'] }}</span>
                            <span class="font-medium text-base-content">
                                @if ($metric['type'] === 'currency')
                                    <x-currency-abbr :value="$this->yearlyTargetFor($targetSettingEmployeeId, $metric['key'])" />
                                @else
                                    {{ number_format($this->yearlyTargetFor($targetSettingEmployeeId, $metric['key'])) }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                    <div class="text-[11px] text-base-content/40 pt-1">Cuma Admin FO / Super Admin yang bisa mengubah target.</div>
                </div>
            @endif
        </div>

        <!-- Kanan: data saat ini -->
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
                                    $exactSuffix = fn ($v) => $isCurrency ? ' Nilai: '.Money::exact($v).'.' : '';
                                @endphp
                                <div class="flex items-center justify-between gap-1 text-xs">
                                    <span class="text-base-content/50 shrink-0">{{ $metric['label'] }}</span>
                                    <span class="text-right">
                                        <span class="tooltip tooltip-left" data-tip="Realisasi — yang udah beneran terjadi di periode ini. {{ $gap >= 0 ? 'Hijau = udah capai/lewat target.' : 'Merah = masih di bawah target.' }}{{ $exactSuffix($realisasi) }}">
                                            <span class="font-medium {{ $gap >= 0 ? 'text-success' : 'text-error' }}">{{ $fmt($realisasi) }}</span>
                                        </span>
                                        @if ($hasProyeksi)
                                            <span class="tooltip tooltip-left" data-tip="Proyeksi — perkiraan tambahan dari funnel yang masih jalan (Pitching/Follow Up), belum pasti closing.{{ $exactSuffix($proyeksi) }}">
                                                <span class="text-warning">({{ $fmt($proyeksi) }})</span>
                                            </span>
                                        @endif
                                        <span class="tooltip tooltip-left" data-tip="Target — angka target {{ $activePeriod }} (manual kalau udah diisi admin, atau saran otomatis dari data historis kalau belum).{{ $exactSuffix($target) }}">
                                            <span class="text-base-content/30">/ {{ $fmt($target) }}</span>
                                        </span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-xs text-base-content/40 px-1">Format: Realisasi <span class="text-warning">(Proyeksi)</span> / Target — hijau/merah nunjukin udah lewat/belum lewat target</div>

            <!-- Ringkasan Semua FO -->
            <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
                <div class="text-xs font-semibold text-base-content/50 mb-2">Total Semua FO</div>
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
                </div>
            </div>
        </div>
    </div>

    <!-- Rekap Bulanan (FO yang lagi dipilih di dropdown kiri) -->
    <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
        <div class="text-sm font-semibold text-base-content mb-1">Rekap 6 Bulan Terakhir — {{ collect($this->foEmployees)->firstWhere('id', $targetSettingEmployeeId)['name'] ?? '-' }}</div>
        <p class="text-xs text-base-content/50 mb-3">Tren realisasi bulanan, bukan snapshot periode aktif di atas.</p>
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>Bulan</th>
                        @foreach ($metrics as $metric)
                            <th class="text-right">{{ $metric['label'] }}</th>
                        @endforeach
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($metrics) + 1 }}" class="text-center text-base-content/50 py-4">Pilih FO dulu di panel kiri.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ranking Semua FO (tahun berjalan) -->
    <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
        <div class="text-sm font-semibold text-base-content mb-1">Ranking FO Tahun Ini</div>
        <p class="text-xs text-base-content/50 mb-3">Dibandingkan berdasarkan Nilai Closing tahun berjalan vs target tahunan.</p>
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>#</th>
                        <th>Nama FO</th>
                        <th class="text-right">Closing</th>
                        <th class="text-right">Nilai Closing</th>
                        <th class="text-right">Target</th>
                        <th class="text-right">Achievement</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->foRanking as $i => $fo)
                        <tr class="border-b border-base-300">
                            <td>{{ $i + 1 }}</td>
                            <td class="font-medium text-base-content">{{ $fo['name'] }}</td>
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
                            <td colspan="6" class="text-center text-base-content/50 py-4">Belum ada data FO.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Riwayat Perubahan Target (audit trail) -->
    <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
        <div class="text-sm font-semibold text-base-content mb-1">Riwayat Perubahan Target</div>
        <p class="text-xs text-base-content/50 mb-3">30 perubahan terakhir tahun ini — siapa ubah, target siapa, metrik apa, dari berapa ke berapa.</p>
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>Waktu</th>
                        <th>Diubah Oleh</th>
                        <th>Target Milik</th>
                        <th>Metrik</th>
                        <th class="text-right">Dari</th>
                        <th class="text-right">Ke</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->targetChangeLogs as $log)
                        @php
                            $logMetric = collect($metrics)->firstWhere('key', $log->metric_key);
                            $logIsCurrency = $logMetric && $logMetric['type'] === 'currency';
                        @endphp
                        <tr class="border-b border-base-300">
                            <td class="whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $log->changedBy?->name ?? '-' }}</td>
                            <td>{{ $log->employee?->user?->name ?? '-' }}</td>
                            <td>{{ $logMetric['label'] ?? $log->metric_key }}</td>
                            <td class="text-right text-base-content/50">
                                @if (is_null($log->old_value))
                                    Saran otomatis
                                @elseif ($logIsCurrency)
                                    <x-currency-abbr :value="$log->old_value" />
                                @else
                                    {{ number_format($log->old_value) }}
                                @endif
                            </td>
                            <td class="text-right font-medium text-base-content">
                                @if (is_null($log->new_value))
                                    Saran otomatis (di-reset)
                                @elseif ($logIsCurrency)
                                    <x-currency-abbr :value="$log->new_value" />
                                @else
                                    {{ number_format($log->new_value) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-base-content/50 py-4">Belum ada perubahan target tahun ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
