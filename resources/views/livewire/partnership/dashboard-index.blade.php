<?php

use function Livewire\Volt\{state, layout, computed};

layout('layouts.app');

state(['filterFiscalYear' => (string) date('Y')]);

\Livewire\Volt\mount(function () {
    $this->dispatch('dashboard-charts-ready', $this->dashboardData);
});

$applyFilter = function () {
    $this->dispatch('dashboard-charts-ready', $this->dashboardData);
};

$dashboardData = computed(function () {
    $user       = auth()->user();
    $isSupervisor = $user->hasRole('Partnership Admin');
    $regionId   = $isSupervisor
        ? \App\Models\Employee::where('user_id', auth()->id())->value('region_id')
        : null;

    $year = (int) $this->filterFiscalYear;

    // ── Base builder ───────────────────────────────────────────
    $base = \App\Models\Card::query()
        ->where('fiscal_year', $year)
        ->when($isSupervisor && $regionId, fn($q) =>
            $q->whereHas('employee', fn($e) => $e->where('region_id', $regionId))
        );

    // ── Summary (load once, aggregate in PHP) ──────────────────
    $allCards = (clone $base)->with('stage')->get();
    $total    = $allCards->count();
    $wonCount = $allCards->where('status', 'won')->count();
    $hotCount = $allCards->where('status', 'hot')->count();
    $convRate = $total > 0 ? round(($wonCount / $total) * 100, 1) : 0;

    $nominalByStatus = [
        'won'  => (float) $allCards->where('status', 'won')->sum('nominal_value'),
        'hot'  => (float) $allCards->where('status', 'hot')->sum('nominal_value'),
        'warm' => (float) $allCards->where('status', 'warm')->sum('nominal_value'),
        'lost' => (float) $allCards->where('status', 'lost')->sum('nominal_value'),
    ];

    // Region scope label
    $scopeLabel = 'Semua Region';
    if ($isSupervisor && $regionId) {
        $scopeLabel = \App\Models\Region::find($regionId)?->name ?? 'Region Anda';
    }

    // ── Chart 1: Monthly pitching count ────────────────────────
    $monthlyRaw = (clone $base)
        ->selectRaw('EXTRACT(MONTH FROM pitching_date)::int AS month, COUNT(*) AS cnt')
        ->whereNotNull('pitching_date')
        ->groupByRaw('EXTRACT(MONTH FROM pitching_date)')
        ->pluck('cnt', 'month')
        ->toArray();

    $monthlyLabels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    $monthlyCounts = [];
    for ($m = 1; $m <= 12; $m++) {
        $monthlyCounts[] = (int) ($monthlyRaw[$m] ?? 0);
    }

    // ── Chart 2: Region vs Nominal  (per PMO if supervisor) ────
    if ($isSupervisor && $regionId) {
        // Show per-PMO breakdown within their region
        $regionRows = \App\Models\Card::query()
            ->where('fiscal_year', $year)
            ->whereHas('employee', fn($e) => $e->where('region_id', $regionId))
            ->join('employees', 'cards.employee_id', '=', 'employees.id')
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->whereNotNull('cards.employee_id')
            ->selectRaw('users.name AS label, SUM(cards.nominal_value) AS total_nominal, COUNT(*) AS cnt')
            ->groupBy('employees.id', 'users.id', 'users.name')
            ->orderByDesc('total_nominal')
            ->get();
    } else {
        $regionRows = \App\Models\Card::query()
            ->where('fiscal_year', $year)
            ->join('employees', 'cards.employee_id', '=', 'employees.id')
            ->join('regions', 'employees.region_id', '=', 'regions.id')
            ->whereNotNull('cards.employee_id')
            ->selectRaw('regions.name AS label, SUM(cards.nominal_value) AS total_nominal, COUNT(*) AS cnt')
            ->groupBy('regions.id', 'regions.name')
            ->orderByDesc('total_nominal')
            ->get();
    }
    $regionLabels   = $regionRows->pluck('label')->toArray();
    $regionNominals = $regionRows->pluck('total_nominal')->map(fn($v) => (float) $v)->toArray();
    $regionCounts   = $regionRows->pluck('cnt')->map(fn($v) => (int) $v)->toArray();

    // ── Widget 3: Top 3 Performers ─────────────────────────────
    $topPerformers = \App\Models\Card::query()
        ->where('fiscal_year', $year)
        ->when($isSupervisor && $regionId, fn($q) =>
            $q->whereHas('employee', fn($e) => $e->where('region_id', $regionId))
        )
        ->join('employees', 'cards.employee_id', '=', 'employees.id')
        ->join('users', 'employees.user_id', '=', 'users.id')
        ->whereNotNull('cards.employee_id')
        ->selectRaw('users.name AS pmo_name, SUM(cards.nominal_value) AS total_nominal, COUNT(*) AS pitching_count')
        ->groupBy('employees.id', 'users.id', 'users.name')
        ->orderByDesc('total_nominal')
        ->limit(3)
        ->get()
        ->toArray();

    // ── Chart 4: Sumber distribution ───────────────────────────
    $sumberRaw  = (clone $base)
        ->selectRaw('sumber_proyeksi, COUNT(*) AS cnt')
        ->whereNotNull('sumber_proyeksi')
        ->where('sumber_proyeksi', '<>', '')
        ->groupBy('sumber_proyeksi')
        ->orderByDesc('cnt')
        ->get();

    $sumberTotal   = $sumberRaw->sum('cnt') ?: 1;
    $sumberLabels  = $sumberRaw->pluck('sumber_proyeksi')->toArray();
    $sumberCounts  = $sumberRaw->pluck('cnt')->map(fn($v) => (int) $v)->toArray();
    $sumberPercents = $sumberRaw->map(fn($r) => round(($r->cnt / $sumberTotal) * 100, 1))->toArray();

    // ── Widget 5: Pipeline Funnel ──────────────────────────────
    $stageCounts = $allCards
        ->groupBy(fn($c) => $c->stage?->stage_name ?? 'Lainnya')
        ->map->count();

    $funnel = [
        'rencana'          => (int) ($stageCounts['Rencana'] ?? 0),
        'terlaksana'       => (int) ($stageCounts['Terlaksana'] ?? 0),
        'tidak_terlaksana' => (int) ($stageCounts['Tidak Terlaksana'] ?? 0),
        'won'              => $wonCount,
    ];

    // ── Widget 6: Top 5 Produk ─────────────────────────────────
    $topProducts = (clone $base)
        ->selectRaw('title, COUNT(*) AS cnt, SUM(nominal_value) AS total_nominal')
        ->groupBy('title')
        ->orderByDesc('cnt')
        ->limit(5)
        ->get()
        ->toArray();

    return [
        // Summary
        'total_pitching'       => $total,
        'nominal_by_status'    => $nominalByStatus,
        'won_count'            => $wonCount,
        'hot_count'            => $hotCount,
        'conversion_rate'      => $convRate,
        'pending_verification' => $allCards->where('manager_verified', false)->count(),
        'scope_label'          => $scopeLabel,
        'is_supervisor'        => $isSupervisor,
        // Chart 1
        'monthly_labels'  => $monthlyLabels,
        'monthly_counts'  => $monthlyCounts,
        // Chart 2
        'region_labels'   => $regionLabels,
        'region_nominals' => $regionNominals,
        'region_counts'   => $regionCounts,
        'region_chart_title' => $isSupervisor ? 'Nominal per PMO (Region Anda)' : 'Nominal per Region',
        // Widget 3
        'top_performers' => $topPerformers,
        // Chart 4
        'sumber_labels'   => $sumberLabels,
        'sumber_counts'   => $sumberCounts,
        'sumber_percents' => $sumberPercents,
        // Widget 5
        'funnel' => $funnel,
        // Widget 6
        'top_products' => $topProducts,
    ];
});

new class {};
?>

<div class="space-y-6">

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Partnership Dashboard</h1>
            <p class="text-sm text-base-content/50 mt-1">
                Overview analytics pitching & realisasi &mdash;
                <span class="font-medium text-indigo-500">{{ $this->dashboardData['scope_label'] }}</span>
            </p>
        </div>
        <div class="flex items-center gap-3">
            @php
                $years = array_map(fn($y) => ['id' => (string)$y, 'name' => (string)$y],
                    range(date('Y'), date('Y') - 3));
            @endphp
            <x-select wire:model="filterFiscalYear" wire:change="applyFilter" :options="$years" class="select-sm w-28" />
            @if(auth()->user()->hasRole('Super Admin'))
                <span class="badge badge-neutral badge-sm font-semibold">Super Admin</span>
            @elseif(auth()->user()->hasRole('Partnership Admin'))
                <span class="badge badge-info badge-sm font-semibold text-white">Supervisor</span>
            @endif
        </div>
    </div>

    {{-- ── Summary Cards ────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Pitching --}}
        <div class="card bg-base-100 border border-base-300 p-5 rounded-2xl shadow-xs">
            <p class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Total Pitching</p>
            <p class="text-3xl font-extrabold text-base-content mt-1">{{ number_format($this->dashboardData['total_pitching']) }}</p>
            <p class="text-xs text-base-content/50 mt-1">Kegiatan tahun {{ $filterFiscalYear }}</p>
        </div>
        {{-- Nominal per Status --}}
        <div class="card bg-base-100 border border-base-300 p-5 rounded-2xl shadow-xs">
            <p class="text-xs font-bold text-base-content/40 uppercase tracking-wider mb-3">Nominal per Status</p>
            @php $nbs = $this->dashboardData['nominal_by_status']; @endphp
            <div class="space-y-2">
                <div class="flex justify-between items-center text-xs">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-600 inline-block shrink-0"></span>
                        <span class="text-base-content/70">Won</span>
                    </span>
                    <span class="font-bold text-emerald-600">Rp {{ number_format($nbs['won'], 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-orange-500 inline-block shrink-0"></span>
                        <span class="text-base-content/70">Hot</span>
                    </span>
                    <span class="font-bold text-orange-500">Rp {{ number_format($nbs['hot'], 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-500 inline-block shrink-0"></span>
                        <span class="text-base-content/70">Warm</span>
                    </span>
                    <span class="font-bold text-blue-500">Rp {{ number_format($nbs['warm'], 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-red-400 inline-block shrink-0"></span>
                        <span class="text-base-content/70">Lost</span>
                    </span>
                    <span class="font-bold text-red-500">Rp {{ number_format($nbs['lost'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        {{-- Conversion Rate --}}
        <div class="card bg-base-100 border border-base-300 p-5 rounded-2xl shadow-xs">
            <p class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Win Rate</p>
            <p class="text-3xl font-extrabold text-emerald-600 mt-1">{{ $this->dashboardData['conversion_rate'] }}%</p>
            <div class="flex items-center gap-3 mt-2 text-xs text-base-content/50">
                <span class="text-emerald-500 font-semibold">Won: {{ $this->dashboardData['won_count'] }}</span>
                <span class="text-orange-500 font-semibold">Hot: {{ $this->dashboardData['hot_count'] }}</span>
            </div>
        </div>
        {{-- Pending Verifikasi --}}
        <div class="card bg-base-100 border border-base-300 p-5 rounded-2xl shadow-xs">
            <p class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Pending Verif Manager</p>
            <p class="text-3xl font-extrabold text-amber-500 mt-1">{{ number_format($this->dashboardData['pending_verification']) }}</p>
            <p class="text-xs text-base-content/50 mt-1">Menunggu verifikasi</p>
        </div>
    </div>

    {{-- ── Chart 1: Monthly Pitching ───────────────────────────── --}}
    <div class="card bg-base-100 border border-base-300 p-6 rounded-2xl shadow-xs">
        <h2 class="text-base font-bold text-base-content mb-1">Jumlah Pitching per Bulan</h2>
        <p class="text-xs text-base-content/40 mb-4">Distribusi kegiatan pitching sepanjang tahun {{ $filterFiscalYear }}</p>
        <div wire:ignore id="chart-monthly" style="min-height:220px;"></div>
    </div>

    {{-- ── Row 2: Chart 2 + Widget 3 ──────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Chart 2: Region / PMO Nominal --}}
        <div class="card bg-base-100 border border-base-300 p-6 rounded-2xl shadow-xs">
            <h2 class="text-base font-bold text-base-content mb-1">
                {{ $this->dashboardData['region_chart_title'] }}
            </h2>
            <p class="text-xs text-base-content/40 mb-4">Total nilai nominal per wilayah</p>
            <div wire:ignore id="chart-region" style="min-height:220px;"></div>
        </div>

        {{-- Widget 3: Top 3 Performers --}}
        <div class="card bg-base-100 border border-base-300 p-6 rounded-2xl shadow-xs">
            <h2 class="text-base font-bold text-base-content mb-1">Top 3 PMO Terbaik</h2>
            <p class="text-xs text-base-content/40 mb-4">Berdasarkan total nilai nominal pitching</p>
            <div class="space-y-4">
                @forelse($this->dashboardData['top_performers'] as $i => $pmo)
                    @php
                        $medals = ['🥇','🥈','🥉'];
                        $colors = ['bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700',
                                   'bg-base-200/60 border-base-300',
                                   'bg-base-200/40 border-base-300'];
                        $nominalColors = ['text-amber-600','text-base-content/80','text-base-content/60'];
                    @endphp
                    <div class="flex items-center gap-4 p-3 rounded-xl border {{ $colors[$i] ?? 'bg-base-200/40 border-base-300' }}">
                        <span class="text-2xl shrink-0">{{ $medals[$i] ?? '#'.($i+1) }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-base-content truncate">{{ $pmo['pmo_name'] }}</p>
                            <p class="text-xs text-base-content/50">{{ $pmo['pitching_count'] }} pitching</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="font-bold text-sm {{ $nominalColors[$i] ?? '' }}">
                                Rp {{ number_format($pmo['total_nominal'], 0, ',', '.') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-base-content/40 italic text-center py-8">Belum ada data PMO.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Row 3: Chart 4 (Sumber) + Widget 5 (Funnel) ────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Chart 4: Sumber Donut --}}
        <div class="card bg-base-100 border border-base-300 p-6 rounded-2xl shadow-xs">
            <h2 class="text-base font-bold text-base-content mb-1">Sumber Proyeksi</h2>
            <p class="text-xs text-base-content/40 mb-4">Distribusi kegiatan berdasarkan sumber prospek</p>
            <div wire:ignore id="chart-sumber" style="min-height:340px;"></div>
        </div>

        {{-- Widget 5: Pipeline Funnel --}}
        <div class="card bg-base-100 border border-base-300 p-6 rounded-2xl shadow-xs">
            <h2 class="text-base font-bold text-base-content mb-1">Pipeline Funnel</h2>
            <p class="text-xs text-base-content/40 mb-4">Konversi dari rencana hingga deal</p>
            @php
                $f = $this->dashboardData['funnel'];
                $fMax = max($f['rencana'], 1);
            @endphp
            <div class="space-y-5 mt-2">
                {{-- Rencana --}}
                <div>
                    <div class="flex justify-between text-xs mb-1.5">
                        <span class="font-semibold text-base-content/70">Rencana</span>
                        <span class="font-bold text-base-content">{{ number_format($f['rencana']) }}</span>
                    </div>
                    <div class="h-3 bg-base-200 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-400 rounded-full transition-all duration-500"
                             style="width: 100%"></div>
                    </div>
                </div>
                {{-- Terlaksana --}}
                <div>
                    <div class="flex justify-between text-xs mb-1.5">
                        <span class="font-semibold text-base-content/70">Terlaksana</span>
                        <span class="font-bold text-base-content">{{ number_format($f['terlaksana']) }}</span>
                    </div>
                    <div class="h-3 bg-base-200 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all duration-500"
                             style="width: {{ $fMax > 0 ? round(($f['terlaksana'] / $fMax) * 100) : 0 }}%"></div>
                    </div>
                </div>
                {{-- Tidak Terlaksana --}}
                <div>
                    <div class="flex justify-between text-xs mb-1.5">
                        <span class="font-semibold text-base-content/70">Tidak Terlaksana</span>
                        <span class="font-bold text-base-content">{{ number_format($f['tidak_terlaksana']) }}</span>
                    </div>
                    <div class="h-3 bg-base-200 rounded-full overflow-hidden">
                        <div class="h-full bg-red-400 rounded-full transition-all duration-500"
                             style="width: {{ $fMax > 0 ? round(($f['tidak_terlaksana'] / $fMax) * 100) : 0 }}%"></div>
                    </div>
                </div>
                {{-- Won --}}
                <div>
                    <div class="flex justify-between text-xs mb-1.5">
                        <span class="font-semibold text-base-content/70">Won (Deal)</span>
                        <span class="font-bold text-emerald-600">{{ number_format($f['won']) }}</span>
                    </div>
                    <div class="h-3 bg-base-200 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-600 rounded-full transition-all duration-500"
                             style="width: {{ $fMax > 0 ? round(($f['won'] / $fMax) * 100) : 0 }}%"></div>
                    </div>
                </div>
                {{-- Conversion summary --}}
                <div class="pt-2 border-t border-base-300 flex items-center justify-between text-xs text-base-content/50">
                    <span>Conversion Rencana → Won</span>
                    <span class="font-bold text-emerald-600">
                        {{ $f['rencana'] > 0 ? round(($f['won'] / $f['rencana']) * 100, 1) : 0 }}%
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Widget 6: Top 5 Produk ──────────────────────────────── --}}
    <div class="card bg-base-100 border border-base-300 p-6 rounded-2xl shadow-xs">
        <h2 class="text-base font-bold text-base-content mb-1">Top 5 Produk / Output</h2>
        <p class="text-xs text-base-content/40 mb-4">Produk yang paling sering dipitching berdasarkan frekuensi</p>
        <div wire:ignore id="chart-products" style="min-height:220px;"></div>
    </div>

    {{-- ── Inject initial data so charts render immediately on first visit ──────── --}}
    <script>window.__partnershipDashboard = @json($this->dashboardData);</script>

    {{-- ── ApexCharts CDN + Init ──────────────────────────────────────────────── --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
    <script>
    (function () {
        const COLORS = {
            indigo:  '#6366f1',
            emerald: '#10b981',
            amber:   '#f59e0b',
            red:     '#ef4444',
            sky:     '#0ea5e9',
            violet:  '#8b5cf6',
            pink:    '#ec4899',
            teal:    '#14b8a6',
        };
        const PALETTE = Object.values(COLORS);

        const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark'
            || document.documentElement.classList.contains('dark');

        // Text color that's readable on the card background in both themes
        const textColor = () => isDark() ? '#d1d5db' : '#374151';

        const baseOpts = () => ({
            chart: { fontFamily: 'inherit', toolbar: { show: false }, background: 'transparent' },
            theme: { mode: isDark() ? 'dark' : 'light' },
            grid: { borderColor: isDark() ? '#374151' : '#e5e7eb', strokeDashArray: 4 },
            tooltip: { theme: isDark() ? 'dark' : 'light' },
        });

        const legendOpts = () => ({
            show: true,
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '12px',
            labels: { colors: textColor() },
        });

        const rupiah = (v) => 'Rp ' + Intl.NumberFormat('id-ID').format(v);
        const rupiahShort = (v) => {
            if (v >= 1e9) return 'Rp ' + (v / 1e9).toFixed(1).replace('.', ',') + 'M';
            if (v >= 1e6) return 'Rp ' + (v / 1e6).toFixed(0) + 'jt';
            return 'Rp ' + Intl.NumberFormat('id-ID').format(v);
        };

        let charts = {};
        let lastData = null;

        function initOrUpdate(id, opts) {
            const el = document.querySelector('#' + id);
            if (!el) return;
            if (charts[id]) {
                charts[id].updateOptions(opts, true, true);
            } else {
                charts[id] = new ApexCharts(el, opts);
                charts[id].render();
            }
        }

        function buildCharts(d) {
            const tc = textColor();

            // ── Chart 1: Monthly bar ───────────────────────────
            initOrUpdate('chart-monthly', {
                ...baseOpts(),
                chart: { ...baseOpts().chart, type: 'bar', height: 260 },
                series: [{ name: 'Jumlah Pitching', data: d.monthly_counts }],
                xaxis: {
                    categories: d.monthly_labels,
                    labels: { style: { fontSize: '12px', colors: tc }, rotate: 0 },
                    axisBorder: { show: true },
                    axisTicks: { show: true },
                },
                yaxis: { labels: { formatter: v => Math.round(v), style: { colors: tc } } },
                colors: [COLORS.indigo],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
                dataLabels: {
                    enabled: true,
                    formatter: v => v > 0 ? v : '',
                    style: { fontSize: '11px', fontWeight: 600, colors: ['#fff'] },
                    dropShadow: { enabled: false },
                },
                legend: { ...legendOpts() },
            });

            // ── Chart 2: Region horizontal bar ────────────────
            initOrUpdate('chart-region', {
                ...baseOpts(),
                chart: { ...baseOpts().chart, type: 'bar', height: Math.max(240, d.region_labels.length * 56 + 60) },
                series: [{ name: 'Total Nominal', data: d.region_nominals }],
                xaxis: {
                    categories: d.region_labels,
                    labels: { style: { fontSize: '13px', fontWeight: 600, colors: tc } },
                },
                yaxis: { labels: { formatter: rupiahShort, style: { colors: tc } } },
                colors: [COLORS.violet],
                plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
                dataLabels: {
                    enabled: true,
                    formatter: rupiahShort,
                    style: { fontSize: '11px', fontWeight: 600, colors: ['#fff'] },
                    dropShadow: { enabled: false },
                },
                legend: { ...legendOpts() },
                tooltip: {
                    custom: ({ dataPointIndex }) => {
                        var label   = d.region_labels[dataPointIndex] || '';
                        var nominal = rupiah(d.region_nominals[dataPointIndex] || 0);
                        var count   = d.region_counts[dataPointIndex] || 0;
                        return '<div style="padding:8px 12px;font-size:12px;"><b>' + label + '</b><br/>Nominal: ' + nominal + '<br/>Pitching: ' + count + '</div>';
                    },
                },
            });

            // ── Chart 4: Sumber donut ──────────────────────────
            if (d.sumber_counts.length > 0) {
                initOrUpdate('chart-sumber', {
                    ...baseOpts(),
                    chart: { ...baseOpts().chart, type: 'donut', height: 340 },
                    series: d.sumber_counts,
                    labels: d.sumber_labels,
                    colors: PALETTE,
                    legend: {
                        show: true,
                        position: 'bottom',
                        fontSize: '13px',
                        fontWeight: 500,
                        itemMargin: { horizontal: 8, vertical: 6 },
                        labels: { colors: tc },
                        formatter: function (seriesName, opts) {
                            return seriesName + ' (' + d.sumber_percents[opts.seriesIndex] + '%)';
                        },
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function (v, w) { return d.sumber_percents[w.seriesIndex] + '%'; },
                        style: { fontSize: '12px', fontWeight: 600, colors: ['#fff'] },
                        dropShadow: { enabled: false },
                    },
                    tooltip: {
                        y: { formatter: function (v, w) { return v + ' pitching (' + d.sumber_percents[w.seriesIndex] + '%)'; } },
                    },
                    plotOptions: { pie: { donut: { size: '58%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '13px', color: tc, formatter: function () { return d.sumber_counts.reduce(function (a, b) { return a + b; }, 0) + ' pitching'; } } } } } },
                });
            }

            // ── Chart 6: Top Products horizontal bar ──────────
            var prodFull     = d.top_products.map(function (p) { return p.title; });
            var prodShort    = d.top_products.map(function (p) { return p.title.length > 28 ? p.title.slice(0, 28) + '…' : p.title; });
            var prodCounts   = d.top_products.map(function (p) { return p.cnt; });
            var prodNominals = d.top_products.map(function (p) { return p.total_nominal; });
            if (prodShort.length > 0) {
                initOrUpdate('chart-products', {
                    ...baseOpts(),
                    chart: { ...baseOpts().chart, type: 'bar', height: Math.max(260, prodShort.length * 58 + 60) },
                    series: [{ name: 'Frekuensi', data: prodCounts }],
                    xaxis: {
                        categories: prodShort,
                        labels: { style: { fontSize: '12px', fontWeight: 500, colors: tc } },
                        axisBorder: { show: true },
                    },
                    yaxis: { labels: { formatter: function (v) { return Math.round(v); }, style: { colors: tc } } },
                    colors: [COLORS.teal],
                    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
                    legend: { ...legendOpts() },
                    dataLabels: {
                        enabled: true,
                        formatter: function (v, w) { return prodShort[w.dataPointIndex] + '  ' + v + 'x'; },
                        style: { fontSize: '11px', fontWeight: 600, colors: ['#fff'] },
                        textAnchor: 'start',
                        offsetX: 4,
                        dropShadow: { enabled: false },
                    },
                    tooltip: {
                        custom: function (w) {
                            var i = w.dataPointIndex;
                            return '<div style="padding:8px 12px;font-size:12px;max-width:260px;white-space:normal;"><b>' + (prodFull[i] || '') + '</b><br/>Frekuensi: ' + (prodCounts[i] || 0) + 'x<br/>Total Nominal: ' + rupiah(prodNominals[i] || 0) + '</div>';
                        },
                    },
                });
            }
        }

        // ── Init: use server-injected data immediately (fixes first-visit race) ──
        var initData = window.__partnershipDashboard;
        if (initData) {
            lastData = initData;
            buildCharts(initData);
        }

        // ── Listen for filter-update re-dispatches ─────────────────────────────
        document.addEventListener('livewire:init', function () {
            Livewire.on('dashboard-charts-ready', function (payload) {
                var d = Array.isArray(payload) ? payload[0] : payload;
                lastData = d;
                buildCharts(d);
            });
        });

        // ── Rebuild on dark/light theme toggle ─────────────────────────────────
        var themeTimer = null;
        new MutationObserver(function () {
            clearTimeout(themeTimer);
            themeTimer = setTimeout(function () {
                if (lastData) buildCharts(lastData);
            }, 80);
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] });
    })();
    </script>
</div>
