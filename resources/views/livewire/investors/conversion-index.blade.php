<?php

use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use Illuminate\Support\Facades\Cache;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;

layout('layouts.app');

state([
    'viewAsEmployeeId' => null,

    'bulanLabels' => [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ],

    'stageLabels' => [
        'lead_to_ql' => 'Lead → QL',
        'ql_to_pitch' => 'QL → Pitching',
        'pitch_to_won' => 'Pitching → Won',
        'won_to_closing' => 'Won → Closing',
    ],
]);


$isAdminFo = computed(fn () => auth()->user()->hasRole(['Investor Admin', 'Super Admin']));

$currentEmployee = computed(fn () => Employee::with('user')->where('user_id', auth()->id())->first());

mount(function () {
    if (! $this->isAdminFo) {
        $this->viewAsEmployeeId = $this->currentEmployee?->id;
    }
});


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


$realisasiInRange = function ($employeeId, $metricKey, $start, $end) {
    return match ($metricKey) {
        'leads' => FoFunnel::where('employee_id', $employeeId)->whereBetween('tgl_leads', [$start, $end])->count(),
        'ql' => FoFunnel::where('employee_id', $employeeId)->whereNotNull('tgl_qualified')->whereBetween('tgl_qualified', [$start, $end])->count(),
        'pitching' => FoFunnelActivity::where('stage', 'pitching')
            ->whereBetween('tanggal', [$start, $end])
            ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId))
            ->distinct('fo_funnel_id')
            ->count('fo_funnel_id'),
        'won' => FoFunnel::where('employee_id', $employeeId)->whereBetween('tgl_won', [$start, $end])->count(),
        'closing' => FoFunnel::where('employee_id', $employeeId)->whereIn('stage', FoFunnel::CLOSED_STAGES)->whereBetween('tgl_closing', [$start, $end])->count(),
        'nilai_closing' => (float) FoFunnel::where('employee_id', $employeeId)->whereIn('stage', FoFunnel::CLOSED_STAGES)->whereBetween('tgl_closing', [$start, $end])->sum('nominal_investasi'),
        default => 0,
    };
};


$teamRealisasiInRange = function ($metricKey, $start, $end) {
    $employeeIds = $this->scopedEmployeeIds;

    return collect($employeeIds)->sum(fn ($id) => $this->realisasiInRange($id, $metricKey, $start, $end));
};

$monthlyRows = computed(function () {
    $year = (int) now()->year;
    $currentMonth = (int) now()->month;

    return collect(range(1, $currentMonth))->map(function ($month) use ($year) {
        $start = now()->setDate($year, $month, 1)->startOfMonth();
        $end = now()->setDate($year, $month, 1)->endOfMonth();

        $lead = $this->teamRealisasiInRange('leads', $start, $end);
        $ql = $this->teamRealisasiInRange('ql', $start, $end);
        $pitching = $this->teamRealisasiInRange('pitching', $start, $end);
        $won = $this->teamRealisasiInRange('won', $start, $end);
        $closing = $this->teamRealisasiInRange('closing', $start, $end);
        $nilaiClosing = $this->teamRealisasiInRange('nilai_closing', $start, $end);

        return [
            'month' => $this->bulanLabels[$month - 1],
            'hasData' => $lead > 0,
            'lead' => $lead,
            'ql' => $ql,
            'pitching' => $pitching,
            'won' => $won,
            'closing' => $closing,
            'nilai_closing' => $nilaiClosing,
            'lead_to_ql' => $lead > 0 ? round($ql / $lead * 100, 1) : null,
            'ql_to_pitch' => $ql > 0 ? round($pitching / $ql * 100, 1) : null,
            'pitch_to_won' => $pitching > 0 ? round($won / $pitching * 100, 1) : null,
            'won_to_closing' => $won > 0 ? round($closing / $won * 100, 1) : null,
            'overall' => $lead > 0 ? round($closing / $lead * 100, 1) : null,
        ];
    })->values()->all();
});


$activeMonths = computed(fn () => collect($this->monthlyRows)->where('hasData', true)->values());

$rataRata = computed(function () {
    $active = $this->activeMonths;

    return $active->isEmpty() ? 0.0 : round($active->avg('overall'), 1);
});

$statusFor = function (?float $overall) {
    $rataRata = $this->rataRata;

    if ($overall === null || $rataRata <= 0) {
        return null;
    }

    return match (true) {
        $overall >= $rataRata => ['label' => '≥ average', 'class' => 'badge-success'],
        $overall / $rataRata >= 0.8 => ['label' => 'near average', 'class' => 'badge-warning'],
        default => ['label' => 'below average', 'class' => 'badge-error'],
    };
};

$totalRow = computed(function () {
    $rows = collect($this->monthlyRows);
    $lead = $rows->sum('lead');
    $ql = $rows->sum('ql');
    $pitching = $rows->sum('pitching');
    $won = $rows->sum('won');
    $closing = $rows->sum('closing');

    return [
        'lead' => $lead,
        'ql' => $ql,
        'pitching' => $pitching,
        'won' => $won,
        'closing' => $closing,
        'nilai_closing' => $rows->sum('nilai_closing'),
        'lead_to_ql' => $lead > 0 ? round($ql / $lead * 100, 1) : null,
        'ql_to_pitch' => $ql > 0 ? round($pitching / $ql * 100, 1) : null,
        'pitch_to_won' => $pitching > 0 ? round($won / $pitching * 100, 1) : null,
        'won_to_closing' => $won > 0 ? round($closing / $won * 100, 1) : null,
        'overall' => $lead > 0 ? round($closing / $lead * 100, 1) : null,
        'active_months' => $this->activeMonths->count(),
    ];
});

$ringkasan = computed(function () {
    $active = $this->activeMonths;
    $total = $this->totalRow;

    $stageAverages = collect($this->stageLabels)->map(function ($label, $key) use ($active) {
        $values = $active->pluck($key)->filter(fn ($v) => $v !== null);

        return [
            'key' => $key,
            'label' => $label,
            'average' => $values->isEmpty() ? null : round($values->avg(), 1),
        ];
    })->filter(fn ($stage) => $stage['average'] !== null)->values();

    $bottleneck = $stageAverages->sortBy('average')->first();

    return [
        'konversi_kumulatif' => $total['overall'] ?? 0.0,
        'total_lead' => $total['lead'],
        'total_closing' => $total['closing'],
        'bulan_terbaik' => $active->sortByDesc('overall')->first(),
        'bulan_terlemah' => $active->sortBy('overall')->first(),
        'bottleneck' => $bottleneck,
    ];
});

$funnelKumulatif = computed(function () {
    $total = $this->totalRow;

    $data = [
        ['label' => 'New Leads', 'value' => $total['lead']],
        ['label' => 'Qualified Lead', 'value' => $total['ql']],
        ['label' => 'Pitching', 'value' => $total['pitching']],
        ['label' => 'Won', 'value' => $total['won']],
        ['label' => 'Closing', 'value' => $total['closing']],
    ];

    $this->dispatch('conversion-funnel-ready', $data);

    return $data;
});

?>

<div class="space-y-6">
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-base-content">Monthly Conversion</h2>
                <p class="text-sm text-base-content/50 mt-1">Team conversion funnel + year-to-date summary.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if ($this->isAdminFo)
                    <div class="w-56">
                        <x-select label="View as" wire:model.live="viewAsEmployeeId" :options="$this->viewAsOptions" class="select-sm" />
                    </div>
                @else
                    <div class="text-sm text-base-content/50">
                        Viewing as <span class="font-semibold text-base-content">{{ $this->currentEmployee?->user?->name }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="alert bg-info/10 border border-info/30 text-info text-sm">
        <x-icon name="o-information-circle" class="w-5 h-5" />
        <span>All figures are calculated automatically from the Funnel (Pipeline) module — this page is read-only, with no manual input.</span>
    </div>

    <!-- Monthly conversion table -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <p class="text-xs text-base-content/50 mb-3">
            Read-only. Monthly rows use per-month percentages; the Total row uses a cumulative (SUM/SUM) calculation.
        </p>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>Month</th>
                        <th>Lead</th>
                        <th>QL</th>
                        <th>Pitching</th>
                        <th>Won</th>
                        <th>Closing</th>
                        <th>Deal Value</th>
                        <th>Lead→QL</th>
                        <th>QL→Pitch</th>
                        <th>Pitch→Won</th>
                        <th>Won→Closing</th>
                        <th>Overall</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->monthlyRows as $row)
                        <x-investors.conversion-row
                            :month="$row['month']"
                            :has-data="$row['hasData']"
                            :lead="$row['lead']"
                            :ql="$row['ql']"
                            :pitching="$row['pitching']"
                            :won="$row['won']"
                            :closing="$row['closing']"
                            :nilai-closing="$row['nilai_closing']"
                            :lead-to-ql="$row['lead_to_ql']"
                            :ql-to-pitch="$row['ql_to_pitch']"
                            :pitch-to-won="$row['pitch_to_won']"
                            :won-to-closing="$row['won_to_closing']"
                            :overall="$row['overall']"
                            :status="$row['hasData'] ? $this->statusFor($row['overall']) : null"
                        />
                    @endforeach

                    @php $total = $this->totalRow; @endphp
                    <x-investors.conversion-row
                        :month="'Total ('.$total['active_months'].' active months)'"
                        :is-total="true"
                        has-data
                        :lead="$total['lead']"
                        :ql="$total['ql']"
                        :pitching="$total['pitching']"
                        :won="$total['won']"
                        :closing="$total['closing']"
                        :nilai-closing="$total['nilai_closing']"
                        :lead-to-ql="$total['lead_to_ql']"
                        :ql-to-pitch="$total['ql_to_pitch']"
                        :pitch-to-won="$total['pitch_to_won']"
                        :won-to-closing="$total['won_to_closing']"
                        :overall="$total['overall']"
                    />
                </tbody>
            </table>
        </div>

        <p class="text-xs text-base-content/40 mt-3">
            ≥ average: monthly overall ≥ year-to-date average ({{ number_format($this->rataRata, 1) }}%). Near average: 80–100% of the average. Below average: &lt;80% of the average.
        </p>
    </div>

    <!-- Summary -->
    <div>
        <h3 class="text-base font-bold text-base-content mb-3">Year-to-Date Conversion Summary</h3>

        @php $r = $this->ringkasan; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <x-stat-card label="Cumulative Conversion" value="{{ number_format($r['konversi_kumulatif'], 1) }}%" accent="border-t-primary">
                {{ $r['total_closing'] }} closing from {{ $r['total_lead'] }} leads
            </x-stat-card>

            <x-stat-card label="Best Month" value="{{ $r['bulan_terbaik']['month'] ?? '-' }}" accent="border-t-success">
                @if ($r['bulan_terbaik'])
                    Overall {{ number_format($r['bulan_terbaik']['overall'], 1) }}%
                @else
                    No data yet
                @endif
            </x-stat-card>

            <x-stat-card label="Weakest Month" value="{{ $r['bulan_terlemah']['month'] ?? '-' }}" accent="border-t-error">
                @if ($r['bulan_terlemah'])
                    Overall {{ number_format($r['bulan_terlemah']['overall'], 1) }}%
                @else
                    No data yet
                @endif
            </x-stat-card>

            <x-stat-card label="Bottleneck" value="{{ $r['bottleneck']['label'] ?? '-' }}" accent="border-t-warning">
                @if ($r['bottleneck'])
                    Average {{ number_format($r['bottleneck']['average'], 1) }}%
                @else
                    No data yet
                @endif
            </x-stat-card>
        </div>
    </div>

    <!-- Cumulative funnel -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <h3 class="text-base font-bold text-base-content">Cumulative Funnel</h3>
        <p class="text-xs text-base-content/50 mt-1 mb-4">Total across all months elapsed so far.</p>
        <div wire:ignore id="chart-conversion-funnel" style="min-height:280px;"></div>
    </div>

</div>

@script
<script>
(function () {
    const initialFunnel = @json($this->funnelKumulatif);

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
            chart: { ...baseOpts().chart, type: 'bar', height: 280 },
            series: [{ name: 'Count', data: d.map((row) => row.value) }],
            xaxis: {
                categories: d.map((row) => row.label),
                labels: { style: { fontSize: '12px', colors: tc } },
            },
            yaxis: { labels: { style: { fontSize: '13px', fontWeight: 600, colors: tc } } },
            colors: ['#c2410c', '#ea580c', '#eab308', '#6366f1', '#16a34a'],
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4,
                    barHeight: '60%',
                    distributed: true,
                },
            },
            legend: { show: false },
            dataLabels: {
                enabled: true,
                formatter: (v) => v,
                style: { fontSize: '12px', fontWeight: 600, colors: ['#fff'] },
                dropShadow: { enabled: false },
            },
        };

        const el = document.querySelector('#chart-conversion-funnel');
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
        lastData = initialFunnel || [];
        buildChart(lastData);
    });

    $wire.on('conversion-funnel-ready', (payload) => {
        const d = Array.isArray(payload) ? payload[0]?.funnel ?? payload[0] : payload.funnel ?? payload;
        lastData = d;
        ensureApexChartsThen(() => buildChart(lastData));
    });
})();
</script>
@endscript
