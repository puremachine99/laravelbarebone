<?php

use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoTarget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;

layout('layouts.app');

state([
    'viewAsEmployeeId' => null,
    'selectedDate' => now()->toDateString(),
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

// Guard against incomplete/invalid dates (e.g. "0000-08-26" while the native
// date picker is mid-typing) — falls back to today instead of letting an
// unparsable date reach Carbon::parse()/whereDate() further down and 500.
$updatedSelectedDate = function ($value) {
    $year = (int) substr((string) $value, 0, 4);
    $month = (int) substr((string) $value, 5, 2);
    $day = (int) substr((string) $value, 8, 2);

    if (
        ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)
        || ! checkdate($month, $day, $year)
        || $value > now()->toDateString()
    ) {
        $this->selectedDate = now()->toDateString();
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

$yearlyTargetFor = function ($employeeId, $metricKey) {
    return (float) (FoTarget::where('employee_id', $employeeId)
        ->where('year', (int) substr($this->selectedDate, 0, 4))
        ->where('metric_key', $metricKey)
        ->value('yearly_target') ?? 0);
};

$dailyTargetFor = fn ($employeeId, $metricKey) => $this->yearlyTargetFor($employeeId, $metricKey) / 12 / 25;

$realisasiForFo = function ($employeeId, $metricKey, $date) {
    return match ($metricKey) {
        'leads' => FoFunnel::where('employee_id', $employeeId)->whereDate('tgl_leads', $date)->count(),
        'ql' => FoFunnel::where('employee_id', $employeeId)->whereNotNull('tgl_qualified')->whereDate('tgl_qualified', $date)->count(),
        'pitching' => FoFunnelActivity::where('stage', 'pitching')
            ->whereDate('tanggal', $date)
            ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId)->stageAtLeast('pitching'))
            ->distinct('fo_funnel_id')
            ->count('fo_funnel_id'),
        'followup' => FoFunnelActivity::where('stage', 'followup')
            ->whereDate('tanggal', $date)
            ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId)->stageAtLeast('followup'))
            ->distinct('fo_funnel_id')
            ->count('fo_funnel_id'),
        'won' => FoFunnel::where('employee_id', $employeeId)->stageAtLeast('won')->whereDate('tgl_won', $date)->count(),
        'closing' => FoFunnel::where('employee_id', $employeeId)->whereIn('stage', FoFunnel::CLOSED_STAGES)->whereDate('tgl_closing', $date)->count(),
        'nilai_closing' => (float) FoFunnel::where('employee_id', $employeeId)->whereIn('stage', FoFunnel::CLOSED_STAGES)->whereDate('tgl_closing', $date)->sum('nominal_investasi'),
        default => 0,
    };
};

$statusFor = function (?float $percent) {
    return match (true) {
        $percent === null => ['label' => 'RED', 'class' => 'badge-error'],
        $percent >= 100 => ['label' => 'GREEN', 'class' => 'badge-success'],
        $percent >= 50 => ['label' => 'YELLOW', 'class' => 'badge-warning'],
        default => ['label' => 'RED', 'class' => 'badge-error'],
    };
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

$trend = computed(function () {
    $employeeIds = $this->scopedEmployeeIds;
    $selectedDate = Carbon::parse($this->selectedDate);

    $data = collect(range(13, 0))->map(function ($daysAgo) use ($employeeIds, $selectedDate) {
        $date = $selectedDate->copy()->subDays($daysAgo);

        return [
            'label' => $date->format('d/m'),
            'leads' => collect($employeeIds)->sum(fn ($id) => FoFunnel::where('employee_id', $id)->whereDate('tgl_leads', $date)->count()),
            'won' => collect($employeeIds)->sum(fn ($id) => FoFunnel::where('employee_id', $id)->stageAtLeast('won')->whereDate('tgl_won', $date)->count()),
            'closing' => collect($employeeIds)->sum(fn ($id) => FoFunnel::where('employee_id', $id)->whereIn('stage', FoFunnel::CLOSED_STAGES)->whereDate('tgl_closing', $date)->count()),
        ];
    })->values()->all();

    $this->dispatch('dashboard-harian-trend-ready', $data);

    return $data;
});

$leaderboard = computed(function () {
    return collect($this->foEmployees)->map(function ($fo) {
        return [
            'name' => $fo['name'],
            'lead' => FoFunnel::where('employee_id', $fo['id'])->count(),
            'won' => FoFunnel::where('employee_id', $fo['id'])->where('stage', 'won')->count(),
            'closing' => FoFunnel::where('employee_id', $fo['id'])->whereIn('stage', FoFunnel::CLOSED_STAGES)->count(),
            'nilai_closing' => (float) FoFunnel::where('employee_id', $fo['id'])->whereIn('stage', FoFunnel::CLOSED_STAGES)->sum('nominal_investasi'),
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
                    Summary of team achievement for the selected date, 14-day trend & leaderboard — read-only, automatically sourced from the Pipeline.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                @if ($this->isAdminFo)
                    <div class="w-56">
                        <x-select label="View as" wire:model.live="viewAsEmployeeId" :options="$this->viewAsOptions" class="select-sm" />
                    </div>
                @else
                    <div class="text-sm text-base-content/50 pb-2.5">
                        Viewing as <span class="font-semibold text-base-content">{{ $this->currentEmployee?->user?->name }}</span>
                    </div>
                @endif

                <div class="w-44">
                    <x-input type="date" label="Work Date" wire:model.live="selectedDate" max="{{ now()->toDateString() }}"
                        class="input-sm" onkeydown="event.preventDefault()" onpaste="event.preventDefault()"
                        onclick="this.showPicker && this.showPicker()" onfocus="this.showPicker && this.showPicker()" />
                </div>

                @if ($selectedDate !== now()->toDateString())
                    <x-button label="Today" icon="o-calendar-days" class="btn-ghost btn-sm" wire:click="$set('selectedDate', '{{ now()->toDateString() }}')" />
                @endif
            </div>
        </div>
    </div>

    <div class="alert bg-info/10 border border-info/30 text-info text-sm">
        <x-icon name="o-information-circle" class="w-5 h-5" />
        <span>All figures are calculated automatically from the Funnel (Pipeline) module — this page is read-only, with no manual input.</span>
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
            <div class="text-xs text-base-content/40 mt-2">{{ Carbon::parse($selectedDate)->translatedFormat('d M Y') }}</div>
        </div>
    </div>

    <!-- Realization per FO -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <h3 class="text-base font-bold text-base-content">Realization per FO — {{ Carbon::parse($selectedDate)->translatedFormat('l, d F Y') }}</h3>
        <p class="text-xs text-base-content/50 mt-1 mb-4">
            Calculated automatically from stage transitions in the Pipeline for the selected date. Read-only — no manual input.
        </p>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
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

    <!-- 14-Day Trend -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <h3 class="text-base font-bold text-base-content">Last 14 Days Trend</h3>
        <p class="text-xs text-base-content/50 mt-1 mb-4">
            Daily total for {{ $viewAsEmployeeId ? 'the selected FO' : 'the entire team' }} per funnel stage.
        </p>
        <div wire:ignore id="chart-trend-harian" style="min-height:300px;"></div>
    </div>

    <!-- FO Leaderboard -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <h3 class="text-base font-bold text-base-content">FO Leaderboard</h3>
        <p class="text-xs text-base-content/50 mt-1 mb-4">Sorted by highest cumulative deal value (all-time).</p>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
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

@script
<script>
(function () {
    const initialTrend = @json($this->trend);

    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark'
        || document.documentElement.classList.contains('dark');

    const textColor = () => isDark() ? '#d1d5db' : '#374151';

    const baseOpts = () => ({
        chart: { fontFamily: 'inherit', toolbar: { show: false }, background: 'transparent' },
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
            chart: { ...baseOpts().chart, type: 'area', height: 300 },
            series: [
                { name: 'New Leads', data: d.map((row) => row.leads) },
                { name: 'Won', data: d.map((row) => row.won) },
                { name: 'Closing', data: d.map((row) => row.closing) },
            ],
            xaxis: {
                categories: d.map((row) => row.label),
                labels: { style: { fontSize: '12px', colors: tc } },
            },
            yaxis: { labels: { formatter: (v) => Math.round(v), style: { colors: tc } } },
            colors: ['#f97316', '#6366f1', '#10b981'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            legend: { show: true, position: 'top', horizontalAlign: 'right', fontSize: '12px', labels: { colors: tc } },
            dataLabels: { enabled: false },
        };

        const el = document.querySelector('#chart-trend-harian');
        if (!el) {
            return;
        }

        if (chart) {
            chart.updateOptions(opts, true, true);
        } else {
            chart = new ApexCharts(el, opts);
            chart.render();
        }
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

    $wire.on('dashboard-harian-trend-ready', (payload) => {
        const d = Array.isArray(payload) ? payload[0]?.trend ?? payload[0] : payload.trend ?? payload;
        lastData = d;
        ensureApexChartsThen(() => buildChart(lastData));
    });

})();
</script>
@endscript
