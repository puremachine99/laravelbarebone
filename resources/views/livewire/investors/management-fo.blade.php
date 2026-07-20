<?php

use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;
use function Livewire\Volt\uses;

layout('layouts.app');
uses(\Mary\Traits\Toast::class);

state([
    'activePeriod' => 'tahunan',
    'targetOverrides' => [],
    'targetSettingEmployeeId' => null,
]);

$metricsData = [
    ['key' => 'leads', 'label' => 'Leads', 'icon' => 'o-user-plus', 'type' => 'count'],
    ['key' => 'ql', 'label' => 'QL', 'icon' => 'o-check-badge', 'type' => 'count'],
    ['key' => 'pitching', 'label' => 'Pitching', 'icon' => 'o-presentation-chart-line', 'type' => 'count'],
    ['key' => 'closing', 'label' => 'Closing', 'icon' => 'o-trophy', 'type' => 'count'],
    ['key' => 'nilai_closing', 'label' => 'Nilai Closing', 'icon' => 'o-banknotes', 'type' => 'currency'],
];

state(['metrics' => $metricsData]);

// Halaman ini khusus Admin FO (Investor Admin) / Super Admin — FO biasa gak boleh lihat/atur target orang lain.
mount(function () {
    abort_unless(auth()->user()->hasRole(['Investor Admin', 'Super Admin']), 403);

    $this->targetSettingEmployeeId = $this->foEmployees[0]['id'] ?? null;
});

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

// Belum ada tabel target beneran — angka target ini digenerate deterministik (stabil tiap reload, seeded dari
// employee id) biar UI-nya bisa dites dulu. Ganti dengan query beneran begitu skema `fo_targets` dibuatkan.
// Realisasi & Proyeksi di bawah ini SUDAH dari data funnel asli (bukan dummy).
$dummyYearlyTarget = function ($employeeId, $metricKey) {
    mt_srand($employeeId * 31 + crc32($metricKey));

    return match ($metricKey) {
        'leads' => mt_rand(80, 160),
        'ql' => mt_rand(40, 90),
        'pitching' => mt_rand(20, 60),
        'closing' => mt_rand(6, 20),
        'nilai_closing' => mt_rand(500, 3000) * 1_000_000,
        default => 0,
    };
};

// Rekomendasi otomatis: tahunan dibagi rata ke bulanan (÷12), bulanan dibagi ke harian (÷25 hari kerja/bulan).
$splitTarget = function ($yearly) {
    return [
        'tahunan' => $yearly,
        'bulanan' => $yearly / 12,
        'harian' => $yearly / 12 / 25,
    ];
};

$yearlyTargetFor = function ($employeeId, $metricKey) {
    return $this->targetOverrides[$employeeId][$metricKey]
        ?? $this->dummyYearlyTarget($employeeId, $metricKey);
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

// Realisasi = data funnel yang beneran sudah terjadi di periode ini, milik FO tersebut.
$realisasiFor = function ($employeeId, $metricKey) {
    [$start, $end] = $this->periodRange();

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

// Dummy dulu — belum nulis ke database, cuma disimpan di state komponen (hilang kalau halaman di-reload).
$updateYearlyTarget = function ($employeeId, $metricKey, $value) {
    if (! is_numeric($value) || $value < 0) {
        $this->error('Nilai target gak valid.');

        return;
    }

    $this->targetOverrides[$employeeId][$metricKey] = (float) $value;
    $this->success('Target tahunan diperbarui (data uji coba, belum tersimpan permanen).');
};

?>

<div class="space-y-4">
    <div class="card bg-base-100 border border-base-300 p-4 rounded-xl">
        <h2 class="text-lg font-bold text-base-content">Management FO</h2>
        <p class="text-xs text-base-content/50">
            Atur & pantau target kerja Funding Officer — target tahunan otomatis dipecah ke bulanan & harian.
        </p>
        <div class="badge badge-warning badge-outline badge-sm mt-1.5">Target manual · Realisasi & Proyeksi dari data funnel asli</div>
    </div>

    @php $fmtRupiah = function ($value) {
        if (! $value) {
            return 'Rp 0';
        }
        if ($value >= 1_000_000_000) {
            return 'Rp '.rtrim(rtrim(number_format($value / 1_000_000_000, 1), '0'), '.').' M';
        }

        return 'Rp '.rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.').' Jt';
    }; @endphp

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

            <div class="space-y-3">
                @foreach ($metrics as $metric)
                    <x-input
                        :label="$metric['label']"
                        type="number"
                        value="{{ $this->yearlyTargetFor($targetSettingEmployeeId, $metric['key']) }}"
                        wire:change="updateYearlyTarget({{ $targetSettingEmployeeId }}, '{{ $metric['key'] }}', $event.target.value)"
                        class="input-sm"
                    />
                @endforeach
            </div>
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
                                    $target = $this->targetFor($fo['id'], $metric['key']);
                                    $proyeksi = $hasProyeksi ? $this->proyeksiFor($fo['id'], $metric['key']) : null;
                                    $realisasi = $this->realisasiFor($fo['id'], $metric['key']);
                                    $gap = $realisasi - $target;
                                    $fmt = fn ($v) => $metric['type'] === 'currency' ? $fmtRupiah($v) : number_format($v, $activePeriod === 'harian' ? 1 : 0);
                                @endphp
                                <div class="flex items-center justify-between gap-1 text-xs">
                                    <span class="text-base-content/50 shrink-0">{{ $metric['label'] }}</span>
                                    <span class="text-right">
                                        <span class="font-medium {{ $gap >= 0 ? 'text-success' : 'text-error' }}">{{ $fmt($realisasi) }}</span>
                                        @if ($hasProyeksi)
                                            <span class="text-warning">({{ $fmt($proyeksi) }})</span>
                                        @endif
                                        <span class="text-base-content/30">/ {{ $fmt($target) }}</span>
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
                            $fmtTotal = fn ($v) => $metric['type'] === 'currency' ? $fmtRupiah($v) : number_format($v, 0);
                        @endphp
                        <div>
                            <span class="text-base-content/50">{{ $metric['label'] }}</span>
                            <span class="font-semibold text-base-content ms-1">{{ $fmtTotal($totalRealisasi) }}</span>
                            <span class="text-base-content/40">/ {{ $fmtTotal($totalTarget) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
