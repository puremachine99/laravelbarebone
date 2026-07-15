<?php

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\uses;

layout('layouts.app');
uses([\Mary\Traits\Toast::class, \Livewire\WithFileUploads::class]);

$stagesData = [
    ['key' => 'leads', 'label' => 'Leads', 'badge' => 'badge-neutral'],
    ['key' => 'ql', 'label' => 'QL', 'badge' => 'badge-info'],
    ['key' => 'pitching', 'label' => 'Pitching', 'badge' => 'badge-warning'],
    ['key' => 'followup', 'label' => 'Follow Up', 'badge' => 'badge-secondary'],
    ['key' => 'won', 'label' => 'Won', 'badge' => 'badge-success'],
    ['key' => 'lost', 'label' => 'Lost', 'badge' => 'badge-error'],
    ['key' => 'akad', 'label' => 'Akad', 'badge' => 'badge-primary'],
];

state([
    'stages' => $stagesData,
    'search' => '',
    'filterFo' => '',
    'filterBulan' => '',
    'filterTahun' => '',
    'historyFunnelId' => null,

    'akadFunnelId' => null,
    'akadTanggal' => '',
    'akadNominal' => null,
    'akadFile' => null,

    'bulanOptions' => [
        ['id' => 1, 'name' => 'Januari'], ['id' => 2, 'name' => 'Februari'], ['id' => 3, 'name' => 'Maret'],
        ['id' => 4, 'name' => 'April'], ['id' => 5, 'name' => 'Mei'], ['id' => 6, 'name' => 'Juni'],
        ['id' => 7, 'name' => 'Juli'], ['id' => 8, 'name' => 'Agustus'], ['id' => 9, 'name' => 'September'],
        ['id' => 10, 'name' => 'Oktober'], ['id' => 11, 'name' => 'November'], ['id' => 12, 'name' => 'Desember'],
    ],

    'sumberOptions' => [
        ['id' => 'Murni', 'name' => 'Murni'],
        ['id' => 'EXPFO', 'name' => 'EXPFO'],
        ['id' => 'Referral', 'name' => 'Referral'],
        ['id' => 'SIDS', 'name' => 'SIDS'],
    ],
]);

// Admin FO (Investor Admin / Super Admin) sees everyone's funnel entries. A plain FO only ever sees their own.
$isAdminFo = computed(fn () => auth()->user()->hasRole(['Investor Admin', 'Super Admin']));

$currentEmployee = computed(fn () => Employee::with('user')->where('user_id', auth()->id())->first());

// Contacts owned by the logged-in FO — used to pick which contact a lead entry is created for.
$contactOptions = computed(function () {
    $employeeId = $this->currentEmployee?->id;

    return FoContact::query()
        ->where('employee_id', $employeeId)
        ->orderBy('nama')
        ->get(['id', 'nama', 'profesi'])
        ->toArray();
});

$foOptions = computed(function () {
    return Employee::with('user')
        ->whereHas('department', fn ($q) => $q->where('name', 'Departemen Finance'))
        ->whereHas('user.roles', fn ($q) => $q->whereIn('name', ['Funding Officer', 'Investor Admin']))
        ->get()
        ->map(fn ($e) => ['id' => $e->id, 'name' => $e->user?->name])
        ->values()
        ->all();
});

$tahunOptions = computed(function () {
    return FoFunnel::query()
        ->whereNotNull('proyeksi_close_date')
        ->get(['proyeksi_close_date'])
        ->pluck('proyeksi_close_date')
        ->map(fn ($date) => $date->year)
        ->unique()
        ->sortDesc()
        ->map(fn ($year) => ['id' => $year, 'name' => (string) $year])
        ->values()
        ->all();
});

$funnels = computed(function () {
    $isAdmin = $this->isAdminFo;
    $ownEmployeeId = $this->currentEmployee?->id;
    $search = trim(strtolower($this->search));

    return FoFunnel::query()
        ->with(['contact', 'employee.user', 'activities'])
        ->when(! $isAdmin, fn ($q) => $q->where('employee_id', $ownEmployeeId))
        ->when($isAdmin && $this->filterFo, fn ($q) => $q->where('employee_id', $this->filterFo))
        ->orderByDesc('created_at')
        ->get()
        ->filter(function ($funnel) use ($search) {
            if ($this->filterBulan && (int) optional($funnel->proyeksi_close_date)->month !== (int) $this->filterBulan) {
                return false;
            }

            if ($this->filterTahun && (int) optional($funnel->proyeksi_close_date)->year !== (int) $this->filterTahun) {
                return false;
            }

            if ($search !== '') {
                $haystack = collect([
                    $funnel->contact?->nama,
                    $funnel->contact?->profesi,
                    $funnel->contact?->no_telepon,
                    $funnel->employee?->user?->name,
                    $funnel->sumber,
                    $funnel->jangka_waktu,
                    $funnel->bagi_hasil,
                    $funnel->tier,
                ])->filter()->map(fn ($v) => strtolower($v))->implode(' ');

                if (! str_contains($haystack, $search)) {
                    return false;
                }
            }

            return true;
        })
        ->values();
});

$stageSum = function ($stage) {
    return (float) $this->funnels->where('stage', $stage)->sum('proyeksi_amount');
};

$historyFunnel = computed(function () {
    if (! $this->historyFunnelId) {
        return null;
    }

    $funnel = FoFunnel::with([
        'contact',
        'activities' => fn ($q) => $q->where('stage', 'followup')->orderByDesc('tanggal'),
    ])->find($this->historyFunnelId);

    if (! $funnel) {
        return null;
    }

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    return $funnel;
});

$resetFilters = function () {
    $this->search = '';
    $this->filterFo = '';
    $this->filterBulan = '';
    $this->filterTahun = '';
};

$createLead = function ($contactId, $proyeksiCloseDate, $proyeksiAmount, $sumber) {
    $contactQuery = FoContact::query()->where('id', $contactId);

    if (! $this->isAdminFo) {
        $contactQuery->where('employee_id', $this->currentEmployee?->id);
    }

    $contact = $contactQuery->first();

    if (! $contact) {
        abort(403);
    }

    // Kolom proyeksi_amount = numeric(15,2) — nominal (Rupiah penuh) tidak boleh melebihi ini.
    if ($proyeksiAmount && $proyeksiAmount > 9_999_999_999_999) {
        $this->error('Nominal proyeksi terlalu besar', 'Maksimal Rp 9.999.999.999.999.');

        return;
    }

    FoFunnel::create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $contact->employee_id,
        'stage' => 'leads',
        'sumber' => $sumber,
        'tgl_leads' => now()->toDateString(),
        'proyeksi_close_date' => $proyeksiCloseDate ?: null,
        'proyeksi_amount' => $proyeksiAmount ?: null,
    ]);

    $this->success('Lead berhasil ditambahkan!');
};

$updateLead = function ($funnelId, $proyeksiCloseDate, $proyeksiAmount, $sumber) {
    $funnel = FoFunnel::findOrFail($funnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    // Kolom proyeksi_amount = numeric(15,2) — nominal (Rupiah penuh) tidak boleh melebihi ini.
    if ($proyeksiAmount && $proyeksiAmount > 9_999_999_999_999) {
        $this->error('Nominal proyeksi terlalu besar', 'Maksimal Rp 9.999.999.999.999.');

        return;
    }

    $funnel->update([
        'proyeksi_close_date' => $proyeksiCloseDate ?: null,
        'proyeksi_amount' => $proyeksiAmount ?: null,
        'sumber' => $sumber,
    ]);

    $this->success('Data funnel berhasil diperbarui.');
};

$deleteFunnel = function ($funnelId) {
    $funnel = FoFunnel::findOrFail($funnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $funnel->delete();

    $this->error('Data funnel berhasil dihapus.');
};

// Akad menandakan lead yang sudah Won benar-benar sampai ke tahap tanda tangan akad —
// cuma Admin FO / Super Admin yang boleh memindahkan kartu ke sana, tapi tetap kelihatan oleh role lain.
$moveStage = function ($funnelId, $stage) {
    $funnel = FoFunnel::findOrFail($funnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    if ($stage === 'akad' && ! $this->isAdminFo) {
        abort(403);
    }

    $funnel->update(['stage' => $stage]);
};

$openAkadModal = function ($funnelId) {
    if (! $this->isAdminFo) {
        abort(403);
    }

    $this->akadFunnelId = $funnelId;
    $this->akadTanggal = '';
    $this->akadNominal = null;
    $this->akadFile = null;
    $this->resetValidation();
};

// Bersihkan pesan error "required" begitu file baru dipilih, biar gak nyangkut dari percobaan submit sebelumnya.
$updatedAkadFile = function () {
    $this->resetErrorBag('akadFile');
};

// Dipicu drag-drop ke kolom Akad. Simpan tanggal akad, nominal, dan dokumen (unggah ke bucket S3/MinIO
// lewat disk "s3" — kredensialnya di .env, lihat config/filesystems.php).
$submitAkad = function () {
    if (! $this->isAdminFo) {
        abort(403);
    }

    $funnel = FoFunnel::findOrFail($this->akadFunnelId);

    $validated = $this->validate([
        'akadTanggal' => ['required', 'date'],
        'akadNominal' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
        'akadFile' => ['required', 'file', 'mimes:doc,docx,pdf', 'max:5120'],
    ]);

    $path = $this->akadFile->store('funnel-akad', 's3');

    $funnel->update([
        'stage' => 'akad',
        'tgl_closing' => $validated['akadTanggal'],
        'nominal_investasi' => $validated['akadNominal'],
        'dokumen_akad_path' => $path,
        'dokumen_akad_nama' => $this->akadFile->getClientOriginalName(),
    ]);

    $this->reset(['akadFunnelId', 'akadTanggal', 'akadNominal', 'akadFile']);
    $this->success('Proses akad berhasil dicatat!');
    $this->js("document.getElementById('funnel-akad-modal').close()");
};

// Pindah ke kolom Pitching selalu lewat sini — mencatat tanggal & catatan sesi pitching sebagai histori.
$logPitchingAndMove = function ($funnelId, $tanggal, $catatan) {
    $funnel = FoFunnel::findOrFail($funnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $funnel->update(['stage' => 'pitching']);

    FoFunnelActivity::create([
        'fo_funnel_id' => $funnel->id,
        'employee_id' => $this->currentEmployee?->id,
        'stage' => 'pitching',
        'tanggal' => $tanggal ?: now()->toDateString(),
        'catatan' => $catatan ?: null,
    ]);

    $this->success('Sesi pitching berhasil dicatat.');
};

// Follow up bisa dicatat berkali-kali untuk funnel yang sama.
// $moveStage: true ketika dipicu drag-drop ke kolom Follow Up, false saat menambah histori dari sidebar
// (funnel yang sudah lanjut ke tahap lain — mis. Won — tidak boleh mundur ke Follow Up cuma karena dicatat).
$logFollowUp = function ($funnelId, $tanggal, $catatan, $moveStage = false) {
    $funnel = FoFunnel::findOrFail($funnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    if ($moveStage) {
        $funnel->update(['stage' => 'followup']);
    }

    FoFunnelActivity::create([
        'fo_funnel_id' => $funnel->id,
        'employee_id' => $this->currentEmployee?->id,
        'stage' => 'followup',
        'tanggal' => $tanggal ?: now()->toDateString(),
        'catatan' => $catatan ?: null,
    ]);

    $this->success('Follow up berhasil dicatat.');
};

$openHistory = function ($funnelId) {
    $funnel = FoFunnel::findOrFail($funnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $this->historyFunnelId = $funnelId;
};

$closeHistory = function () {
    $this->historyFunnelId = null;
};

?>

<div class="space-y-6" x-data="funnelUi(@js($this->contactOptions), @js($this->isAdminFo))">
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <h2 class="text-xl font-bold text-base-content">Funnel</h2>
        <p class="text-sm text-base-content/50 mt-1">
            Tahapan funnel investor/mitra funding — geser kartu antar kolom untuk mengubah tahap.
        </p>
    </div>

    <!-- Filter Panel -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div class="shrink-0">
                <div class="fieldset-legend mb-0.5 invisible">&nbsp;</div>
                <x-button label="Tambah Leads" icon="o-plus" class="btn-primary btn-sm" x-on:click="showCreate()" />
            </div>

            @if ($this->isAdminFo)
                <div class="w-full sm:w-56">
                    <x-select label="Funding Officer" wire:model.live="filterFo" :options="$this->foOptions" placeholder="Semua FO" placeholder-value="" />
                </div>
            @endif
            <div class="w-full sm:w-44">
                <x-select label="Bulan" wire:model.live="filterBulan" :options="$bulanOptions" placeholder="Semua Bulan" placeholder-value="" />
            </div>
            <div class="w-full sm:w-32">
                <x-select label="Tahun" wire:model.live="filterTahun" :options="$this->tahunOptions" placeholder="Semua Tahun" placeholder-value="" />
            </div>
            <div class="shrink-0">
                <div class="fieldset-legend mb-0.5 invisible">&nbsp;</div>
                <x-button label="Reset" icon="o-x-mark" class="btn-ghost btn-sm" wire:click="resetFilters" />
            </div>

            <div class="w-full sm:w-72 sm:ms-auto">
                <x-input label="Cari" wire:model.live.debounce.300ms="search" placeholder="Nama, profesi, no HP, PIC, sumber..." icon="o-magnifying-glass" clearable />
            </div>
        </div>
    </div>

    <!-- View Toggle -->
    <div class="flex justify-end">
        <div class="join">
            <button type="button" class="btn btn-sm btn-square join-item" :class="viewMode === 'kanban' && 'btn-active'" x-on:click="viewMode = 'kanban'" title="Kanban">
                <x-icon name="o-view-columns" class="w-4 h-4" />
            </button>
            <button type="button" class="btn btn-sm btn-square join-item" :class="viewMode === 'table' && 'btn-active'" x-on:click="viewMode = 'table'" title="Table">
                <x-icon name="o-table-cells" class="w-4 h-4" />
            </button>
        </div>
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

    <!-- Kanban Mode -->
    <div x-show="viewMode === 'kanban'" class="flex gap-4 overflow-x-auto pb-2">
        @foreach ($stages as $stage)
            <div
                class="w-72 shrink-0 bg-base-200 rounded-2xl p-3 flex flex-col gap-3 transition-colors"
                x-on:dragover.prevent="dragOverStage = ('{{ $stage['key'] }}' === 'akad' && !isAdminFo) ? null : '{{ $stage['key'] }}'"
                x-on:dragleave="dragOverStage = null"
                x-on:drop="onDrop('{{ $stage['key'] }}')"
                :class="dragOverStage === '{{ $stage['key'] }}' && 'ring-2 ring-primary/50 bg-primary/5'"
            >
                <div class="flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <span class="{{ $stage['badge'] }} badge badge-sm">{{ $stage['label'] }}</span>
                        @if ($stage['key'] === 'akad' && ! $this->isAdminFo)
                            <div class="tooltip" data-tip="Hanya Admin FO / Super Admin yang bisa memindahkan ke sini">
                                <x-icon name="o-lock-closed" class="w-3 h-3 text-base-content/30" />
                            </div>
                        @endif
                        <span class="text-xs font-semibold text-base-content/40">{{ $this->funnels->where('stage', $stage['key'])->count() }}</span>
                    </div>
                    <span class="text-xs font-bold text-primary">{{ $fmtRupiah($this->stageSum($stage['key'])) }}</span>
                </div>

                <div class="flex flex-col gap-2 min-h-[80px]">
                    @forelse ($this->funnels->where('stage', $stage['key']) as $funnel)
                        <div
                            draggable="true"
                            x-on:dragstart="onDragStart({{ $funnel->id }}, '{{ $funnel->stage }}')"
                            x-on:dragend="onDragEnd()"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 scale-90 -translate-y-2"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            :class="{
                                'opacity-40': draggingId === {{ $funnel->id }},
                                'ring-2 ring-primary/60 shadow-lg': justMovedId === {{ $funnel->id }},
                            }"
                            class="relative card bg-base-100 border border-base-300 shadow-sm p-3 space-y-2 cursor-grab active:cursor-grabbing hover:shadow-md transition-all duration-300"
                        >
                            <div class="dropdown dropdown-end absolute top-1 right-1">
                                <div tabindex="0" role="button" draggable="false" x-on:mousedown.stop x-on:click.stop class="btn btn-ghost btn-xs btn-circle">
                                    <x-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                                </div>
                                <ul tabindex="0" class="dropdown-content menu menu-sm bg-base-100 rounded-box shadow-lg border border-base-300 w-32 z-20">
                                    <li><a x-on:click="editCard({{ $funnel->id }}, '{{ $funnel->proyeksi_close_date?->format('Y-m-d') }}', {{ $funnel->proyeksi_amount ?? 'null' }}, '{{ $funnel->sumber }}')">Edit</a></li>
                                    <li><a class="text-error" x-on:click="deleteCard({{ $funnel->id }})">Hapus</a></li>
                                </ul>
                            </div>

                            <div class="font-semibold text-sm text-base-content pr-5">{{ $funnel->contact?->nama }}</div>
                            <div class="text-xs text-base-content/50 truncate">{{ $funnel->contact?->profesi }}</div>

                            <div class="flex items-center justify-between pt-1">
                                <span class="badge badge-sm badge-ghost font-semibold">{{ $fmtRupiah($funnel->proyeksi_amount) }}</span>
                                <span class="text-[11px] text-base-content/50 truncate max-w-[110px]" title="{{ $funnel->employee?->user?->name }}">{{ $funnel->employee?->user?->name }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] text-base-content/40">{{ $funnel->proyeksi_close_date?->format('d M Y') ?? '-' }}</span>
                                <span class="badge badge-xs badge-outline">{{ $funnel->sumber }}</span>
                            </div>
                            @if ($funnel->stage === 'akad' && $funnel->dokumen_akad_path)
                                <a
                                    href="{{ $funnel->dokumenAkadUrl() }}"
                                    target="_blank"
                                    class="badge badge-sm badge-primary badge-outline w-full truncate"
                                    x-on:mousedown.stop
                                    x-on:click.stop
                                    title="{{ $funnel->dokumen_akad_nama }}"
                                >
                                    <x-icon name="o-paper-clip" class="w-3 h-3" /> {{ $funnel->dokumen_akad_nama }}
                                </a>
                            @endif
                            @php $fuCount = $funnel->activities->where('stage', 'followup')->count(); @endphp
                            @if ($fuCount > 0)
                                <div class="tooltip" data-tip="Tampilkan detail">
                                    <button
                                        type="button"
                                        class="badge badge-sm badge-secondary w-full"
                                        wire:click="openHistory({{ $funnel->id }})"
                                        x-on:mousedown.stop
                                        x-on:click.stop
                                    >
                                        {{ $fuCount }}x Follow Up
                                    </button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-xs text-base-content/30 text-center py-6 border-2 border-dashed border-base-300 rounded-xl">
                            Tidak ada kartu
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <!-- Table Mode -->
    <div x-show="viewMode === 'table'" class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>Nama</th>
                        <th>Profesi / Institusi</th>
                        <th>Proyeksi Amount</th>
                        <th>PIC (FO)</th>
                        <th>Sumber</th>
                        <th>Tahap</th>
                        <th>Proyeksi Close</th>
                        <th>Follow Up</th>
                        <th>Dokumen Akad</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->funnels as $funnel)
                        <tr class="border-b border-base-300 hover:bg-base-200">
                            <td class="font-semibold text-base-content py-3">{{ $funnel->contact?->nama }}</td>
                            <td class="max-w-[220px] truncate">{{ $funnel->contact?->profesi }}</td>
                            <td>{{ $fmtRupiah($funnel->proyeksi_amount) }}</td>
                            <td>{{ $funnel->employee?->user?->name }}</td>
                            <td>{{ $funnel->sumber }}</td>
                            <td>
                                @php $meta = collect($stages)->firstWhere('key', $funnel->stage); @endphp
                                <span class="badge badge-sm {{ $meta['badge'] ?? 'badge-neutral' }}">{{ $meta['label'] ?? $funnel->stage }}</span>
                            </td>
                            <td>{{ $funnel->proyeksi_close_date?->format('d M Y') ?? '-' }}</td>
                            <td>
                                @php $fuCountTable = $funnel->activities->where('stage', 'followup')->count(); @endphp
                                @if ($fuCountTable > 0)
                                    <div class="tooltip" data-tip="Tampilkan detail">
                                        <button type="button" class="badge badge-sm badge-secondary" wire:click="openHistory({{ $funnel->id }})">
                                            {{ $fuCountTable }}x
                                        </button>
                                    </div>
                                @else
                                    <span class="text-base-content/30">-</span>
                                @endif
                            </td>
                            <td>
                                @if ($funnel->dokumen_akad_path)
                                    <a href="{{ $funnel->dokumenAkadUrl() }}" target="_blank" class="link link-primary text-xs" title="{{ $funnel->dokumen_akad_nama }}">
                                        <x-icon name="o-paper-clip" class="w-3 h-3 inline" /> Lihat
                                    </a>
                                @else
                                    <span class="text-base-content/30">-</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex gap-2 justify-end">
                                    <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" x-on:click="editCard({{ $funnel->id }}, '{{ $funnel->proyeksi_close_date?->format('Y-m-d') }}', {{ $funnel->proyeksi_amount ?? 'null' }}, '{{ $funnel->sumber }}')" />
                                    <x-button icon="o-trash" class="btn-ghost btn-xs text-error" x-on:click="deleteCard({{ $funnel->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-base-content/50 py-8">
                                Belum ada data funnel.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Lead Modal -->
    <x-modal id="funnel-add-modal" title="Tambah Leads" without-trap-focus>
        <form x-on:submit.prevent="submitCreate()" class="space-y-4">
            <div class="relative" x-on:click.outside="contactDropdownOpen = false">
                <x-input
                    label="Kontak"
                    placeholder="Cari nama kontak milikmu..."
                    x-model="contactSearchText"
                    x-on:focus="contactDropdownOpen = true"
                    x-on:input="contactDropdownOpen = true; addForm.contactId = null"
                    icon="o-magnifying-glass"
                    autocomplete="off"
                    required
                />

                <div x-show="contactDropdownOpen" x-cloak class="absolute z-30 mt-1 w-full max-h-56 overflow-y-auto bg-base-100 border border-base-300 rounded-lg shadow-lg">
                    <template x-for="contact in filteredContactOptions()" :key="contact.id">
                        <div class="px-3 py-2 hover:bg-base-200 cursor-pointer" x-on:click="selectContact(contact)">
                            <div class="text-sm font-medium text-base-content" x-text="contact.nama"></div>
                            <div class="text-xs text-base-content/50" x-text="contact.profesi || '-'"></div>
                        </div>
                    </template>

                    <template x-if="filteredContactOptions().length === 0">
                        <div class="px-3 py-2 text-xs text-base-content/40">
                            Tidak ada kontak ditemukan. Tambahkan dulu di halaman Contacts.
                        </div>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input label="Proyeksi Close Date" type="date" x-model="addForm.tanggal" />
                <x-input label="Proyeksi Amount (Rp)" type="number" max="9999999999999" x-model="addForm.proyeksiAmount" placeholder="Cth: 250000000" hint="Isi nominal penuh, cth: 250000000 untuk Rp 250 Juta" />
            </div>

            <x-select label="Sumber" x-model="addForm.sumber" :options="$sumberOptions" placeholder="Pilih Sumber" required />

            <div class="modal-action">
                <form method="dialog">
                    <x-button label="Batal" class="btn-ghost" />
                </form>
                <x-button label="Simpan" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Edit Modal -->
    <x-modal id="funnel-edit-modal" title="Edit Data Funnel" without-trap-focus>
        <form x-on:submit.prevent="submitEdit()" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input label="Proyeksi Close Date" type="date" x-model="editForm.tanggal" />
                <x-input label="Proyeksi Amount (Rp)" type="number" max="9999999999999" x-model="editForm.proyeksiAmount" placeholder="Cth: 250000000" hint="Isi nominal penuh, cth: 250000000 untuk Rp 250 Juta" />
            </div>

            <x-select label="Sumber" x-model="editForm.sumber" :options="$sumberOptions" placeholder="Pilih Sumber" required />

            <div class="modal-action">
                <form method="dialog">
                    <x-button label="Batal" class="btn-ghost" />
                </form>
                <x-button label="Simpan" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Pitching Log Modal -->
    <x-modal id="funnel-pitch-modal" title="Catat Sesi Pitching" subtitle="Kartu ini akan pindah ke kolom Pitching setelah dicatat." without-trap-focus>
        <form x-on:submit.prevent="submitPitch()" class="space-y-4">
            <div>
                <div class="flex items-center justify-between mb-0.5">
                    <span class="fieldset-legend text-sm font-medium">Tanggal Pitching</span>
                    <x-button label="Hari Ini" icon="o-calendar-days" class="btn-ghost btn-xs" x-on:click="setPitchToday()" />
                </div>
                <x-input type="date" x-model="pitchForm.tanggal" required />
            </div>

            <x-textarea label="Catatan Kegiatan" x-model="pitchForm.catatan" placeholder="Ringkasan kegiatan pitching hari ini..." rows="4" />

            <div class="modal-action">
                <form method="dialog">
                    <x-button label="Batal" class="btn-ghost" />
                </form>
                <x-button label="Simpan" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Follow Up Log Modal -->
    <x-modal id="funnel-followup-modal" title="Catat Follow Up" subtitle="Follow up bisa dicatat berkali-kali untuk funnel yang sama." without-trap-focus>
        <form x-on:submit.prevent="submitFollowUp()" class="space-y-4">
            <div>
                <div class="flex items-center justify-between mb-0.5">
                    <span class="fieldset-legend text-sm font-medium">Tanggal Follow Up</span>
                    <x-button label="Hari Ini" icon="o-calendar-days" class="btn-ghost btn-xs" x-on:click="setFollowUpToday()" />
                </div>
                <x-input type="date" x-model="followUpForm.tanggal" required />
            </div>

            <x-textarea label="Catatan" x-model="followUpForm.catatan" placeholder="Ringkasan hasil follow up..." rows="4" />

            <div class="modal-action">
                <form method="dialog">
                    <x-button label="Batal" class="btn-ghost" />
                </form>
                <x-button label="Simpan" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Akad Modal (Admin FO / Super Admin only) -->
    <x-modal id="funnel-akad-modal" title="Proses Akad" subtitle="Kartu ini akan pindah ke kolom Akad setelah dokumen tersimpan." without-trap-focus wire:ignore.self>
        <form wire:submit="submitAkad" class="space-y-4">
            <div>
                <div class="flex items-center justify-between mb-0.5">
                    <span class="fieldset-legend text-sm font-medium">Tanggal Akad</span>
                    <x-button label="Hari Ini" icon="o-calendar-days" class="btn-ghost btn-xs" x-on:click="setAkadToday()" />
                </div>
                <x-input type="date" wire:model="akadTanggal" required />
            </div>

            <x-input label="Nominal Investasi (Rp)" type="number" max="9999999999999" wire:model="akadNominal" placeholder="Cth: 250000000" hint="Isi nominal penuh, cth: 250000000 untuk Rp 250 Juta" required />

            <x-file label="Dokumen Akad" wire:model="akadFile" accept=".doc,.docx,.pdf" hint="Format DOC, DOCX, atau PDF. Maksimal 5MB." required />

            <div class="modal-action">
                <form method="dialog">
                    <x-button label="Batal" class="btn-ghost" />
                </form>
                <x-button label="Simpan" type="submit" class="btn-primary" spinner="submitAkad" />
            </div>
        </form>
    </x-modal>

    <!-- Follow Up History Sidebar -->
    <div
        x-show="$wire.historyFunnelId"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-40 w-full sm:w-96 bg-base-100 border-l border-base-300 shadow-xl overflow-y-auto"
    >
        @if ($this->historyFunnel)
            <div class="p-6 space-y-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-bold text-lg text-base-content">{{ $this->historyFunnel->contact?->nama }}</h3>
                        <p class="text-xs text-base-content/50">Histori Follow Up</p>
                    </div>
                    <x-button icon="o-x-mark" class="btn-ghost btn-sm btn-circle" wire:click="closeHistory" />
                </div>

                <x-button label="Tambah Follow Up" icon="o-plus" class="btn-primary btn-sm w-full" x-on:click="addFollowUpFromHistory()" />

                <div class="space-y-3">
                    @forelse ($this->historyFunnel->activities as $activity)
                        <div class="card bg-base-200 p-3 space-y-1">
                            <div class="text-sm font-semibold text-base-content">{{ $activity->tanggal->format('d M Y') }}</div>
                            <div class="text-sm text-base-content/70">{{ $activity->catatan ?? 'Tidak ada catatan.' }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-base-content/40 text-center py-8">Belum ada follow up tercatat.</div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    <!-- History Sidebar Backdrop -->
    <div
        x-show="$wire.historyFunnelId"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-30 bg-black/30"
        wire:click="closeHistory"
    ></div>
</div>

@script
<script>
    Alpine.data('funnelUi', (contacts, isAdminFo) => ({
        viewMode: 'kanban',
        draggingId: null,
        draggingStage: null,
        dragOverStage: null,
        justMovedId: null,
        isAdminFo: isAdminFo,

        contacts: contacts,
        contactSearchText: '',
        contactDropdownOpen: false,

        addForm: { contactId: null, tanggal: '', proyeksiAmount: '', sumber: '' },
        editForm: { id: null, tanggal: '', proyeksiAmount: '', sumber: '' },
        pitchForm: { funnelId: null, tanggal: '', catatan: '' },
        followUpForm: { funnelId: null, tanggal: '', catatan: '' },
        followUpMovesStage: false,

        filteredContactOptions() {
            const search = this.contactSearchText.toLowerCase();

            return this.contacts.filter((contact) =>
                contact.nama.toLowerCase().includes(search)
                || (contact.profesi ?? '').toLowerCase().includes(search)
            );
        },

        selectContact(contact) {
            this.addForm.contactId = contact.id;
            this.contactSearchText = contact.nama;
            this.contactDropdownOpen = false;
        },

        showCreate() {
            this.addForm = { contactId: null, tanggal: '', proyeksiAmount: '', sumber: '' };
            this.contactSearchText = '';
            document.getElementById('funnel-add-modal').showModal();
        },

        submitCreate() {
            if (!this.addForm.contactId || !this.addForm.sumber) {
                return;
            }

            $wire.createLead(this.addForm.contactId, this.addForm.tanggal, this.addForm.proyeksiAmount, this.addForm.sumber);
            document.getElementById('funnel-add-modal').close();
        },

        editCard(id, tanggal, proyeksiAmount, sumber) {
            this.editForm = { id, tanggal: tanggal ?? '', proyeksiAmount: proyeksiAmount ?? '', sumber };
            document.getElementById('funnel-edit-modal').showModal();
        },

        submitEdit() {
            $wire.updateLead(this.editForm.id, this.editForm.tanggal, this.editForm.proyeksiAmount, this.editForm.sumber);
            document.getElementById('funnel-edit-modal').close();
        },

        deleteCard(id) {
            if (confirm('Hapus data funnel ini?')) {
                $wire.deleteFunnel(id);
            }
        },

        onDragStart(id, stage) {
            this.draggingId = id;
            this.draggingStage = stage;
        },

        onDragEnd() {
            this.draggingId = null;
            this.dragOverStage = null;
        },

        onDrop(stage) {
            if (!this.draggingId || this.draggingStage === stage) {
                this.draggingId = null;
                this.dragOverStage = null;
                return;
            }

            if (stage === 'akad' && !this.isAdminFo) {
                alert('Hanya Admin FO / Super Admin yang bisa memindahkan kartu ke Akad.');
                this.draggingId = null;
                this.dragOverStage = null;
                return;
            }

            if (stage === 'pitching') {
                this.pitchForm = { funnelId: this.draggingId, tanggal: '', catatan: '' };
                document.getElementById('funnel-pitch-modal').showModal();
            } else if (stage === 'followup') {
                this.followUpForm = { funnelId: this.draggingId, tanggal: '', catatan: '' };
                this.followUpMovesStage = true;
                document.getElementById('funnel-followup-modal').showModal();
            } else if (stage === 'akad') {
                $wire.openAkadModal(this.draggingId).then(() => {
                    document.getElementById('funnel-akad-modal').showModal();
                });
            } else {
                $wire.moveStage(this.draggingId, stage);
            }

            this.draggingId = null;
            this.dragOverStage = null;
        },

        setPitchToday() {
            this.pitchForm.tanggal = new Date().toISOString().slice(0, 10);
        },

        submitPitch() {
            if (!this.pitchForm.tanggal) {
                return;
            }

            $wire.logPitchingAndMove(this.pitchForm.funnelId, this.pitchForm.tanggal, this.pitchForm.catatan);
            document.getElementById('funnel-pitch-modal').close();
        },

        addFollowUpFromHistory() {
            this.followUpForm = { funnelId: this.$wire.historyFunnelId, tanggal: '', catatan: '' };
            this.followUpMovesStage = false;
            document.getElementById('funnel-followup-modal').showModal();
        },

        setFollowUpToday() {
            this.followUpForm.tanggal = new Date().toISOString().slice(0, 10);
        },

        setAkadToday() {
            $wire.set('akadTanggal', new Date().toISOString().slice(0, 10));
        },

        submitFollowUp() {
            if (!this.followUpForm.tanggal) {
                return;
            }

            $wire.logFollowUp(this.followUpForm.funnelId, this.followUpForm.tanggal, this.followUpForm.catatan, this.followUpMovesStage);
            document.getElementById('funnel-followup-modal').close();
        },
    }));
</script>
@endscript
