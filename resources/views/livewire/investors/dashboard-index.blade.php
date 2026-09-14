<?php

use App\Models\Employee;
use App\Models\FoFunnel;
use App\Services\FoTargetMetrics;
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

$foEmployees = computed(function () {
    return Cache::remember('fo-dashboard-harian:fo-options', 60, function () {
        return Employee::with('user')
            ->whereHas('department', fn ($q) => $q->where('name', 'Departemen Finance'))
            ->whereHas('user.roles', fn ($q) => $q->whereIn('name', ['Funding Officer', 'Investor Admin']))
            ->get()
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->user?->name])
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

$yearlyTargetFor = function ($employeeId, $metricKey) {
    return $this->foMetrics->yearlyTarget($employeeId, $metricKey, (int) substr($this->selectedDate, 0, 4));
};

$dailyTargetFor = fn ($employeeId, $metricKey) => $this->yearlyTargetFor($employeeId, $metricKey) / 12 / 25;

$realisasiForFo = function ($employeeId, $metricKey, $date) {
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
// biar tetap ringan berapa pun jumlah FO/hari dalam sebulan.
$activityHeatmap = computed(function () {
    $employeeIds = $this->scopedEmployeeIds;
    $date = Carbon::parse($this->selectedDate);
    $daysInMonth = $date->daysInMonth;
    $year = $date->year;
    $month = $date->month;

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

// Leaderboard ikut Activity Tracker: scoped ke bulan yang lagi dipilih, bukan all-time lagi.
$leaderboard = computed(function () {
    $year = $this->selectedYear;
    $month = $this->selectedMonth;

    return collect($this->foEmployees)->map(function ($fo) use ($year, $month) {
        return [
            'name' => $fo['name'],
            'lead' => FoFunnel::where('employee_id', $fo['id'])->whereYear('tgl_leads', $year)->whereMonth('tgl_leads', $month)->count(),
            'won' => FoFunnel::where('employee_id', $fo['id'])->stageAtLeast('won')->whereYear('tgl_won', $year)->whereMonth('tgl_won', $month)->count(),
            'closing' => FoFunnel::where('employee_id', $fo['id'])->whereIn('stage', FoFunnel::CLOSED_STAGES)->whereYear('tgl_closing', $year)->whereMonth('tgl_closing', $month)->count(),
            'nilai_closing' => (float) FoFunnel::where('employee_id', $fo['id'])->whereIn('stage', FoFunnel::CLOSED_STAGES)->whereYear('tgl_closing', $year)->whereMonth('tgl_closing', $month)->sum('nominal_investasi'),
        ];
    })->sortByDesc('nilai_closing')->values()->all();
});

?>

<div class="space-y-6">
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-base-content">Daily Dashboard</h2>
                <p class="text-sm text-base-content/50 mt-1">
                    Pick a Month/Year below, then click a day column in the Activity Tracker to drill into that day's Stat Cards & Realization per FO — read-only, automatically sourced from the Pipeline.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                @if ($this->isAdminFo)
                    <div class="w-56">
                        <x-select label="View as" aria-label="View as" wire:model.live="viewAsEmployeeId" :options="$this->viewAsOptions" class="select-sm" />
                    </div>
                @else
                    <div class="text-sm text-base-content/50 pb-2.5">
                        Viewing as <span class="font-semibold text-base-content">{{ $this->currentEmployee?->user?->name }}</span>
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
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <!-- Activity Tracker (Stage x Day heatmap) -->
        <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
            <h3 class="text-base font-bold text-base-content">Activity Tracker — {{ $this->activityHeatmap['month_label'] }}</h3>
            <p class="text-xs text-base-content/50 mt-1 mb-4">
                Daily activity count per funnel stage for {{ $viewAsEmployeeId ? 'the selected FO' : 'the entire team' }}. Click a day to drill down below.
            </p>
            <div class="overflow-x-auto">
                <table class="border-separate" style="border-spacing: 3px;">
                    <thead>
                        <tr>
                            <th class="sticky left-0 bg-base-100 text-left text-xs font-semibold text-base-content/50 pr-3 z-10">Stage</th>
                            @foreach ($this->activityHeatmap['days'] as $day)
                                <th class="p-0 text-center">
                                    <button type="button" wire:click="selectDay({{ $day }})"
                                        class="w-5 h-5 rounded text-[10px] cursor-pointer {{ $day === $selectedDay ? 'bg-primary text-primary-content font-bold' : 'text-base-content/40 hover:bg-base-200' }}"
                                        title="Show details for day {{ $day }}">{{ $day }}</button>
                                </th>
                            @endforeach
                            <th class="text-xs font-semibold text-base-content/50 pl-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->activityHeatmap['rows'] as $row)
                            <tr>
                                <td class="sticky left-0 bg-base-100 text-xs font-medium text-base-content pr-3 whitespace-nowrap z-10">{{ $row['label'] }}</td>
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
                                        <button type="button" wire:click="selectDay({{ $day }})"
                                            class="w-5 h-5 rounded flex items-center justify-center text-[9px] font-semibold leading-none {{ $tier }} {{ $textTier }} {{ $day === $selectedDay ? 'ring-2 ring-primary ring-offset-1 ring-offset-base-100' : '' }}"
                                            title="{{ $row['label'] }} — day {{ $day }}: {{ $count }}">{{ $count > 0 ? $count : '' }}</button>
                                    </td>
                                @endforeach
                                <td class="text-xs font-semibold text-base-content text-right pl-3">{{ number_format($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Monthly Trend -->
        <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
            <h3 class="text-base font-bold text-base-content">Monthly Trend — {{ $this->activityHeatmap['month_label'] }}</h3>
            <p class="text-xs text-base-content/50 mt-1 mb-4">
                Daily total for {{ $viewAsEmployeeId ? 'the selected FO' : 'the entire team' }} per funnel stage, this month.
            </p>
            <div wire:ignore id="chart-trend-harian" class="w-full min-h-65 md:min-h-75"></div>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-4">
        @foreach ($metrics as $metric)
            @php $s = $this->todaySummary[$metric['key']]; @endphp
            <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
                <div class="text-xs font-semibold text-base-content/40 uppercase tracking-wide">{{ $s['label'] }}</div>
                <div class="text-3xl font-bold text-base-content mt-1">{{ number_format($s['realisasi']) }}</div>
                <div class="flex items-center gap-2 mt-2 text-xs text-base-content/50">
                    <span>Target: {{ number_format($s['target'], 1) }} · {{ $s['percent'] }}%</span>
                    <span class="badge badge-sm {{ $s['status']['class'] }}">{{ $s['status']['label'] }}</span>
                </div>
            </div>
        @endforeach

        @php $nc = $this->todaySummary['nilai_closing']; @endphp
        <div class="card bg-base-100 border border-success/40 p-4 rounded-xl">
            <div class="text-xs font-semibold text-base-content/40 uppercase tracking-wide">Deal Value</div>
            <div class="text-2xl font-bold text-base-content mt-1"><x-currency-abbr :value="$nc['realisasi']" /></div>
            <div class="text-xs text-base-content/40 mt-2">{{ Carbon::parse($this->selectedDate)->translatedFormat('d M Y') }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Realization per FO -->
        <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
            <h3 class="text-base font-bold text-base-content">Realization per FO — {{ Carbon::parse($this->selectedDate)->translatedFormat('l, d F Y') }}</h3>
            <p class="text-xs text-base-content/50 mt-1 mb-4">
                Calculated automatically from stage transitions in the Pipeline for the selected date. Read-only — no manual input.
            </p>
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full text-base-content/70 whitespace-nowrap">
                    <thead>
                        <tr class="text-base-content/50 border-b border-base-300">
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
                            <tr class="border-b border-base-300 hover:bg-base-200">
                                <td class="font-medium text-base-content">{{ $row['name'] }}</td>
                                <td class="text-right">{{ number_format($row['leads']) }}</td>
                                <td class="text-right">{{ number_format($row['ql']) }}</td>
                                <td class="text-right">{{ number_format($row['pitching']) }}</td>
                                <td class="text-right">{{ number_format($row['followup']) }}</td>
                                <td class="text-right">{{ number_format($row['won']) }}</td>
                                <td class="text-right">{{ number_format($row['closing']) }}</td>
                                <td class="text-right"><x-currency-abbr :value="$row['nilai_closing']" /></td>
                                <td><span class="badge badge-sm {{ $row['status']['class'] }}">{{ $row['status']['label'] }}</span></td>
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
            <p class="text-xs text-base-content/40 mt-3">
                Individual target = team target ÷ number of active FOs ({{ number_format($teamLeadsTarget, 1) }} ÷ {{ $foCount }} = {{ $perFoTarget }} New Leads/day).
                Targets are set per FO per year on the FO Management page.
            </p>
        </div>

        <!-- FO Leaderboard -->
        <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
            <h3 class="text-base font-bold text-base-content">FO Leaderboard — {{ $this->activityHeatmap['month_label'] }}</h3>
            <p class="text-xs text-base-content/50 mt-1 mb-4">Sorted by highest deal value for the selected month.</p>
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full text-base-content/70 whitespace-nowrap">
                    <thead>
                        <tr class="text-base-content/50 border-b border-base-300">
                            <th>#</th>
                            <th>FO</th>
                            <th class="text-right">Lead</th>
                            <th class="text-right">Won</th>
                            <th class="text-right">Closing</th>
                            <th class="text-right">Cumulative Deal Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->leaderboard as $i => $fo)
                            <tr class="border-b border-base-300">
                                <td>{{ $i + 1 }}</td>
                                <td class="font-medium text-base-content">{{ $fo['name'] }}</td>
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

    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark'
        || document.documentElement.classList.contains('dark');

    const textColor = () => isDark() ? '#d1d5db' : '#374151';

    const baseOpts = () => ({
        chart: { fontFamily: 'inherit', toolbar: { show: false }, background: 'transparent', animations: { enabled: false } },
        theme: { mode: isDark() ? 'dark' : 'light' },
        grid: { borderColor: isDark() ? '#374151' : '#e5e7eb', strokeDashArray: 4 },
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
            colors: ['#f97316', '#6366f1', '#10b981'],
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
        lastData = d;
        ensureApexChartsThen(() => buildChart(lastData));
    });

})();
</script>
@endscript
