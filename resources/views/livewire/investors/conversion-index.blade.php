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
        'pitch_to_closing' => 'Pitching → Closing',
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
    return collect([['id' => '', 'name' => 'Admin (Semua FO)']])
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
        'closing' => FoFunnel::where('employee_id', $employeeId)->whereIn('stage', ['won', 'akad'])->whereBetween('tgl_closing', [$start, $end])->count(),
        'nilai_closing' => (float) FoFunnel::where('employee_id', $employeeId)->whereIn('stage', ['won', 'akad'])->whereBetween('tgl_closing', [$start, $end])->sum('nominal_investasi'),
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
        $closing = $this->teamRealisasiInRange('closing', $start, $end);
        $nilaiClosing = $this->teamRealisasiInRange('nilai_closing', $start, $end);

        return [
            'month' => $this->bulanLabels[$month - 1],
            'hasData' => $lead > 0,
            'lead' => $lead,
            'ql' => $ql,
            'pitching' => $pitching,
            'closing' => $closing,
            'nilai_closing' => $nilaiClosing,
            'lead_to_ql' => $lead > 0 ? round($ql / $lead * 100, 1) : null,
            'ql_to_pitch' => $ql > 0 ? round($pitching / $ql * 100, 1) : null,
            'pitch_to_closing' => $pitching > 0 ? round($closing / $pitching * 100, 1) : null,
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
        $overall >= $rataRata => ['label' => '≥ rata-rata', 'class' => 'badge-success'],
        $overall / $rataRata >= 0.8 => ['label' => 'mendekati', 'class' => 'badge-warning'],
        default => ['label' => 'di bawah', 'class' => 'badge-error'],
    };
};

$totalRow = computed(function () {
    $rows = collect($this->monthlyRows);
    $lead = $rows->sum('lead');
    $ql = $rows->sum('ql');
    $pitching = $rows->sum('pitching');
    $closing = $rows->sum('closing');

    return [
        'lead' => $lead,
        'ql' => $ql,
        'pitching' => $pitching,
        'closing' => $closing,
        'nilai_closing' => $rows->sum('nilai_closing'),
        'lead_to_ql' => $lead > 0 ? round($ql / $lead * 100, 1) : null,
        'ql_to_pitch' => $ql > 0 ? round($pitching / $ql * 100, 1) : null,
        'pitch_to_closing' => $pitching > 0 ? round($closing / $pitching * 100, 1) : null,
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
        ['label' => 'Lead Baru', 'value' => $total['lead']],
        ['label' => 'Qualified Lead', 'value' => $total['ql']],
        ['label' => 'Pitching', 'value' => $total['pitching']],
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
                <h2 class="text-xl font-bold text-base-content">Konversi Bulanan</h2>
                <p class="text-sm text-base-content/50 mt-1">Funnel konversi tim + ringkasan tahun berjalan.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if ($this->isAdminFo)
                    <div class="w-56">
                        <x-select label="Masuk sebagai" wire:model.live="viewAsEmployeeId" :options="$this->viewAsOptions" class="select-sm" />
                    </div>
                @else
                    <div class="text-sm text-base-content/50">
                        Masuk sebagai <span class="font-semibold text-base-content">{{ $this->currentEmployee?->user?->name }}</span>
                    </div>
                @endif

                <div class="badge badge-lg badge-outline border-success/50 text-success">
                    Tanggal Kerja: {{ now()->translatedFormat('l, d F Y') }}
                </div>
            </div>
        </div>
    </div>

    <div class="alert bg-info/10 border border-info/30 text-info text-sm">
        <x-icon name="o-information-circle" class="w-5 h-5" />
        <span>Seluruh angka dihitung otomatis dari modul Funnel (Pipeline) — halaman ini read-only, tidak ada input manual.</span>
    </div>

    <!-- Tabel konversi bulanan -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <p class="text-xs text-base-content/50 mb-3">
            Read-only. Baris bulanan pakai persentase per-bulan; baris Total pakai kumulatif (SUM/SUM).
        </p>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>Bulan</th>
                        <th>Lead</th>
                        <th>QL</th>
                        <th>Pitching</th>
                        <th>Closing</th>
                        <th>Nilai Closing</th>
                        <th>Lead→QL</th>
                        <th>QL→Pitch</th>
                        <th>Pitch→Closing</th>
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
                            :closing="$row['closing']"
                            :nilai-closing="$row['nilai_closing']"
                            :lead-to-ql="$row['lead_to_ql']"
                            :ql-to-pitch="$row['ql_to_pitch']"
                            :pitch-to-closing="$row['pitch_to_closing']"
                            :overall="$row['overall']"
                            :status="$row['hasData'] ? $this->statusFor($row['overall']) : null"
                        />
                    @endforeach

                    @php $total = $this->totalRow; @endphp
                    <x-investors.conversion-row
                        :month="'Total ('.$total['active_months'].' bulan aktif)'"
                        :is-total="true"
                        has-data
                        :lead="$total['lead']"
                        :ql="$total['ql']"
                        :pitching="$total['pitching']"
                        :closing="$total['closing']"
                        :nilai-closing="$total['nilai_closing']"
                        :lead-to-ql="$total['lead_to_ql']"
                        :ql-to-pitch="$total['ql_to_pitch']"
                        :pitch-to-closing="$total['pitch_to_closing']"
                        :overall="$total['overall']"
                    />
                </tbody>
            </table>
        </div>

        <p class="text-xs text-base-content/40 mt-3">
            ≥ rata-rata: overall bulan ≥ rata-rata tahun berjalan ({{ number_format($this->rataRata, 1) }}%). Mendekati: 80–100% dari rata-rata. Di bawah: &lt;80% dari rata-rata.
        </p>
    </div>

    <!-- Ringkasan -->
    <div>
        <h3 class="text-base font-bold text-base-content mb-3">Ringkasan Konversi Tahun Berjalan</h3>

        @php $r = $this->ringkasan; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <x-stat-card label="Konversi Kumulatif" value="{{ number_format($r['konversi_kumulatif'], 1) }}%" accent="border-t-primary">
                {{ $r['total_closing'] }} closing dari {{ $r['total_lead'] }} lead
            </x-stat-card>

            <x-stat-card label="Bulan Terbaik" value="{{ $r['bulan_terbaik']['month'] ?? '-' }}" accent="border-t-success">
                @if ($r['bulan_terbaik'])
                    Overall {{ number_format($r['bulan_terbaik']['overall'], 1) }}%
                @else
                    Belum ada data
                @endif
            </x-stat-card>

            <x-stat-card label="Bulan Terlemah" value="{{ $r['bulan_terlemah']['month'] ?? '-' }}" accent="border-t-error">
                @if ($r['bulan_terlemah'])
                    Overall {{ number_format($r['bulan_terlemah']['overall'], 1) }}%
                @else
                    Belum ada data
                @endif
            </x-stat-card>

            <x-stat-card label="Bottleneck" value="{{ $r['bottleneck']['label'] ?? '-' }}" accent="border-t-warning">
                @if ($r['bottleneck'])
                    Rata-rata {{ number_format($r['bottleneck']['average'], 1) }}%
                @else
                    Belum ada data
                @endif
            </x-stat-card>
        </div>
    </div>

    <!-- Funnel kumulatif -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <h3 class="text-base font-bold text-base-content">Funnel Kumulatif</h3>
        <p class="text-xs text-base-content/50 mt-1 mb-4">Total dari seluruh bulan yang sudah berjalan.</p>
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
            series: [{ name: 'Jumlah', data: d.map((row) => row.value) }],
            xaxis: {
                categories: d.map((row) => row.label),
                labels: { style: { fontSize: '12px', colors: tc } },
            },
            yaxis: { labels: { style: { fontSize: '13px', fontWeight: 600, colors: tc } } },
            colors: ['#c2410c', '#ea580c', '#eab308', '#16a34a'],
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
