<?php

use App\Models\Employee;
use App\Models\FoTarget;
use App\Services\FoTargetMetrics;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Employee $employee;

    public int $selectedMonth;

    public int $selectedYear;

    public string $selectedMetric = 'leads';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
        $this->selectedMonth = (int) now()->month;
        $this->selectedYear = (int) now()->year;
    }

    #[Computed]
    public function metrics(): array
    {
        return app(FoTargetMetrics::class)->targetableMetrics();
    }

    #[Computed]
    public function hasTargets(): bool
    {
        return FoTarget::where('employee_id', $this->employee->id)->exists();
    }

    #[Computed]
    public function tahunOptions(): array
    {
        $currentYear = (int) now()->year;

        return collect(range($currentYear, $currentYear - 2))
            ->map(fn ($year) => ['id' => $year, 'name' => (string) $year])
            ->all();
    }

    #[Computed]
    public function heatmap(): array
    {
        $service = app(FoTargetMetrics::class);

        $counts = $service->dailyCountsForMonth([$this->employee->id], $this->selectedMetric, $this->selectedYear, $this->selectedMonth);
        $dailyTarget = $service->dailyTarget($this->employee->id, $this->selectedMetric, $this->selectedYear);

        $days = [];
        $totalRealisasi = 0;

        foreach ($counts as $day => $count) {
            $percent = $dailyTarget > 0 ? round($count / $dailyTarget * 100) : ($count > 0 ? 100.0 : 0.0);

            $days[$day] = [
                'count' => $count,
                'percent' => $percent,
                'status' => $service->statusFor($percent),
            ];

            $totalRealisasi += $count;
        }

        $daysInMonth = count($counts);
        $monthlyTarget = $dailyTarget * $daysInMonth;
        $monthlyPercent = $monthlyTarget > 0 ? round($totalRealisasi / $monthlyTarget * 100) : ($totalRealisasi > 0 ? 100.0 : 0.0);

        return [
            'days' => $days,
            'month_label' => Carbon::create($this->selectedYear, $this->selectedMonth, 1)->translatedFormat('F Y'),
            'daily_target' => $dailyTarget,
            'total_realisasi' => $totalRealisasi,
            'monthly_target' => $monthlyTarget,
            'monthly_percent' => $monthlyPercent,
            'monthly_status' => $service->statusFor($monthlyPercent),
        ];
    }
};

?>

<div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-base-content">{{ __('KPI Funding') }}</h3>
            <p class="text-xs text-base-content/50 mt-1">{{ __('Capaian harian vs target, diambil otomatis dari Pipeline Funding.') }}</p>
        </div>

        @if ($this->hasTargets)
            <div class="flex flex-wrap items-end gap-2">
                <div class="w-40">
                    <x-select label="{{ __('Metric') }}" wire:model.live="selectedMetric" :options="$this->metrics" option-value="key" option-label="label" class="select-sm" />
                </div>
                <div class="w-32">
                    <x-select label="{{ __('Bulan') }}" wire:model.live="selectedMonth" :options="[
                        ['id' => 1, 'name' => __('Januari')], ['id' => 2, 'name' => __('Februari')], ['id' => 3, 'name' => __('Maret')],
                        ['id' => 4, 'name' => __('April')], ['id' => 5, 'name' => __('Mei')], ['id' => 6, 'name' => __('Juni')],
                        ['id' => 7, 'name' => __('Juli')], ['id' => 8, 'name' => __('Agustus')], ['id' => 9, 'name' => __('September')],
                        ['id' => 10, 'name' => __('Oktober')], ['id' => 11, 'name' => __('November')], ['id' => 12, 'name' => __('Desember')],
                    ]" class="select-sm" />
                </div>
                <div class="w-24">
                    <x-select label="{{ __('Tahun') }}" wire:model.live="selectedYear" :options="$this->tahunOptions" class="select-sm" />
                </div>
            </div>
        @endif
    </div>

    @if (! $this->hasTargets)
        <p class="text-sm text-base-content/50 mt-4">{{ __('Belum ada target KPI Funding yang di-set untuk karyawan ini.') }}</p>
    @else
        <div class="mt-4 overflow-x-auto">
            <div class="flex gap-1 flex-wrap">
                @foreach ($this->heatmap['days'] as $day => $data)
                    @php
                        $tier = match (true) {
                            $data['count'] === 0 => 'bg-base-200',
                            $data['percent'] >= 100 => 'bg-success',
                            $data['percent'] >= 50 => 'bg-warning',
                            default => 'bg-error/70',
                        };
                        $textTier = $data['count'] === 0 ? '' : 'text-white';
                    @endphp
                    <div class="w-6 h-6 rounded flex items-center justify-center text-[9px] font-semibold leading-none {{ $tier }} {{ $textTier }}"
                        title="{{ __('Hari :day: :count (:percent%)', ['day' => $day, 'count' => $data['count'], 'percent' => $data['percent']]) }}">
                        {{ $day }}
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-3 mt-4 text-sm">
            <span class="text-base-content/70">
                {{ __(':label — :realisasi dari target :target (:percent%)', [
                    'label' => $this->heatmap['month_label'],
                    'realisasi' => number_format($this->heatmap['total_realisasi']),
                    'target' => number_format($this->heatmap['monthly_target'], 1),
                    'percent' => $this->heatmap['monthly_percent'],
                ]) }}
            </span>
            <span class="badge badge-sm {{ $this->heatmap['monthly_status']['class'] }}">{{ $this->heatmap['monthly_status']['label'] }}</span>
        </div>
    @endif
</div>
