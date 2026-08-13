<?php

use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;

layout('layouts.app');

state([
    'viewAsEmployeeId' => null,
    'selectedMonth' => (int) now()->month,
    'selectedYear' => (int) now()->year,

    'bulanOptions' => [
        ['id' => 1, 'name' => 'January'], ['id' => 2, 'name' => 'February'], ['id' => 3, 'name' => 'March'],
        ['id' => 4, 'name' => 'April'], ['id' => 5, 'name' => 'May'], ['id' => 6, 'name' => 'June'],
        ['id' => 7, 'name' => 'July'], ['id' => 8, 'name' => 'August'], ['id' => 9, 'name' => 'September'],
        ['id' => 10, 'name' => 'October'], ['id' => 11, 'name' => 'November'], ['id' => 12, 'name' => 'December'],
    ],
]);

// Admin FO (Investor Admin) / Super Admin bisa "masuk sebagai" siapa saja; FO biasa terkunci ke dirinya sendiri.
$isAdminFo = computed(fn () => auth()->user()->hasRole(['Investor Admin', 'Super Admin']));

$currentEmployee = computed(fn () => Employee::with('user')->where('user_id', auth()->id())->first());

mount(function () {
    if (! $this->isAdminFo) {
        $this->viewAsEmployeeId = $this->currentEmployee?->id;
    }
});

// Semua Funding Officer (+ Admin FO) aktif.
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

// FO mana saja yang datanya ditampilkan — satu FO tertentu, atau seluruh tim kalau "Admin (Semua FO)" dipilih.
$scopedEmployees = computed(function () {
    if (! $this->viewAsEmployeeId) {
        return $this->foEmployees;
    }

    return collect($this->foEmployees)->where('id', (int) $this->viewAsEmployeeId)->values()->all();
});

// Tahun yang tersedia di dropdown — diambil dari rentang data leads asli, biar gak ada tahun kosong.
$tahunOptions = computed(function () {
    return Cache::remember('fo-monthly:tahun-options', 60, function () {
        $yearExpression = FoFunnel::query()->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', tgl_leads) AS INTEGER)"
            : 'CAST(EXTRACT(YEAR FROM tgl_leads) AS INTEGER)';

        $years = FoFunnel::query()
            ->whereNotNull('tgl_leads')
            ->selectRaw("{$yearExpression} as year")
            ->distinct()
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->push((int) now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return $years->map(fn ($year) => ['id' => $year, 'name' => (string) $year])->all();
    });
});

// Realisasi satu metrik untuk satu FO dalam rentang tanggal tertentu — dasar tabel bulanan & tren tahunan.
$realisasiInRange = function ($employeeId, $metricKey, $start, $end) {
    return match ($metricKey) {
        'leads' => FoFunnel::where('employee_id', $employeeId)->whereBetween('tgl_leads', [$start, $end])->count(),
        'ql' => FoFunnel::where('employee_id', $employeeId)->whereNotNull('tgl_qualified')->whereBetween('tgl_qualified', [$start, $end])->count(),
        'pitching' => FoFunnelActivity::where('stage', 'pitching')
            ->whereBetween('tanggal', [$start, $end])
            ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId))
            ->distinct('fo_funnel_id')
            ->count('fo_funnel_id'),
        'followup' => FoFunnelActivity::where('stage', 'followup')
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

// Rekap bulan yang lagi dipilih, per FO yang sedang di-scope — baris tabel utama.
$realisasiPerFo = computed(function () {
    $start = now()->setDate($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
    $end = now()->setDate($this->selectedYear, $this->selectedMonth, 1)->endOfMonth();

    return collect($this->scopedEmployees)->map(function ($fo) use ($start, $end) {
        $leads = $this->realisasiInRange($fo['id'], 'leads', $start, $end);
        $closing = $this->realisasiInRange($fo['id'], 'closing', $start, $end);

        return [
            'name' => $fo['name'],
            'leads' => $leads,
            'ql' => $this->realisasiInRange($fo['id'], 'ql', $start, $end),
            'pitching' => $this->realisasiInRange($fo['id'], 'pitching', $start, $end),
            'followup' => $this->realisasiInRange($fo['id'], 'followup', $start, $end),
            'won' => $this->realisasiInRange($fo['id'], 'won', $start, $end),
            'closing' => $closing,
            'nilai_closing' => $this->realisasiInRange($fo['id'], 'nilai_closing', $start, $end),
            'konversi' => $leads > 0 ? round($closing / $leads * 100, 1) : null,
        ];
    })->values()->all();
});

// Baris "Total Tim" — jumlah dari seluruh baris FO yang sedang ditampilkan di atas.
$totalTim = computed(function () {
    $rows = collect($this->realisasiPerFo);
    $leads = $rows->sum('leads');
    $closing = $rows->sum('closing');

    return [
        'leads' => $leads,
        'ql' => $rows->sum('ql'),
        'pitching' => $rows->sum('pitching'),
        'followup' => $rows->sum('followup'),
        'won' => $rows->sum('won'),
        'closing' => $closing,
        'nilai_closing' => $rows->sum('nilai_closing'),
        'konversi' => $leads > 0 ? round($closing / $leads * 100, 1) : null,
    ];
});

// Tren 12 bulan (Lead Baru vs Closing) untuk tahun yang sedang dipilih, total FO yang sedang di-scope.
$monthlyTrend = computed(function () {
    $employeeIds = collect($this->scopedEmployees)->pluck('id')->all();
    $year = $this->selectedYear;

    $data = collect($this->bulanOptions)->map(function ($bulan) use ($employeeIds, $year) {
        $start = now()->setDate($year, $bulan['id'], 1)->startOfMonth();
        $end = now()->setDate($year, $bulan['id'], 1)->endOfMonth();

        return [
            'label' => Str::substr($bulan['name'], 0, 3),
            'leads' => collect($employeeIds)->sum(fn ($id) => $this->realisasiInRange($id, 'leads', $start, $end)),
            'won' => collect($employeeIds)->sum(fn ($id) => $this->realisasiInRange($id, 'won', $start, $end)),
            'closing' => collect($employeeIds)->sum(fn ($id) => $this->realisasiInRange($id, 'closing', $start, $end)),
        ];
    })->values()->all();

    $this->dispatch('monthly-bulanan-trend-ready', $data);

    return $data;
});

?>

<div class="space-y-6">
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-base-content">Monthly Achievement</h2>
                <p class="text-sm text-base-content/50 mt-1">Realization summary per FO per month (automatically generated from the Pipeline).</p>
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

    <!-- Month/year filter -->
    <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-40">
                <x-select label="Month" wire:model.live="selectedMonth" :options="$bulanOptions" class="select-sm" />
            </div>
            <div class="w-32">
                <x-select label="Year" wire:model.live="selectedYear" :options="$this->tahunOptions" class="select-sm" />
            </div>
        </div>
        <p class="text-xs text-base-content/40 mt-3">
            Read-only — automatically aggregated from the Pipeline (Funnel) for {{ collect($bulanOptions)->firstWhere('id', $selectedMonth)['name'] }} {{ $selectedYear }}. No manual edit mode available.
        </p>
    </div>

    <!-- Rekap per FO -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
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
                        <th class="text-right">Lead→Closing Conversion</th>
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
                            <td class="text-right">{{ $row['konversi'] !== null ? $row['konversi'].'%' : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-base-content/50 py-6">No active FOs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
                @php $total = $this->totalTim; @endphp
                <tfoot>
                    <tr class="border-t-2 border-base-300 font-semibold text-base-content">
                        <td>Team Total</td>
                        <td class="text-right">{{ number_format($total['leads']) }}</td>
                        <td class="text-right">{{ number_format($total['ql']) }}</td>
                        <td class="text-right">{{ number_format($total['pitching']) }}</td>
                        <td class="text-right">{{ number_format($total['followup']) }}</td>
                        <td class="text-right">{{ number_format($total['won']) }}</td>
                        <td class="text-right">{{ number_format($total['closing']) }}</td>
                        <td class="text-right"><x-currency-abbr :value="$total['nilai_closing']" /></td>
                        <td class="text-right">{{ $total['konversi'] !== null ? $total['konversi'].'%' : '-' }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Tren Bulanan -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <h3 class="text-base font-bold text-base-content">Team Monthly Trend</h3>
        <p class="text-xs text-base-content/50 mt-1 mb-4">
            Total realization for {{ $viewAsEmployeeId ? 'the selected FO' : 'all FOs' }} per month (year {{ $selectedYear }}).
        </p>
        <div wire:ignore id="chart-trend-bulanan" style="min-height:300px;"></div>
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
            chart: { ...baseOpts().chart, type: 'bar', height: 300 },
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
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
            legend: { show: true, position: 'top', horizontalAlign: 'right', fontSize: '12px', labels: { colors: tc } },
            dataLabels: { enabled: false },
        };

        const el = document.querySelector('#chart-trend-bulanan');
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

    $wire.on('monthly-bulanan-trend-ready', (payload) => {
        const d = Array.isArray(payload) ? payload[0]?.trend ?? payload[0] : payload.trend ?? payload;
        lastData = d;
        ensureApexChartsThen(() => buildChart(lastData));
    });
})();
</script>
@endscript
