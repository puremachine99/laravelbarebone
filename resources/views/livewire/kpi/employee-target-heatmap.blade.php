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

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
        $this->selectedMonth = (int) now()->month;
        $this->selectedYear = (int) now()->year;
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

    /**
     * Satu baris per metric yang bisa ditarget (leads/ql/pitching/closing) — setiap baris
     * berisi grid harian bulan terpilih, warnanya berdasarkan % capaian ke target harian
     * employee ini untuk metric itu.
     */
    #[Computed]
    public function heatmap(): array
    {
        $service = app(FoTargetMetrics::class);
        $daysInMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->daysInMonth;

        $rows = collect($service->targetableMetrics())->map(function ($metric) use ($service) {
            $counts = $service->dailyCountsForMonth([$this->employee->id], $metric['key'], $this->selectedYear, $this->selectedMonth);
            $dailyTarget = $service->dailyTarget($this->employee->id, $metric['key'], $this->selectedYear);

            $days = [];
            $totalRealisasi = 0;

            foreach ($counts as $day => $count) {
                $percent = $dailyTarget > 0 ? round($count / $dailyTarget * 100) : ($count > 0 ? 100.0 : 0.0);
                $days[$day] = ['count' => $count, 'percent' => $percent];
                $totalRealisasi += $count;
            }

            $monthlyTarget = $dailyTarget * count($counts);
            $monthlyPercent = $monthlyTarget > 0 ? round($totalRealisasi / $monthlyTarget * 100) : ($totalRealisasi > 0 ? 100.0 : 0.0);

            return [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'days' => $days,
                'daily_target' => $dailyTarget,
                'total_realisasi' => $totalRealisasi,
                'monthly_target' => $monthlyTarget,
                'monthly_percent' => $monthlyPercent,
                'monthly_status' => $service->statusFor($monthlyPercent),
            ];
        })->values()->all();

        return [
            'days' => range(1, $daysInMonth),
            'month_label' => Carbon::create($this->selectedYear, $this->selectedMonth, 1)->translatedFormat('F Y'),
            'rows' => $rows,
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
            <table class="border-separate" style="border-spacing: 3px;">
                <thead>
                    <tr>
                        <th class="sticky left-0 bg-base-100 text-left text-xs font-semibold text-base-content/50 pr-3 z-10">{{ __('Metric') }}</th>
                        @foreach ($this->heatmap['days'] as $day)
                            <th class="p-0 text-center text-[10px] text-base-content/40 font-normal w-5">{{ $day }}</th>
                        @endforeach
                        <th class="text-xs font-semibold text-base-content/50 pl-3 text-right">{{ __('Realisasi / Target') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->heatmap['rows'] as $row)
                        <tr>
                            <td class="sticky left-0 bg-base-100 text-xs font-medium text-base-content pr-3 whitespace-nowrap z-10">{{ $row['label'] }}</td>
                            @foreach ($row['days'] as $day => $data)
                                @php
                                    $tier = match (true) {
                                        $data['count'] === 0 => 'bg-base-200',
                                        $data['percent'] >= 100 => 'bg-success',
                                        $data['percent'] >= 50 => 'bg-warning',
                                        default => 'bg-error/70',
                                    };
                                    $textTier = $data['count'] === 0 ? '' : 'text-white';
                                @endphp
                                <td class="p-0 text-center">
                                    <div class="tooltip tooltip-top w-5 h-5 rounded flex items-center justify-center text-[9px] font-semibold leading-none cursor-pointer {{ $tier }} {{ $textTier }}"
                                        data-tip="{{ $row['label'] }} — {{ __('hari :day: :count dari target :target (:percent%)', ['day' => $day, 'count' => $data['count'], 'target' => number_format($row['daily_target'], 1), 'percent' => $data['percent']]) }}">
                                        {{ $data['count'] > 0 ? $data['count'] : '' }}
                                    </div>
                                </td>
                            @endforeach
                            <td class="text-right pl-3 whitespace-nowrap">
                                <span class="text-xs text-base-content/60">{{ number_format($row['total_realisasi']) }} / {{ number_format($row['monthly_target'], 1) }}</span>
                                <span class="badge badge-sm {{ $row['monthly_status']['class'] }} ms-1">{{ $row['monthly_percent'] }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
