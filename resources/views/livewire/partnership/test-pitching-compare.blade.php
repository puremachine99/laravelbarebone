<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};

layout('layouts.app');
usesPagination();

state(['perPage' => 10]);
$updatedPerPage = function () {
    $this->resetPage('ppage');
    $this->resetPage('spage');
};

state([
    'search'     => '',
    'filterYear' => '2026',
    'filterPmo'  => '',
    'panel'      => 'both', // 'both' | 'partnership' | 'smartboard'
]);

$partnershipRows = computed(function () {
    return \Illuminate\Support\Facades\DB::connection('mysql_partnership')
        ->table('rencana_pitchings')
        ->leftJoin('users', 'users.id', '=', 'rencana_pitchings.created_by')
        ->leftJoin('kontaks', 'kontaks.id', '=', 'rencana_pitchings.kontak_id')
        ->leftJoin('instansis', 'instansis.id', '=', 'rencana_pitchings.opd_id')
        ->select(
            'rencana_pitchings.id',
            'rencana_pitchings.tahun',
            'rencana_pitchings.proyeksi_potensi',
            'rencana_pitchings.sumber_proyeksi',
            'rencana_pitchings.anggaran_proyeksi',
            'rencana_pitchings.tanggal_pitching',
            'rencana_pitchings.metode',
            'rencana_pitchings.hasil_pitching',
            'rencana_pitchings.alasan_negative',
            'rencana_pitchings.status_pelaksanaan',
            'rencana_pitchings.is_verified',
            'users.nama as pmo_name',
            'kontaks.kontak_nama as kontak_name',
            'instansis.instansi as instansi_name'
        )
        ->whereNull('rencana_pitchings.deleted_at')
        ->where('rencana_pitchings.tahun', $this->filterYear)
        ->when($this->filterPmo, fn ($q) => $q->where('users.nama', 'like', '%' . $this->filterPmo . '%'))
        ->when($this->search, fn ($q) => $q->where(function ($sub) {
            $sub->where('instansis.instansi', 'like', '%' . $this->search . '%')
                ->orWhere('kontaks.kontak_nama', 'like', '%' . $this->search . '%')
                ->orWhere('rencana_pitchings.proyeksi_potensi', 'like', '%' . $this->search . '%');
        }))
        ->orderByDesc('rencana_pitchings.tanggal_pitching')
        ->paginate(\App\Support\PerPage::resolve($this->perPage), ['*'], 'ppage');
});

$smartboardRows = computed(function () {
    return \App\Models\Card::forPitching()
        ->where('fiscal_year', (int) $this->filterYear)
        ->when($this->filterPmo, fn ($q) => $q->whereHas('employee.user', fn ($u) => $u->where('name', 'ilike', '%' . $this->filterPmo . '%')))
        ->when($this->search, fn ($q) => $q->where(function ($sub) {
            $sub->where('title', 'ilike', '%' . $this->search . '%')
                ->orWhereHas('contact', fn ($c) => $c->where('name', 'ilike', '%' . $this->search . '%')
                    ->orWhereHas('opd', fn ($o) => $o->where('name', 'ilike', '%' . $this->search . '%')));
        }))
        ->with(['stage', 'contact.opd', 'employee.user'])
        ->orderByDesc('pitching_date')
        ->paginate(\App\Support\PerPage::resolve($this->perPage), ['*'], 'spage');
});

$statusBadge = function (string $status): string {
    return match ($status) {
        'won'  => 'badge-success text-white',
        'hot'  => 'bg-orange-500 text-white border-orange-500',
        'warm' => 'bg-blue-500 text-white border-blue-500',
        'lost' => 'badge-error text-white',
        default => 'badge-ghost',
    };
};

$pelaksanaanBadge = function (string $status): string {
    return match ($status) {
        'terlaksana'       => 'badge-success text-white',
        'tidak_terlaksana' => 'badge-error text-white',
        default            => 'badge-info text-white',
    };
};

new class {};
?>

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-base-content">Perbandingan Data Pitching</h2>
            <p class="text-sm text-base-content/50 mt-0.5">
                Kiri: <span class="font-semibold text-orange-500">Partnership App</span> &nbsp;|&nbsp;
                Kanan: <span class="font-semibold text-indigo-500">Smartboard</span>
            </p>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <span class="badge badge-outline badge-sm">TEST PAGE</span>
        </div>
    </div>

    {{-- Global Filters --}}
    <div class="card bg-base-100 border border-base-300 rounded-xl px-5 py-4">
        <div class="flex flex-wrap gap-4 items-end">
            <div class="w-28">
                <x-select label="Tahun" wire:model.live="filterYear" :options="[
                    ['id' => '2025', 'name' => '2025'],
                    ['id' => '2026', 'name' => '2026'],
                ]" />
            </div>
            <div class="w-52">
                <x-input label="Filter PMO" wire:model.live.debounce.400ms="filterPmo" placeholder="Nama PMO..." icon="o-user" />
            </div>
            <div class="flex-1 min-w-52">
                <x-input label="Cari Instansi / Kontak / Produk" wire:model.live.debounce.400ms="search" placeholder="Ketik untuk cari..." icon="o-magnifying-glass" />
            </div>
            <x-button label="Reset" wire:click="$set('search', ''); $set('filterPmo', '')" icon="o-arrow-path" class="btn-ghost btn-sm" />
        </div>
    </div>

    {{-- Side by Side --}}
    <div class="grid grid-cols-2 gap-4">

        {{-- LEFT: Partnership App --}}
        <div class="card bg-base-100 border-2 border-orange-300 dark:border-orange-700 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-orange-50 dark:bg-orange-950/30 border-b border-orange-200 dark:border-orange-800 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-orange-700 dark:text-orange-400 text-sm">Partnership App (DB Lama)</h3>
                    <p class="text-[11px] text-orange-500/70">rencana_pitchings</p>
                </div>
                <span class="badge badge-sm bg-orange-500 text-white border-orange-500">
                    {{ number_format($this->partnershipRows->total()) }} rows
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-xs table-zebra w-full">
                    <thead>
                        <tr class="text-[10px] text-base-content/50 border-orange-100 dark:border-orange-900">
                            <th>PMO / Instansi / Kontak</th>
                            <th>Produk</th>
                            <th class="text-right">Anggaran</th>
                            <th>Tgl</th>
                            <th>Status</th>
                            <th>Verif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->partnershipRows as $row)
                            <tr class="border-orange-100 dark:border-orange-900/30 hover:bg-orange-50/50 dark:hover:bg-orange-950/20">
                                <td>
                                    <div class="text-[11px] font-semibold text-base-content leading-tight">{{ $row->pmo_name ?? '-' }}</div>
                                    <div class="text-[10px] text-base-content/60 leading-tight">{{ Str::limit($row->instansi_name ?? '-', 30) }}</div>
                                    <div class="text-[10px] text-base-content/40 leading-tight italic">{{ $row->kontak_name ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="text-[11px] text-base-content/80">{{ Str::limit($row->proyeksi_potensi ?? '-', 25) }}</span>
                                    <div class="text-[10px] text-base-content/40">{{ $row->sumber_proyeksi }}</div>
                                </td>
                                <td class="text-right">
                                    <span class="text-[11px] font-semibold text-orange-600 dark:text-orange-400">
                                        {{ $row->anggaran_proyeksi ? 'Rp ' . number_format($row->anggaran_proyeksi, 0, ',', '.') : '-' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-[10px]">{{ $row->tanggal_pitching ? \Carbon\Carbon::parse($row->tanggal_pitching)->format('d/m/Y') : '-' }}</span>
                                    <div class="text-[9px] text-base-content/40">{{ $row->metode }}</div>
                                </td>
                                <td>
                                    @php
                                        $pelaksanaan = $row->status_pelaksanaan ?? 'rencana';
                                        $hasilRaw = $row->hasil_pitching;
                                    @endphp
                                    <span class="badge badge-xs rounded font-semibold {{ $this->pelaksanaanBadge($pelaksanaan) }}">
                                        {{ str_replace('_', ' ', ucfirst($pelaksanaan)) }}
                                    </span>
                                    @if ($hasilRaw)
                                        <div>
                                            <span class="badge badge-xs rounded font-semibold mt-0.5
                                                {{ $hasilRaw === 'positive' ? 'badge-success text-white' : ($hasilRaw === 'negative' ? 'badge-error text-white' : 'badge-ghost') }}">
                                                {{ ucfirst($hasilRaw) }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($row->is_verified)
                                        <x-icon name="o-check-circle" class="w-4 h-4 text-green-500 inline-block" />
                                    @else
                                        <span class="text-[10px] text-base-content/30">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-6 text-xs text-base-content/40">Tidak ada data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-orange-100 dark:border-orange-900">
                <x-pagination-footer :paginator="$this->partnershipRows" />
            </div>
        </div>

        {{-- RIGHT: Smartboard --}}
        <div class="card bg-base-100 border-2 border-indigo-300 dark:border-indigo-700 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-indigo-50 dark:bg-indigo-950/30 border-b border-indigo-200 dark:border-indigo-800 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-indigo-700 dark:text-indigo-400 text-sm">Smartboard (DB Baru)</h3>
                    <p class="text-[11px] text-indigo-500/70">cards (forPitching)</p>
                </div>
                <span class="badge badge-sm bg-indigo-500 text-white border-indigo-500">
                    {{ number_format($this->smartboardRows->total()) }} rows
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-xs table-zebra w-full">
                    <thead>
                        <tr class="text-[10px] text-base-content/50 border-indigo-100 dark:border-indigo-900">
                            <th>PMO / Instansi / Kontak</th>
                            <th>Produk</th>
                            <th class="text-right">Anggaran</th>
                            <th>Tgl</th>
                            <th>Status</th>
                            <th>Verif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->smartboardRows as $card)
                            <tr class="border-indigo-100 dark:border-indigo-900/30 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20">
                                <td>
                                    <div class="text-[11px] font-semibold text-base-content leading-tight">{{ $card->employee?->user?->name ?? '-' }}</div>
                                    <div class="text-[10px] text-base-content/60 leading-tight">{{ Str::limit($card->contact?->opd?->name ?? '-', 30) }}</div>
                                    <div class="text-[10px] text-base-content/40 leading-tight italic">{{ $card->contact?->name ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="text-[11px] text-base-content/80">{{ Str::limit($card->title, 25) }}</span>
                                    <div class="text-[10px] text-base-content/40">{{ $card->sumber_proyeksi ?? '-' }}</div>
                                </td>
                                <td class="text-right">
                                    <span class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">
                                        Rp {{ number_format($card->nominal_value, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-[10px]">{{ $card->pitching_date ? $card->pitching_date->format('d/m/Y') : '-' }}</span>
                                    <div class="text-[9px] text-base-content/40">{{ $card->communication_method ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="badge badge-xs rounded font-semibold {{ match($card->stage?->stage_name) {
                                        'Terlaksana'       => 'badge-success text-white',
                                        'Tidak Terlaksana' => 'badge-error text-white',
                                        default            => 'badge-info text-white',
                                    } }}">
                                        {{ $card->stage?->stage_name ?? 'Rencana' }}
                                    </span>
                                    @if (in_array($card->status, ['won','hot','warm','lost']))
                                        <div>
                                            <span class="badge badge-xs rounded font-semibold mt-0.5 {{ $this->statusBadge($card->status) }}">
                                                {{ strtoupper($card->status) }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($card->manager_verified)
                                        <x-icon name="o-check-circle" class="w-4 h-4 text-green-500 inline-block" />
                                    @else
                                        <span class="text-[10px] text-base-content/30">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-6 text-xs text-base-content/40">Tidak ada data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-indigo-100 dark:border-indigo-900">
                <x-pagination-footer :paginator="$this->smartboardRows" />
            </div>
        </div>
    </div>

    {{-- Row count summary --}}
    <div class="flex items-center justify-center gap-6 text-xs text-base-content/50 pb-4">
        <span>
            <span class="font-semibold text-orange-500">{{ number_format($this->partnershipRows->total()) }}</span>
            data di Partnership App
        </span>
        <span class="text-base-content/20">vs</span>
        <span>
            <span class="font-semibold text-indigo-500">{{ number_format($this->smartboardRows->total()) }}</span>
            data di Smartboard
        </span>
        @if ($this->partnershipRows->total() !== $this->smartboardRows->total())
            <span class="badge badge-warning badge-sm">
                Selisih {{ number_format(abs($this->partnershipRows->total() - $this->smartboardRows->total())) }} rows
            </span>
        @else
            <span class="badge badge-success badge-sm text-white">Jumlah sama</span>
        @endif
    </div>
</div>
