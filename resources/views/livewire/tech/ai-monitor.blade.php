@php
    $fmt = fn (?int $value) => number_format((int) $value, 0, ',', '.');
    $usd = fn ($value) => $value === null ? '-' : '$'.number_format((float) $value, 4);
    $stats = $this->stats;
@endphp

<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-base-content">AI Monitor</h1>
            <p class="text-sm text-base-content/60 mt-0.5">
                Pemakaian token & log request AI Center — tanpa isi chat. Log disimpan {{ \App\Models\AiUsageLog::RETENTION_MONTHS }} bulan, ringkasan {{ \App\Models\AiUsageSummary::RETENTION_MONTHS }} bulan.
            </p>
        </div>

        <div role="tablist" class="tabs tabs-boxed w-fit">
            <button type="button" role="tab" wire:click="$set('tab', 'log')" class="tab {{ $tab === 'log' ? 'tab-active' : '' }}">Log</button>
            <button type="button" role="tab" wire:click="$set('tab', 'summary')" class="tab {{ $tab === 'summary' ? 'tab-active' : '' }}">Ringkasan</button>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-base-100 border border-base-300 rounded-box p-4">
            <p class="text-xs text-base-content/60">Request hari ini</p>
            <p class="text-2xl font-bold tabular-nums">{{ $fmt($stats['today_requests']) }}</p>
            <p class="text-xs text-base-content/50">{{ $fmt($stats['today_users']) }} user aktif</p>
        </div>
        <div class="bg-base-100 border border-base-300 rounded-box p-4">
            <p class="text-xs text-base-content/60">Token hari ini</p>
            <p class="text-2xl font-bold tabular-nums">{{ $fmt($stats['today_tokens']) }}</p>
            <p class="text-xs text-base-content/50">Bulan ini: {{ $fmt($stats['month_tokens']) }}</p>
        </div>
        <div class="bg-base-100 border border-base-300 rounded-box p-4">
            <p class="text-xs text-base-content/60">Estimasi biaya bulan ini</p>
            <p class="text-2xl font-bold tabular-nums">{{ $usd($stats['month_cost']) }}</p>
            <p class="text-xs text-base-content/50">
                @if ($stats['budget'])
                    Budget {{ $usd($stats['budget']) }} ({{ number_format(($stats['month_cost'] ?? 0) / $stats['budget'] * 100, 1) }}%)
                @else
                    Budget belum diatur
                @endif
            </p>
        </div>
        <div class="bg-base-100 border border-base-300 rounded-box p-4">
            <p class="text-xs text-base-content/60">Error hari ini</p>
            <p @class(['text-2xl font-bold tabular-nums', 'text-error' => $stats['today_errors'] > 0])>{{ $fmt($stats['today_errors']) }}</p>
            <p class="text-xs text-base-content/50">
                {{ $stats['today_requests'] ? number_format($stats['today_errors'] / $stats['today_requests'] * 100, 1) : '0.0' }}% dari request
            </p>
        </div>
    </div>

    @if ($tab === 'log')
        <div class="flex flex-wrap items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari user / departemen..." class="input input-sm input-bordered w-full sm:w-56" />
            <select wire:model.live="outcome" class="select select-sm select-bordered">
                <option value="">Semua status</option>
                @foreach (\App\Enums\AiUsageOutcome::cases() as $case)
                    <option value="{{ $case->value }}">{{ strtoupper($case->value) }}</option>
                @endforeach
            </select>
            <select wire:model.live="kind" class="select select-sm select-bordered">
                <option value="">Semua jenis</option>
                <option value="{{ \App\Enums\AiUsageKind::Chat->value }}">Chat</option>
                <option value="{{ \App\Enums\AiUsageKind::DataPlan->value }}">Data plan</option>
            </select>
            <input type="date" wire:model.live="date" class="input input-sm input-bordered" />
            <button type="button" wire:click="resetFilters" class="btn btn-sm btn-ghost">Reset</button>
            <label class="label cursor-pointer gap-2 ml-auto">
                <span class="text-sm">Live</span>
                <input type="checkbox" wire:model.live="live" class="toggle toggle-sm toggle-success" />
            </label>
        </div>

        <div class="rounded-box overflow-hidden border border-base-300 shadow-sm">
            <div class="flex items-center gap-2 px-3 py-2 bg-[#161b22] text-[#8b949e] text-xs font-mono">
                <span class="size-2.5 rounded-full bg-[#ff5f56]"></span>
                <span class="size-2.5 rounded-full bg-[#ffbd2e]"></span>
                <span class="size-2.5 rounded-full bg-[#27c93f]"></span>
                <span class="ml-2">nakama-ai — usage.log</span>
                <span class="ml-auto">{{ $this->lines->count() }} baris (maks {{ \App\Livewire\Tech\AiMonitor::LINE_LIMIT }})@if ($live) · live @endif</span>
            </div>

            <div @if ($live) wire:poll.5s @endif
                x-data="{ follow: true }"
                x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight);
                    Livewire.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(() => { if (follow) $el.scrollTop = $el.scrollHeight })))"
                x-on:scroll.debounce.100ms="follow = $el.scrollTop + $el.clientHeight >= $el.scrollHeight - 20"
                class="h-[60vh] overflow-auto bg-[#0d1117] text-[#c9d1d9] font-mono text-xs leading-relaxed p-3">
                @forelse ($this->lines as $line)
                    @php
                        $outcomeClass = match ($line->outcome) {
                            \App\Enums\AiUsageOutcome::Ok => 'text-[#3fb950]',
                            \App\Enums\AiUsageOutcome::Error => 'text-[#f85149]',
                            \App\Enums\AiUsageOutcome::Denied => 'text-[#d29922]',
                            \App\Enums\AiUsageOutcome::Cancelled => 'text-[#8b949e]',
                        };
                    @endphp
                    <div class="whitespace-pre hover:bg-white/5" wire:key="line-{{ $line->id }}"><span class="text-[#8b949e]">[{{ $line->created_at->format('Y-m-d H:i:s') }}]</span> <span class="{{ $outcomeClass }}">{{ str_pad(strtoupper($line->outcome->value), 9) }}</span> <span class="text-[#79c0ff]">{{ str_pad($line->kind === \App\Enums\AiUsageKind::DataPlan ? 'plan' : 'chat', 4) }}</span> user=<span class="text-[#d2a8ff]">{{ $line->user_name ?? '-' }}</span> ({{ $line->department ?? '-' }})@if ($line->model) model={{ $line->model }}@endif @if ($line->outcome === \App\Enums\AiUsageOutcome::Ok) in={{ $fmt($line->prompt_tokens) }} out={{ $fmt($line->output_tokens) }} total=<span class="text-white">{{ $fmt($line->total_tokens) }}</span>@endif @if ($line->error_code) code=<span class="{{ $outcomeClass }}">{{ $line->error_code }}</span>@endif @if ($line->first_token_ms !== null) ttft={{ $fmt($line->first_token_ms) }}ms @endif @if ($line->duration_ms !== null) dur={{ number_format($line->duration_ms / 1000, 1) }}s @endif @if ($line->cost_usd !== null) <span class="text-[#e3b341]">{{ $usd($line->cost_usd) }}</span>@endif</div>
                @empty
                    <div class="text-[#8b949e]">$ belum ada log yang cocok dengan filter.</div>
                @endforelse
                <div class="text-[#3fb950]">$ <span class="animate-pulse">▌</span></div>
            </div>
        </div>
    @else
        <div class="flex items-center gap-2">
            <div role="tablist" class="tabs tabs-boxed tabs-sm w-fit">
                <button type="button" role="tab" wire:click="$set('period', 'weekly')" class="tab {{ $period !== 'monthly' ? 'tab-active' : '' }}">Mingguan</button>
                <button type="button" role="tab" wire:click="$set('period', 'monthly')" class="tab {{ $period === 'monthly' ? 'tab-active' : '' }}">Bulanan</button>
            </div>
            <p class="text-xs text-base-content/50">Dibuat otomatis tiap Senin (mingguan) dan tanggal 1 (bulanan).</p>
        </div>

        <div class="bg-base-100 border border-base-300 rounded-box overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th class="text-right">Request</th>
                        <th class="text-right">OK / Error / Ditolak / Batal</th>
                        <th class="text-right">User</th>
                        <th class="text-right">Token (in / out)</th>
                        <th class="text-right">Total token</th>
                        <th class="text-right">Biaya</th>
                        <th class="text-right">Rata-rata TTFT / durasi</th>
                        <th>Top departemen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->summaries as $summary)
                        <tr wire:key="summary-{{ $summary->id }}">
                            <td class="whitespace-nowrap">
                                {{ $period === 'monthly' ? $summary->period_start->translatedFormat('F Y') : $summary->period_start->format('d M').' – '.$summary->period_end->format('d M Y') }}
                            </td>
                            <td class="text-right tabular-nums">{{ $fmt($summary->requests) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">
                                <span class="text-success">{{ $fmt($summary->ok_count) }}</span> /
                                <span class="text-error">{{ $fmt($summary->error_count) }}</span> /
                                <span class="text-warning">{{ $fmt($summary->denied_count) }}</span> /
                                {{ $fmt($summary->cancelled_count) }}
                            </td>
                            <td class="text-right tabular-nums">{{ $fmt($summary->active_users) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ $fmt($summary->prompt_tokens) }} / {{ $fmt($summary->output_tokens) }}</td>
                            <td class="text-right tabular-nums font-semibold">{{ $fmt($summary->total_tokens) }}</td>
                            <td class="text-right tabular-nums">{{ $usd($summary->cost_usd) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">
                                {{ $summary->avg_first_token_ms !== null ? $fmt($summary->avg_first_token_ms).'ms' : '-' }} /
                                {{ $summary->avg_duration_ms !== null ? number_format($summary->avg_duration_ms / 1000, 1).'s' : '-' }}
                            </td>
                            <td class="text-xs">
                                @foreach (array_slice($summary->breakdown['by_department'] ?? [], 0, 3) as $department)
                                    <div class="whitespace-nowrap">{{ $department['name'] }}: {{ $fmt($department['total_tokens']) }}</div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-base-content/50 py-6">Belum ada ringkasan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
