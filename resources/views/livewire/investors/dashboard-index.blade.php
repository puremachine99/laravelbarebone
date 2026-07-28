<?php

use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoTarget;
use Illuminate\Support\Facades\Cache;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;

layout('layouts.app');

state(['viewAsEmployeeId' => null]);

$metricsData = [
    ['key' => 'leads', 'label' => 'Lead Baru'],
    ['key' => 'ql', 'label' => 'Qualified Lead'],
    ['key' => 'pitching', 'label' => 'Pitching'],
    ['key' => 'closing', 'label' => 'Closing (Deal)'],
];

state(['metrics' => $metricsData]);

// Admin FO (Investor Admin) / Super Admin bisa "masuk sebagai" siapa saja; FO biasa terkunci ke dirinya sendiri.
$isAdminFo = computed(fn () => auth()->user()->hasRole(['Investor Admin', 'Super Admin']));

$currentEmployee = computed(fn () => Employee::with('user')->where('user_id', auth()->id())->first());

mount(function () {
    if (! $this->isAdminFo) {
        $this->viewAsEmployeeId = $this->currentEmployee?->id;
    }
});

// Semua Funding Officer (+ Admin FO) aktif — dasar tabel realisasi & pembagian target tim.
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

// FO mana saja yang datanya ditampilkan — satu FO tertentu, atau seluruh tim kalau "Admin (Semua FO)" dipilih.
$scopedEmployees = computed(function () {
    if (! $this->viewAsEmployeeId) {
        return $this->foEmployees;
    }

    return collect($this->foEmployees)->where('id', (int) $this->viewAsEmployeeId)->values()->all();
});

$scopedEmployeeIds = computed(fn () => collect($this->scopedEmployees)->pluck('id')->all());

// Target tahunan tersimpan di fo_targets (diatur per FO di halaman Management FO). Kalau belum pernah diisi
// admin untuk FO/metrik tersebut, dianggap 0 — dashboard ini murni menampilkan apa yang sudah diatur, bukan
// mengarang saran baru.
$yearlyTargetFor = function ($employeeId, $metricKey) {
    return (float) (FoTarget::where('employee_id', $employeeId)
        ->where('year', now()->year)
        ->where('metric_key', $metricKey)
        ->value('yearly_target') ?? 0);
};

// Target tahunan dipecah rata ke harian: ÷12 bulan ÷25 hari kerja/bulan (konvensi sama dengan Management FO).
$dailyTargetFor = fn ($employeeId, $metricKey) => $this->yearlyTargetFor($employeeId, $metricKey) / 12 / 25;

$realisasiForFo = function ($employeeId, $metricKey, $date) {
    return match ($metricKey) {
        'leads' => FoFunnel::where('employee_id', $employeeId)->whereDate('tgl_leads', $date)->count(),
        'ql' => FoFunnel::where('employee_id', $employeeId)->whereNotNull('tgl_qualified')->whereDate('tgl_qualified', $date)->count(),
        'pitching' => FoFunnelActivity::where('stage', 'pitching')
            ->whereDate('tanggal', $date)
            ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId))
            ->distinct('fo_funnel_id')
            ->count('fo_funnel_id'),
        'followup' => FoFunnelActivity::where('stage', 'followup')
            ->whereDate('tanggal', $date)
            ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId))
            ->distinct('fo_funnel_id')
            ->count('fo_funnel_id'),
        'closing' => FoFunnel::where('employee_id', $employeeId)->whereIn('stage', ['won', 'akad'])->whereDate('tgl_closing', $date)->count(),
        'nilai_closing' => (float) FoFunnel::where('employee_id', $employeeId)->whereIn('stage', ['won', 'akad'])->whereDate('tgl_closing', $date)->sum('nominal_investasi'),
        default => 0,
    };
};

$statusFor = function (?float $percent) {
    return match (true) {
        $percent === null => ['label' => 'MERAH', 'class' => 'badge-error'],
        $percent >= 100 => ['label' => 'HIJAU', 'class' => 'badge-success'],
        $percent >= 50 => ['label' => 'KUNING', 'class' => 'badge-warning'],
        default => ['label' => 'MERAH', 'class' => 'badge-error'],
    };
};

// Ringkasan hari ini per metrik untuk FO yang sedang di-scope (tim atau satu orang) — dipakai 5 stat card teratas.
$todaySummary = computed(function () {
    $today = now()->toDateString();
    $employeeIds = $this->scopedEmployeeIds;

    $summary = [];

    foreach ($this->metrics as $metric) {
        $realisasi = collect($employeeIds)->sum(fn ($id) => $this->realisasiForFo($id, $metric['key'], $today));
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
        'label' => 'Nilai Closing',
        'realisasi' => collect($employeeIds)->sum(fn ($id) => $this->realisasiForFo($id, 'nilai_closing', $today)),
    ];

    return $summary;
});

// Tabel "Realisasi per FO" hari ini — status per baris didasarkan pada capaian Lead Baru (metrik funnel paling awal).
$realisasiPerFo = computed(function () {
    $today = now()->toDateString();

    return collect($this->scopedEmployees)->map(function ($fo) use ($today) {
        $leads = $this->realisasiForFo($fo['id'], 'leads', $today);
        $target = $this->dailyTargetFor($fo['id'], 'leads');
        $percent = $target > 0 ? round($leads / $target * 100) : ($leads > 0 ? 100.0 : 0.0);

        return [
            'name' => $fo['name'],
            'leads' => $leads,
            'ql' => $this->realisasiForFo($fo['id'], 'ql', $today),
            'pitching' => $this->realisasiForFo($fo['id'], 'pitching', $today),
            'followup' => $this->realisasiForFo($fo['id'], 'followup', $today),
            'closing' => $this->realisasiForFo($fo['id'], 'closing', $today),
            'nilai_closing' => $this->realisasiForFo($fo['id'], 'nilai_closing', $today),
            'status' => $this->statusFor($percent),
        ];
    })->values()->all();
});

// Tren 14 hari terakhir (Lead Baru vs Closing), total FO yang sedang di-scope. Di-dispatch tiap render supaya
// chart JS (di-render sekali lewat @script) selalu tahu update-an terbaru setelah "masuk sebagai" diganti.
$trend = computed(function () {
    $employeeIds = $this->scopedEmployeeIds;

    $data = collect(range(13, 0))->map(function ($daysAgo) use ($employeeIds) {
        $date = now()->subDays($daysAgo);

        return [
            'label' => $date->format('d/m'),
            'leads' => collect($employeeIds)->sum(fn ($id) => FoFunnel::where('employee_id', $id)->whereDate('tgl_leads', $date)->count()),
            'closing' => collect($employeeIds)->sum(fn ($id) => FoFunnel::where('employee_id', $id)->whereIn('stage', ['won', 'akad'])->whereDate('tgl_closing', $date)->count()),
        ];
    })->values()->all();

    $this->dispatch('dashboard-harian-trend-ready', $data);

    return $data;
});

// Papan peringkat all-time — selalu seluruh FO tim, tidak ikut ke-scope "masuk sebagai" biar tetap bisa dibandingkan.
$leaderboard = computed(function () {
    return collect($this->foEmployees)->map(function ($fo) {
        return [
            'name' => $fo['name'],
            'lead' => FoFunnel::where('employee_id', $fo['id'])->count(),
            'closing' => FoFunnel::where('employee_id', $fo['id'])->whereIn('stage', ['won', 'akad'])->count(),
            'nilai_closing' => (float) FoFunnel::where('employee_id', $fo['id'])->whereIn('stage', ['won', 'akad'])->sum('nominal_investasi'),
        ];
    })->sortByDesc('nilai_closing')->values()->all();
});

?>

<div class="space-y-6">
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-base-content">Dashboard Harian</h2>
                <p class="text-sm text-base-content/50 mt-1">
                    Ringkasan capaian tim hari ini, tren 14 hari & papan peringkat — read-only, otomatis dari Pipeline.
                </p>
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

    <!-- Stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
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
            <div class="text-xs font-semibold text-base-content/40 uppercase tracking-wide">Nilai Closing</div>
            <div class="text-2xl font-bold text-base-content mt-1"><x-currency-abbr :value="$nc['realisasi']" /></div>
            <div class="text-xs text-base-content/40 mt-2">Hari ini</div>
        </div>
    </div>

    <!-- Realisasi per FO -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <h3 class="text-base font-bold text-base-content">Realisasi per FO — {{ now()->translatedFormat('l, d F Y') }}</h3>
        <p class="text-xs text-base-content/50 mt-1 mb-4">
            Dihitung otomatis dari perpindahan stage di Pipeline hari ini. Read-only — tidak ada input manual.
        </p>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>FO</th>
                        <th class="text-right">Lead Baru</th>
                        <th class="text-right">QL</th>
                        <th class="text-right">Pitching</th>
                        <th class="text-right">Follow Up</th>
                        <th class="text-right">Closing</th>
                        <th class="text-right">Nilai Closing</th>
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
                            <td class="text-right">{{ number_format($row['closing']) }}</td>
                            <td class="text-right"><x-currency-abbr :value="$row['nilai_closing']" /></td>
                            <td><span class="badge badge-sm {{ $row['status']['class'] }}">{{ $row['status']['label'] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-base-content/50 py-6">Belum ada FO aktif.</td>
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
            Target individu = target tim ÷ jumlah FO aktif ({{ number_format($teamLeadsTarget, 1) }} ÷ {{ $foCount }} = {{ $perFoTarget }} Lead Baru/hari).
            Target diatur per FO per tahun di halaman Management FO.
        </p>
    </div>

    <!-- Tren 14 Hari -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <h3 class="text-base font-bold text-base-content">Tren 14 Hari Terakhir</h3>
        <p class="text-xs text-base-content/50 mt-1 mb-4">
            Total harian {{ $viewAsEmployeeId ? 'FO terpilih' : 'seluruh tim' }} per tahap funnel.
        </p>
        <div wire:ignore id="chart-trend-harian" style="min-height:300px;"></div>
    </div>

    <!-- Papan Peringkat FO -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <h3 class="text-base font-bold text-base-content">Papan Peringkat FO</h3>
        <p class="text-xs text-base-content/50 mt-1 mb-4">Diurutkan dari nilai closing kumulatif tertinggi (all-time).</p>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>#</th>
                        <th>FO</th>
                        <th class="text-right">Lead</th>
                        <th class="text-right">Closing</th>
                        <th class="text-right">Nilai Closing Kumulatif</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->leaderboard as $i => $fo)
                        <tr class="border-b border-base-300">
                            <td>{{ $i + 1 }}</td>
                            <td class="font-medium text-base-content">{{ $fo['name'] }}</td>
                            <td class="text-right">{{ number_format($fo['lead']) }}</td>
                            <td class="text-right">{{ number_format($fo['closing']) }}</td>
                            <td class="text-right font-semibold text-base-content"><x-currency-abbr :value="$fo['nilai_closing']" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-base-content/50 py-6">Belum ada data FO.</td>
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
                { name: 'Lead Baru', data: d.map((row) => row.leads) },
                { name: 'Closing', data: d.map((row) => row.closing) },
            ],
            xaxis: {
                categories: d.map((row) => row.label),
                labels: { style: { fontSize: '12px', colors: tc } },
            },
            yaxis: { labels: { formatter: (v) => Math.round(v), style: { colors: tc } } },
            colors: ['#f97316', '#10b981'],
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
