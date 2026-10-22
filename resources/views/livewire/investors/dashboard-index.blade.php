<?php

use App\Models\Employee;
use App\Models\FoFunnel;
use App\Services\FoTargetMetrics;
use App\Support\ResignedVisibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;

layout('layouts.app');

state([
    'viewAsEmployeeId' => null,
    'selectedMonth' => (int) now()->month,
    'selectedYear' => (int) now()->year,
    'selectedDay' => (int) now()->day,

    'bulanOptions' => [
        ['id' => 1, 'name' => 'January'], ['id' => 2, 'name' => 'February'], ['id' => 3, 'name' => 'March'],
        ['id' => 4, 'name' => 'April'], ['id' => 5, 'name' => 'May'], ['id' => 6, 'name' => 'June'],
        ['id' => 7, 'name' => 'July'], ['id' => 8, 'name' => 'August'], ['id' => 9, 'name' => 'September'],
        ['id' => 10, 'name' => 'October'], ['id' => 11, 'name' => 'November'], ['id' => 12, 'name' => 'December'],
    ],
]);

$metricsData = [
    ['key' => 'leads', 'label' => 'New Leads'],
    ['key' => 'ql', 'label' => 'Qualified Lead'],
    ['key' => 'pitching', 'label' => 'Pitching'],
    ['key' => 'won', 'label' => 'Won (Committed)'],
    ['key' => 'closing', 'label' => 'Closing (Deal)'],
];

state(['metrics' => $metricsData]);

$isAdminFo = computed(fn () => auth()->user()->hasRole(['Investor Admin', 'Super Admin']));

$currentEmployee = computed(fn () => Employee::with('user')->where('user_id', auth()->id())->first());

mount(function () {
    if (! $this->isAdminFo) {
        $this->viewAsEmployeeId = $this->currentEmployee?->id;
    }
});

// Ganti bulan/tahun -> clamp selectedDay biar gak nyangkut di tanggal yang gak ada
// di bulan baru (misal hari 31 pas pindah ke Februari).
$updatedSelectedMonth = function () {
    $this->selectedDay = min($this->selectedDay, $this->daysInSelectedMonth);
};

$updatedSelectedYear = function () {
    $this->selectedDay = min($this->selectedDay, $this->daysInSelectedMonth);
};

$daysInSelectedMonth = computed(fn () => Carbon::create($this->selectedYear, $this->selectedMonth, 1)->daysInMonth);

// Activity Tracker adalah kontrol utama: pilih Bulan/Tahun buat ganti periode, klik satu
// kolom tanggal di heatmap buat drill-down hari mana yang dipakai Stat Cards & Realization per FO.
$selectedDate = computed(function () {
    $day = min($this->selectedDay, $this->daysInSelectedMonth);

    return sprintf('%04d-%02d-%02d', $this->selectedYear, $this->selectedMonth, $day);
});

$selectDay = function (int $day) {
    $this->selectedDay = min(max($day, 1), $this->daysInSelectedMonth);
};

$resetFilters = function () {
    $this->selectedMonth = (int) now()->month;
    $this->selectedYear = (int) now()->year;
    $this->selectedDay = (int) now()->day;

    if ($this->isAdminFo) {
        $this->viewAsEmployeeId = null;
    }
};

// Saklar "tampilkan FO resign" dikontrol terpusat dari halaman FO Management.
$showResigned = computed(fn () => ResignedVisibility::enabled('investors'));

$foEmployees = computed(function () {
    $includeResigned = $this->showResigned;

    return Cache::remember('fo-dashboard-harian:fo-options'.($includeResigned ? ':with-resigned' : ''), 60, function () use ($includeResigned) {
        return Employee::with('user')
            ->fundingOfficers($includeResigned)
            ->get()
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->optionName()])
            ->sortBy('name')
            ->values()
            ->all();
    });
});

// Tahun yang tersedia di dropdown — diambil dari rentang data leads asli, biar gak ada tahun kosong.
$tahunOptions = computed(function () {
    return Cache::remember('fo-dashboard-harian:tahun-options', 60, function () {
        $driver = FoFunnel::query()->getConnection()->getDriverName();
        $yearExpression = $driver === 'sqlite'
            ? "CAST(strftime('%Y', tgl_leads) AS INTEGER)"
            : 'CAST(EXTRACT(YEAR FROM tgl_leads) AS INTEGER)';

        return FoFunnel::query()
            ->whereNotNull('tgl_leads')
            ->selectRaw("{$yearExpression} as year")
            ->distinct()
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->push((int) now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn ($year) => ['id' => $year, 'name' => (string) $year])
            ->all();
    });
});

$viewAsOptions = computed(function () {
    return collect([['id' => '', 'name' => 'Admin (All FOs)']])
        ->merge($this->foEmployees)
        ->values()
        ->all();
});

$scopedEmployees = computed(function () {
    if (! $this->viewAsEmployeeId) {
        return $this->foEmployees;
    }

    return collect($this->foEmployees)->where('id', (int) $this->viewAsEmployeeId)->values()->all();
});

$scopedEmployeeIds = computed(fn () => collect($this->scopedEmployees)->pluck('id')->all());

$foMetrics = computed(fn () => app(FoTargetMetrics::class));

// Semua target tahunan FO yang lagi di-scope, sekali query (bukan 1 query per FO per metrik).
$yearlyTargets = computed(fn () => $this->foMetrics->yearlyTargetsByEmployee($this->scopedEmployeeIds, (int) substr($this->selectedDate, 0, 4)));

// Realisasi semua metrik untuk hari yang dipilih, per FO — satu query grouped per metrik.
$dayRealisasi = computed(fn () => $this->foMetrics->realisasiByEmployeeForDate($this->scopedEmployeeIds, $this->selectedDate));

$yearlyTargetFor = function ($employeeId, $metricKey) {
    return $this->yearlyTargets[$employeeId][$metricKey] ?? 0.0;
};

$dailyTargetFor = fn ($employeeId, $metricKey) => $this->yearlyTargetFor($employeeId, $metricKey) / 12 / 25;

$realisasiForFo = function ($employeeId, $metricKey, $date) {
    if ($date === $this->selectedDate && isset($this->dayRealisasi[$metricKey][$employeeId])) {
        return $this->dayRealisasi[$metricKey][$employeeId];
    }

    return $this->foMetrics->realisasiFor($employeeId, $metricKey, $date);
};

$statusFor = function (?float $percent) {
    return $this->foMetrics->statusFor($percent);
};

$todaySummary = computed(function () {
    $selectedDate = $this->selectedDate;
    $employeeIds = $this->scopedEmployeeIds;

    $summary = [];

    foreach ($this->metrics as $metric) {
        $realisasi = collect($employeeIds)->sum(fn ($id) => $this->realisasiForFo($id, $metric['key'], $selectedDate));
        $target = collect($employeeIds)->sum(fn ($id) => $this->dailyTargetFor($id, $metric['key']));
        $percent = $target > 0 ? round($realisasi / $target * 100) : ($realisasi > 0 ? 100.0 : 0.0);

        $summary[$metric['key']] = [
            'label' => $metric['label'],
            'realisasi' => $realisasi,
            'target' => $target,
            'percent' => $percent,
            'status' => $this->statusFor($percent),
        ];
    }

    $summary['nilai_closing'] = [
        'label' => 'Deal Value',
        'realisasi' => collect($employeeIds)->sum(fn ($id) => $this->realisasiForFo($id, 'nilai_closing', $selectedDate)),
    ];

    return $summary;
});

// Tabel "Realisasi per FO" untuk hari yang dipilih — status per baris didasarkan pada capaian Lead Baru (metrik funnel paling awal).
$realisasiPerFo = computed(function () {
    $selectedDate = $this->selectedDate;

    return collect($this->scopedEmployees)->map(function ($fo) use ($selectedDate) {
        $leads = $this->realisasiForFo($fo['id'], 'leads', $selectedDate);
        $target = $this->dailyTargetFor($fo['id'], 'leads');
        $percent = $target > 0 ? round($leads / $target * 100) : ($leads > 0 ? 100.0 : 0.0);

        return [
            'name' => $fo['name'],
            'leads' => $leads,
            'ql' => $this->realisasiForFo($fo['id'], 'ql', $selectedDate),
            'pitching' => $this->realisasiForFo($fo['id'], 'pitching', $selectedDate),
            'followup' => $this->realisasiForFo($fo['id'], 'followup', $selectedDate),
            'won' => $this->realisasiForFo($fo['id'], 'won', $selectedDate),
            'closing' => $this->realisasiForFo($fo['id'], 'closing', $selectedDate),
            'nilai_closing' => $this->realisasiForFo($fo['id'], 'nilai_closing', $selectedDate),
            'status' => $this->statusFor($percent),
        ];
    })->values()->all();
});

// Trend bulanan, sejajar sama Activity Tracker — pakai bulan yang sama (dari
// $selectedDate) dan data yang sama persis (reuse activityHeatmap, gak query ulang).
$monthlyTrend = computed(function () {
    $heatmap = $this->activityHeatmap;
    $rows = collect($heatmap['rows'])->keyBy('key');

    $data = collect($heatmap['days'])->map(fn ($day) => [
        'label' => (string) $day,
        'leads' => $rows['leads']['counts'][$day] ?? 0,
        'won' => $rows['won']['counts'][$day] ?? 0,
        'closing' => $rows['closing']['counts'][$day] ?? 0,
    ])->values()->all();

    $this->dispatch('dashboard-monthly-trend-ready', $data);

    return $data;
});

// Activity Tracker — GitHub-style heatmap: hari (horizontal) x stage (vertical) untuk bulan
// dari $selectedDate. Query di-agregasi per hari langsung di DB (bukan loop per-hari per-FO)
// biar tetap ringan berapa pun jumlah FO/hari dalam sebulan. Di-cache per bulan + scope FO, jadi klik
// tanggal lain di bulan yang sama gak hitung ulang (cache-nya ke-reset tiap ada perubahan di Pipeline).
$activityHeatmap = computed(function () {
    $employeeIds = $this->scopedEmployeeIds;
    $date = Carbon::parse($this->selectedDate);
    $daysInMonth = $date->daysInMonth;
    $year = $date->year;
    $month = $date->month;

    return $this->foMetrics->rememberForMonth('heatmap', $employeeIds, $year, $month, function () use ($employeeIds, $date, $daysInMonth, $year, $month) {

    $stageMetrics = [
        ['key' => 'leads', 'label' => 'Leads'],
        ['key' => 'ql', 'label' => 'QL'],
        ['key' => 'pitching', 'label' => 'Pitching'],
        ['key' => 'followup', 'label' => 'Follow Up'],
        ['key' => 'won', 'label' => 'Won'],
        ['key' => 'closing', 'label' => 'Closing'],
    ];

    $rows = collect($stageMetrics)->map(function ($metric) use ($employeeIds, $year, $month) {
        $counts = $this->foMetrics->dailyCountsForMonth($employeeIds, $metric['key'], $year, $month);

        return [
            'key' => $metric['key'],
            'label' => $metric['label'],
            'counts' => $counts,
            'total' => array_sum($counts),
            'max' => $counts === [] ? 0 : max($counts),
        ];
    })->values()->all();

    return [
        'days' => range(1, $daysInMonth),
        'month_label' => $date->translatedFormat('F Y'),
        'rows' => $rows,
    ];
    });
});

// Leaderboard ikut Activity Tracker: scoped ke bulan yang lagi dipilih, bukan all-time lagi.
$leaderboard = computed(function () {
    $year = $this->selectedYear;
    $month = $this->selectedMonth;
    $employees = $this->foEmployees;
    $employeeIds = collect($employees)->pluck('id')->all();

    return $this->foMetrics->rememberForMonth('leaderboard', $employeeIds, $year, $month, function () use ($employees, $employeeIds, $year, $month) {
        $totals = $this->foMetrics->monthlyTotalsByEmployee($employeeIds, $year, $month);

        return collect($employees)
            ->map(fn ($fo) => ['name' => $fo['name'], ...$totals[$fo['id']]])
            ->sortByDesc('nilai_closing')
            ->values()
            ->all();
    });
});

?>

@php
    // Cards stay in the theme's neutral colours; only bars carry the status colour (literal classes so Tailwind keeps them).
    $statusBar = fn (string $badgeClass, string $kind = 'progress') => match ($badgeClass) {
        'badge-success' => $kind === 'bg' ? 'bg-success' : 'progress-success',
        'badge-warning' => $kind === 'bg' ? 'bg-warning' : 'progress-warning',
        default => $kind === 'bg' ? 'bg-error' : 'progress-error',
    };
@endphp

<div class="space-y-5">
    <!-- Header: title + filters -->
    <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-2xl font-bold tracking-tight text-base-content">Daily Dashboard</h2>
                <span class="badge badge-ghost gap-1 font-medium">
                    <x-icon name="o-calendar-days" class="w-3.5 h-3.5" />
                    {{ Carbon::parse($this->selectedDate)->translatedFormat('d M Y') }}
                </span>
            </div>
            <p class="text-sm text-base-content/50 mt-1 max-w-2xl">
                Pick a Month/Year below, then click a day column in the Activity Tracker to drill into that day's Stat Cards & Realization per FO — read-only, automatically sourced from the Pipeline.
            </p>
        </div>

        <div class="flex flex-wrap items-end gap-2 rounded-2xl border border-base-300 bg-base-100 p-3 shadow-sm">
            @if ($this->isAdminFo)
                <div class="w-full sm:w-56">
                    <x-select label="View as" aria-label="View as" wire:model.live="viewAsEmployeeId" :options="$this->viewAsOptions" class="select-sm" />
                </div>
            @else
                <div class="text-sm text-base-content/50 px-1 pb-2">
                    Viewing as <span class="font-semibold text-base-content"><x-short-name :name="$this->currentEmployee?->user?->name" /></span>
                </div>
            @endif

            <div class="w-36">
                <x-select label="Month" wire:model.live="selectedMonth" :options="$bulanOptions" class="select-sm" />
            </div>

            <div class="w-24">
                <x-select label="Year" wire:model.live="selectedYear" :options="$this->tahunOptions" class="select-sm" />
            </div>

            @if ($selectedMonth !== (int) now()->month || $selectedYear !== (int) now()->year || $selectedDay !== (int) now()->day || ($this->isAdminFo && $viewAsEmployeeId))
                <x-button label="Reset Filter" icon="o-arrow-path" class="btn-ghost btn-sm" wire:click="resetFilters" />
            @endif
        </div>
    </div>

    <!-- Activity Tracker (Stage x Day heatmap), full width: it is the day picker for everything below.
         The highlight follows $wire.selectedDay on the client, so it moves on click instead of after the round trip. -->
    <div class="card bg-base-100 border border-base-300 p-5 shadow-sm rounded-2xl"
        x-data="{ pick(day) { $wire.selectedDay = day; $wire.selectDay(day); } }">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-4">
            <div>
                <h3 class="text-base font-bold text-base-content">Activity Tracker — {{ $this->activityHeatmap['month_label'] }}</h3>
                <p class="text-xs text-base-content/50 mt-1">
                    Daily activity count per funnel stage for {{ $viewAsEmployeeId ? 'the selected FO' : 'the entire team' }}. Click a day to drill down below.
                </p>
            </div>
            <div class="flex items-center gap-1.5 text-[10px] text-base-content/50 shrink-0">
                <span>Less</span>
                <span class="w-3 h-3 rounded-sm bg-base-200"></span>
                <span class="w-3 h-3 rounded-sm bg-success/25"></span>
                <span class="w-3 h-3 rounded-sm bg-success/60"></span>
                <span class="w-3 h-3 rounded-sm bg-success"></span>
                <span>More</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-separate" style="border-spacing: 4px;">
                <thead>
                    <tr>
                        <th class="sticky left-0 bg-base-100 text-left text-sm font-semibold text-base-content/50 pr-3 z-10 w-20">Stage</th>
                        @foreach ($this->activityHeatmap['days'] as $day)
                            <th class="p-0 text-center">
                                <button type="button" x-on:click="pick({{ $day }})"
                                    class="w-full min-w-8 h-8 rounded-lg text-xs cursor-pointer"
                                    x-bind:class="$wire.selectedDay === {{ $day }} ? 'bg-primary text-primary-content font-bold' : 'text-base-content/40 hover:bg-base-200'"
                                    title="Show details for day {{ $day }}">{{ $day }}</button>
                            </th>
                        @endforeach
                        <th class="text-sm font-semibold text-base-content/50 pl-3 text-right w-14">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->activityHeatmap['rows'] as $row)
                        <tr>
                            <td class="sticky left-0 bg-base-100 text-sm font-semibold text-base-content pr-3 whitespace-nowrap z-10">{{ $row['label'] }}</td>
                            @foreach ($row['counts'] as $day => $count)
                                @php
                                    $ratio = $row['max'] > 0 ? $count / $row['max'] : 0;
                                    $tier = match (true) {
                                        $count === 0 => 'bg-base-200',
                                        $ratio >= 0.75 => 'bg-success',
                                        $ratio >= 0.4 => 'bg-success/60',
                                        default => 'bg-success/25',
                                    };
                                    $textTier = match (true) {
                                        $count === 0 => '',
                                        $ratio >= 0.4 => 'text-success-content',
                                        default => 'text-success',
                                    };
                                @endphp
                                <td class="p-0 text-center">
                                    <button type="button" x-on:click="pick({{ $day }})"
                                        class="w-full min-w-8 aspect-square rounded-lg flex items-center justify-center text-sm font-bold leading-none {{ $tier }} {{ $textTier }}"
                                        x-bind:class="{ 'ring-2 ring-primary ring-offset-1 ring-offset-base-100': $wire.selectedDay === {{ $day }} }"
                                        title="{{ $row['label'] }} — day {{ $day }}: {{ $count }}">{{ $count > 0 ? $count : '' }}</button>
                                </td>
                            @endforeach
                            <td class="text-base font-bold text-base-content text-right pl-3">{{ number_format($row['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stat cards for the selected day -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 transition-opacity" wire:loading.class="opacity-60" wire:target="selectDay">
        @foreach ($metrics as $metric)
            @php $s = $this->todaySummary[$metric['key']]; @endphp
            <div class="card bg-base-100 border border-base-300 p-4 rounded-2xl shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="text-[11px] font-semibold text-base-content/50 uppercase tracking-wide leading-tight">{{ $s['label'] }}</div>
                    <span class="badge badge-ghost badge-xs shrink-0">{{ $s['status']['label'] }}</span>
                </div>
                <div class="text-3xl font-bold text-base-content mt-2">{{ number_format($s['realisasi']) }}</div>
                <progress class="progress {{ $statusBar($s['status']['class']) }} h-1.5 w-full mt-3" value="{{ min($s['percent'], 100) }}" max="100"></progress>
                <div class="flex items-center justify-between mt-1.5 text-[11px] text-base-content/50">
                    <span>Target: {{ number_format($s['target'], 1) }}</span>
                    <span class="font-semibold text-base-content/70">{{ $s['percent'] }}%</span>
                </div>
            </div>
        @endforeach

        @php $nc = $this->todaySummary['nilai_closing']; @endphp
        <div class="card bg-base-100 border border-base-300 p-4 rounded-2xl shadow-sm">
            <div class="flex items-center gap-1.5 text-[11px] font-semibold text-base-content/50 uppercase tracking-wide">
                <x-icon name="o-banknotes" class="w-3.5 h-3.5" /> Deal Value
            </div>
            <div class="text-2xl font-bold text-base-content mt-2"><x-currency-abbr :value="$nc['realisasi']" /></div>
            <div class="text-[11px] text-base-content/50 mt-auto pt-3">{{ Carbon::parse($this->selectedDate)->translatedFormat('d M Y') }}</div>
        </div>
    </div>

    <!-- Realization per FO (selected day), full width -->
    <div class="card bg-base-100 border border-base-300 p-5 shadow-sm rounded-2xl transition-opacity" wire:loading.class="opacity-60" wire:target="selectDay">
        <h3 class="text-base font-bold text-base-content">Realization per FO — {{ Carbon::parse($this->selectedDate)->translatedFormat('l, d F Y') }}</h3>
        <p class="text-xs text-base-content/50 mt-1 mb-4">
            Calculated automatically from stage transitions in the Pipeline for the selected date. Read-only — no manual input.
        </p>
        <div class="overflow-x-auto rounded-xl border border-base-300">
            <table class="table table-sm table-zebra w-full text-base-content/70 whitespace-nowrap">
                <thead class="bg-base-200">
                    <tr class="text-base-content/50">
                        <th>FO</th>
                        <th class="text-right">New Leads</th>
                        <th class="text-right">QL</th>
                        <th class="text-right">Pitching</th>
                        <th class="text-right">Follow Up</th>
                        <th class="text-right">Won</th>
                        <th class="text-right">Closing</th>
                        <th class="text-right">Deal Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->realisasiPerFo as $row)
                        <tr class="hover:bg-base-200">
                            <td class="font-medium text-base-content"><x-short-name :name="$row['name']" /></td>
                            <td class="text-right">{{ number_format($row['leads']) }}</td>
                            <td class="text-right">{{ number_format($row['ql']) }}</td>
                            <td class="text-right">{{ number_format($row['pitching']) }}</td>
                            <td class="text-right">{{ number_format($row['followup']) }}</td>
                            <td class="text-right">{{ number_format($row['won']) }}</td>
                            <td class="text-right">{{ number_format($row['closing']) }}</td>
                            <td class="text-right font-semibold text-base-content"><x-currency-abbr :value="$row['nilai_closing']" /></td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-8 rounded-full {{ $statusBar($row['status']['class'], 'bg') }}"></span>
                                    <span class="text-xs font-medium text-base-content/70">{{ $row['status']['label'] }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-base-content/50 py-6">No active FOs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @php
            $teamLeadsTarget = $this->todaySummary['leads']['target'];
            $foCount = count($this->foEmployees);
            $perFoTarget = $foCount > 0 ? round($teamLeadsTarget / $foCount, 1) : 0;
        @endphp
        <p class="flex items-start gap-1.5 text-xs text-base-content/40 mt-3">
            <x-icon name="o-information-circle" class="w-4 h-4 shrink-0" />
            <span>
                Individual target = team target ÷ number of active FOs ({{ number_format($teamLeadsTarget, 1) }} ÷ {{ $foCount }} = {{ $perFoTarget }} New Leads/day).
                Targets are set per FO per year on the FO Management page.
            </span>
        </p>
    </div>

    <!-- Monthly context: trend + leaderboard -->
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">
        <!-- Monthly Trend -->
        <div class="card bg-base-100 border border-base-300 p-5 shadow-sm rounded-2xl xl:col-span-3 min-w-0">
            <h3 class="text-base font-bold text-base-content">Monthly Trend — {{ $this->activityHeatmap['month_label'] }}</h3>
            <p class="text-xs text-base-content/50 mt-1 mb-2">
                Daily total for {{ $viewAsEmployeeId ? 'the selected FO' : 'the entire team' }} per funnel stage, this month.
            </p>
            <div wire:ignore id="chart-trend-harian" class="w-full min-h-65 md:min-h-75"></div>
        </div>

        <!-- FO Leaderboard -->
        <div class="card bg-base-100 border border-base-300 p-5 shadow-sm rounded-2xl xl:col-span-2 min-w-0">
            <h3 class="text-base font-bold text-base-content">FO Leaderboard — {{ $this->activityHeatmap['month_label'] }}</h3>
            <p class="text-xs text-base-content/50 mt-1 mb-4">Sorted by highest deal value for the selected month.</p>
            <div class="overflow-x-auto">
                <table class="table table-sm w-full text-base-content/70 whitespace-nowrap">
                    <thead>
                        <tr class="text-base-content/50 border-b border-base-300">
                            <th class="w-8">#</th>
                            <th>FO</th>
                            <th class="text-right">Lead</th>
                            <th class="text-right">Won</th>
                            <th class="text-right">Closing</th>
                            <th class="text-right">Cumulative Deal Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->leaderboard as $i => $fo)
                            <tr class="border-b border-base-300 last:border-0">
                                <td>
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[11px] font-bold {{ $i < 3 ? 'bg-base-200 text-base-content' : 'text-base-content/50' }}">{{ $i + 1 }}</span>
                                </td>
                                <td class="font-medium text-base-content"><x-short-name :name="$fo['name']" /></td>
                                <td class="text-right">{{ number_format($fo['lead']) }}</td>
                                <td class="text-right">{{ number_format($fo['won']) }}</td>
                                <td class="text-right">{{ number_format($fo['closing']) }}</td>
                                <td class="text-right font-semibold text-base-content"><x-currency-abbr :value="$fo['nilai_closing']" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-base-content/50 py-6">No FO data yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@script
<script>
(function () {
    const initialTrend = @json($this->monthlyTrend);

    const isDark = () => document.documentElement.classList.contains('dark')
        || document.documentElement.classList.contains('dark');

    const textColor = () => chartTheme().text;

    const baseOpts = () => ({
        chart: { fontFamily: 'inherit', toolbar: { show: false }, background: 'transparent', animations: { enabled: false } },
        theme: { mode: isDark() ? 'dark' : 'light' },
        grid: { borderColor: chartTheme().grid, strokeDashArray: 4 },
        tooltip: { theme: isDark() ? 'dark' : 'light' },
    });

    let chart = null;
    let lastData = null;

    function buildChart(d) {
        const tc = textColor();

        const opts = {
            ...baseOpts(),
            chart: { ...baseOpts().chart, type: 'bar', height: 300, width: '100%', stacked: false },
            plotOptions: { bar: { columnWidth: '70%', borderRadius: 2, dataLabels: { position: 'top' } } },
            series: [
                { name: 'New Leads', data: d.map((row) => row.leads) },
                { name: 'Won', data: d.map((row) => row.won) },
                { name: 'Closing', data: d.map((row) => row.closing) },
            ],
            xaxis: {
                categories: d.map((row) => row.label),
                labels: { style: { fontSize: '11px', colors: tc } },
            },
            yaxis: { labels: { formatter: (v) => Math.round(v), style: { colors: tc } } },
            colors: [chartTheme().color(9), chartTheme().color(1), chartTheme().color(2)],
            legend: { show: true, position: 'top', horizontalAlign: 'right', fontSize: '12px', labels: { colors: tc } },
            dataLabels: {
                enabled: true,
                formatter: (v) => (v > 0 ? v : ''),
                offsetY: -16,
                style: { fontSize: '9px', colors: [tc] },
                background: { enabled: false },
            },
            grid: { ...baseOpts().grid, padding: { top: 20 } },
            responsive: [{
                breakpoint: 768,
                options: {
                    chart: { height: 260 },
                    legend: { position: 'bottom', horizontalAlign: 'center' },
                    dataLabels: { enabled: false },
                    xaxis: { labels: { style: { fontSize: '9px' } } },
                },
            }],
        };

        const el = document.querySelector('#chart-trend-harian');
        if (!el) {
            return;
        }

        // chart.updateOptions() leaves data-label groups stuck with ApexCharts'
        // internal "apexcharts-element-hidden" class on bar charts (a known upstream
        // quirk) — destroying and recreating on every data change is what actually
        // keeps the labels visible after switching months.
        if (chart) {
            chart.destroy();
            chart = null;
        }

        chart = new ApexCharts(el, opts);
        chart.render();
    }

    function ensureApexChartsThen(callback) {
        if (window.ApexCharts) {
            callback();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js';
        script.onload = callback;
        document.head.appendChild(script);
    }

    ensureApexChartsThen(() => {
        lastData = initialTrend || [];
        buildChart(lastData);
    });

    $wire.on('dashboard-monthly-trend-ready', (payload) => {
        const d = Array.isArray(payload) ? payload[0]?.trend ?? payload[0] : payload.trend ?? payload;

        // Every render re-sends the trend; picking another day keeps the same month, so skip the costly rebuild.
        if (chart && JSON.stringify(d) === JSON.stringify(lastData)) {
            return;
        }

        lastData = d;
        ensureApexChartsThen(() => buildChart(lastData));
    });

})();
</script>
@endscript
