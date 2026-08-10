<?php

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoFunnelActivityLog;
use App\Notifications\FundingDealAkadNotification;
use App\Notifications\FundingDealClosedNotification;
use App\Notifications\FundingDealLostNotification;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;

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
    ['key' => 'closing', 'label' => 'Closing', 'badge' => 'badge-accent'],
    ['key' => 'akad', 'label' => 'Akad', 'badge' => 'badge-primary'],
];

state([
    'stages' => $stagesData,
    'viewMode' => 'kanban',
    'search' => '',
    'filterFo' => '',
    'filterBulan' => '',
    'filterTahun' => '',
    'historyFunnelId' => null,
    'logActivityId' => null,
    'showDeletedFollowUps' => false,

    'akadFunnelId' => null,
    'akadTanggal' => '',
    'akadNominal' => null,
    'akadFile' => null,

    'wonFunnelId' => null,
    'wonTanggal' => '',
    'wonNominal' => null,

    'closingFunnelId' => null,
    'closingTanggal' => '',
    'closingNominal' => null,

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

$isAdminFo = computed(fn () => auth()->user()->hasRole(['Investor Admin', 'Super Admin']));

$currentEmployee = computed(fn () => Employee::with('user')->where('user_id', auth()->id())->first());

$contactOptions = computed(function () {
    $employeeId = $this->currentEmployee?->id;

    return Cache::remember("fo-funnel:contact-options:{$employeeId}", 60, function () use ($employeeId) {
        return FoContact::query()
            ->where('employee_id', $employeeId)
            ->orderBy('nama')
            ->get(['id', 'nama', 'profesi'])
            ->toArray();
    });
});

// Daftar Funding Officer buat dropdown filter Admin — sama buat semua orang, jarang berubah.
$foOptions = computed(function () {
    return Cache::remember('fo-funnel:fo-options', 60, function () {
        return Employee::with('user')
            ->whereHas('department', fn ($q) => $q->where('name', 'Departemen Finance'))
            ->whereHas('user.roles', fn ($q) => $q->whereIn('name', ['Funding Officer', 'Investor Admin']))
            ->get()
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->user?->name])
            ->values()
            ->all();
    });
});

$tahunOptions = computed(function () {
    return Cache::remember('fo-funnel:tahun-options', 60, function () {
        // EXTRACT(YEAR ...) itu sintaks Postgres/MySQL; SQLite (dipakai di test) butuh strftime.
        $yearExpression = FoFunnel::query()->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', proyeksi_close_date) AS INTEGER)"
            : 'CAST(EXTRACT(YEAR FROM proyeksi_close_date) AS INTEGER)';

        return FoFunnel::query()
            ->whereNotNull('proyeksi_close_date')
            ->selectRaw("{$yearExpression} as year")
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => ['id' => (int) $year, 'name' => (string) (int) $year])
            ->values()
            ->all();
    });
});

$funnels = computed(function () {
    $isAdmin = $this->isAdminFo;
    $ownEmployeeId = $this->currentEmployee?->id;
    $search = trim(strtolower($this->search));

    return FoFunnel::query()
        ->with(['contact', 'employee.user'])
        ->withCount(['activities as followup_count' => fn ($q) => $q->where('stage', 'followup')])
        // Lead yang kontaknya udah dihapus jangan muncul lagi di kolom Leads — kartu tanpa identitas
        // gak ada gunanya di tahap paling awal. Stage lain dibiarkan apa adanya.
        ->where(fn ($q) => $q->where('stage', '!=', 'leads')->orWhereHas('contact'))
        ->when(! $isAdmin, fn ($q) => $q->where('employee_id', $ownEmployeeId))
        ->when($isAdmin && $this->filterFo, fn ($q) => $q->where('employee_id', $this->filterFo))
        ->when($this->filterBulan, fn ($q) => $q->whereMonth('proyeksi_close_date', (int) $this->filterBulan))
        ->when($this->filterTahun, fn ($q) => $q->whereYear('proyeksi_close_date', (int) $this->filterTahun))
        ->when($search !== '', function ($q) use ($search) {
            $like = "%{$search}%";

            $q->where(function ($q2) use ($like) {
                $q2->whereHas('contact', function ($cq) use ($like) {
                    $cq->whereRaw('LOWER(nama) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(profesi) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(no_telepon) LIKE ?', [$like]);
                })
                    ->orWhereHas('employee.user', fn ($uq) => $uq->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereRaw('LOWER(sumber) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(jangka_waktu) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(bagi_hasil) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(tier) LIKE ?', [$like]);
            });
        })
        ->orderByDesc('created_at')
        ->get();
});

$stageSum = function ($stage) {
    return (float) $this->funnels->where('stage', $stage)->sum(function ($funnel) {
        if (in_array($funnel->stage, FoFunnel::CLOSED_STAGES, true) && $funnel->nominal_investasi) {
            return $funnel->nominal_investasi;
        }

        if ($funnel->stage === 'won' && $funnel->nominal_won) {
            return $funnel->nominal_won;
        }

        return $funnel->proyeksi_amount;
    });
};

$historyFunnel = computed(function () {
    if (! $this->historyFunnelId) {
        return null;
    }

    $funnel = FoFunnel::with([
        'contact',
        'activities' => fn ($q) => $q->where('stage', 'followup')->withCount('logs')->orderByDesc('tanggal'),
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

    $proyeksiAmount = Money::normalize($proyeksiAmount);

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

    $proyeksiAmount = Money::normalize($proyeksiAmount);

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


$updatedAkadFile = function () {
    $this->resetErrorBag('akadFile');
};

$submitAkad = function () {
    if (! $this->isAdminFo) {
        abort(403);
    }

    $funnel = FoFunnel::findOrFail($this->akadFunnelId);

    $this->akadNominal = Money::normalize($this->akadNominal);

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

    FundingDealAkadNotification::sendToOwner($funnel);

    $this->reset(['akadFunnelId', 'akadTanggal', 'akadNominal', 'akadFile']);
    $this->success('Proses akad berhasil dicatat!');
    $this->js("document.getElementById('funnel-akad-modal').close()");
};

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

// Won = komitmen verbal calon investor. Nominalnya dicatat terpisah dari Closing (nominal_won vs
// nominal_investasi) karena angkanya bisa berubah pas uang beneran cair — biar bisa dibandingkan.
$openWonModal = function ($funnelId) {
    $funnel = FoFunnel::findOrFail($funnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $this->wonFunnelId = $funnelId;
    $this->wonTanggal = '';
    $this->wonNominal = null;
    $this->resetValidation();
};

$submitWon = function () {
    $funnel = FoFunnel::findOrFail($this->wonFunnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $this->wonNominal = Money::normalize($this->wonNominal);

    $validated = $this->validate([
        'wonTanggal' => ['required', 'date'],
        'wonNominal' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
    ]);

    $funnel->update([
        'stage' => 'won',
        'tgl_won' => $validated['wonTanggal'],
        'nominal_won' => $validated['wonNominal'],
    ]);

    $this->reset(['wonFunnelId', 'wonTanggal', 'wonNominal']);
    $this->success('Komitmen (Won) berhasil dicatat!');
    $this->js("document.getElementById('funnel-won-modal').close()");
};

// Closing = uang investor beneran sudah masuk PT. Nominal & tanggalnya final, dicatat lagi di sini
// (bukan cuma dicopy dari Won) supaya kalau ada selisih dari nominal komitmen, ketahuan.
$openClosingModal = function ($funnelId) {
    $funnel = FoFunnel::findOrFail($funnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $this->closingFunnelId = $funnelId;
    $this->closingTanggal = '';
    $this->closingNominal = null;
    $this->resetValidation();
};

$submitClosing = function () {
    $funnel = FoFunnel::findOrFail($this->closingFunnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $this->closingNominal = Money::normalize($this->closingNominal);

    $validated = $this->validate([
        'closingTanggal' => ['required', 'date'],
        'closingNominal' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
    ]);

    $funnel->update([
        'stage' => 'closing',
        'status_closing' => 'closing',
        'tgl_closing' => $validated['closingTanggal'],
        'nominal_investasi' => $validated['closingNominal'],
    ]);

    FundingDealClosedNotification::sendToAdmins($funnel);
    FundingDealClosedNotification::sendToOwner($funnel);

    $this->reset(['closingFunnelId', 'closingTanggal', 'closingNominal']);
    $this->success('Data closing berhasil dicatat!');
    $this->js("document.getElementById('funnel-closing-modal').close()");
};

$logLostAndMove = function ($funnelId, $tanggal, $reason) {
    $funnel = FoFunnel::findOrFail($funnelId);

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $funnel->update([
        'stage' => 'lost',
        'status_closing' => 'lost',
        'tgl_closing' => $tanggal ?: now()->toDateString(),
        'lost_reason' => $reason ?: null,
    ]);

    FundingDealLostNotification::sendToOwner($funnel);

    $this->success('Data Lost berhasil dicatat.');
};

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

// Ubah follow up yang sudah tercatat — setiap perubahan disimpan ke fo_funnel_activity_logs
// (nilai sebelum & sesudah, siapa yang ubah) biar ada jejak audit, bukan cuma overwrite diam-diam.
$updateFollowUp = function ($activityId, $tanggal, $catatan) {
    $activity = FoFunnelActivity::with('funnel')->findOrFail($activityId);
    $funnel = $activity->funnel;

    if (! $this->isAdminFo && $funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $newTanggal = $tanggal ?: now()->toDateString();
    $newCatatan = $catatan ?: null;

    if ($activity->tanggal->toDateString() === $newTanggal && $activity->catatan === $newCatatan) {
        $this->success('Gak ada perubahan buat disimpan.');

        return;
    }

    FoFunnelActivityLog::create([
        'fo_funnel_activity_id' => $activity->id,
        'changed_by_user_id' => auth()->id(),
        'old_tanggal' => $activity->tanggal,
        'new_tanggal' => $newTanggal,
        'old_catatan' => $activity->catatan,
        'new_catatan' => $newCatatan,
    ]);

    $activity->update([
        'tanggal' => $newTanggal,
        'catatan' => $newCatatan,
    ]);

    $this->success('Follow up berhasil diperbarui.');
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
    $this->showDeletedFollowUps = false;
};

$deleteFollowUp = function ($activityId) {
    $activity = FoFunnelActivity::with('funnel')->findOrFail($activityId);

    if (! $this->isAdminFo && $activity->funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $activity->delete();

    $this->success('Follow up dihapus. Bisa dipulihkan lewat "Lihat Terhapus".');
};

$restoreFollowUp = function ($activityId) {
    $activity = FoFunnelActivity::withTrashed()->with('funnel')->findOrFail($activityId);

    if (! $this->isAdminFo && $activity->funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $activity->restore();

    $this->success('Follow up berhasil dipulihkan.');
};

$toggleDeletedFollowUps = function () {
    $this->showDeletedFollowUps = ! $this->showDeletedFollowUps;
};

$deletedFollowUps = computed(function () {
    if (! $this->historyFunnelId || ! $this->showDeletedFollowUps) {
        return collect();
    }

    return FoFunnelActivity::onlyTrashed()
        ->where('fo_funnel_id', $this->historyFunnelId)
        ->where('stage', 'followup')
        ->orderByDesc('deleted_at')
        ->get();
});

// NOTE: Livewire/Volt's single-file compiler locates the end of the component by finding the
// last "}" immediately followed by ";" in this script — a plain closure assignment satisfies
// that, but a trailing computed(fn () => ...) call (ending in "});") does not. Keep a plain
// "$foo = function () {...};" statement (not a computed()) as the LAST statement in this block,
// or the compiler mis-injects its view() method mid-closure and the whole page 500s on a fresh
// compile. See git history around 2026-08-10 for the same bug hitting management-fo.blade.php.
$activityLogs = computed(function () {
    if (! $this->logActivityId) {
        return collect();
    }

    return FoFunnelActivityLog::with('changedBy')
        ->where('fo_funnel_activity_id', $this->logActivityId)
        ->latest('id')
        ->get();
});

$openActivityLog = function ($activityId) {
    $activity = FoFunnelActivity::withTrashed()->with('funnel')->findOrFail($activityId);

    if (! $this->isAdminFo && $activity->funnel->employee_id !== $this->currentEmployee?->id) {
        abort(403);
    }

    $this->logActivityId = $activityId;
};

$closeActivityLog = function () {
    $this->logActivityId = null;
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
            <button type="button" class="btn btn-sm btn-square join-item {{ $viewMode === 'kanban' ? 'btn-active' : '' }}" wire:click="$set('viewMode', 'kanban')" title="Kanban">
                <x-icon name="o-view-columns" class="w-4 h-4" />
            </button>
            <button type="button" class="btn btn-sm btn-square join-item {{ $viewMode === 'table' ? 'btn-active' : '' }}" wire:click="$set('viewMode', 'table')" title="Table">
                <x-icon name="o-table-cells" class="w-4 h-4" />
            </button>
        </div>
    </div>

    <!-- Kanban Mode -->
    @if ($viewMode === 'kanban')
    <div class="flex items-start gap-4 overflow-auto pb-2 max-h-[70vh]">
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
                    <span class="text-xs font-bold text-primary"><x-currency-abbr :value="$this->stageSum($stage['key'])" /></span>
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

                            @php
                                $usesWonData = $funnel->stage === 'won' && $funnel->nominal_won;
                                $usesClosingData = in_array($funnel->stage, FoFunnel::CLOSED_STAGES, true) && $funnel->nominal_investasi;
                                $displayAmount = $usesClosingData ? $funnel->nominal_investasi : ($usesWonData ? $funnel->nominal_won : $funnel->proyeksi_amount);
                                $displayDate = $usesClosingData ? $funnel->tgl_closing : ($usesWonData ? $funnel->tgl_won : $funnel->proyeksi_close_date);
                                $showWonComparison = $usesClosingData && $funnel->nominal_won && (float) $funnel->nominal_won !== (float) $funnel->nominal_investasi;
                                $amountBadgeClass = collect($stages)->firstWhere('key', $funnel->stage)['badge'] ?? 'badge-ghost';
                            @endphp
                            <div class="flex items-center justify-between pt-1">
                                <span class="badge badge-sm {{ $amountBadgeClass }} font-semibold"><x-currency-abbr :value="$displayAmount" /></span>
                                <span class="text-[11px] text-base-content/50 truncate max-w-[110px]" title="{{ $funnel->employee?->user?->name }}">{{ $funnel->employee?->user?->name }}</span>
                            </div>
                            @php $fuCount = $funnel->followup_count; @endphp
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[11px] text-base-content/40 shrink-0">{{ $displayDate?->format('d M Y') ?? '-' }}</span>
                                <span class="badge badge-xs badge-outline shrink-0">{{ $funnel->sumber }}</span>
                            </div>
                            @if ($showWonComparison)
                                <div class="tooltip w-full" data-tip="Nominal komitmen (Won) vs nominal final (Closing) beda.">
                                    <div class="text-[11px] text-warning/80 bg-warning/10 rounded-lg px-2 py-1 truncate">
                                        Komit: <x-currency-abbr :value="$funnel->nominal_won" />
                                    </div>
                                </div>
                            @endif
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
                            @if ($funnel->stage === 'lost' && $funnel->lost_reason)
                                <div class="tooltip w-full" data-tip="{{ $funnel->lost_reason }}">
                                    <div class="text-[11px] text-error/80 bg-error/10 rounded-lg px-2 py-1 truncate">
                                        {{ $funnel->lost_reason }}
                                    </div>
                                </div>
                            @endif
                            @php $fuCount = $funnel->followup_count; @endphp
                            @if ($fuCount > 0)
                                <div class="tooltip" data-tip="Tampilkan detail">
                                    <button
                                        type="button"
                                        class="badge badge-sm badge-secondary cursor-pointer w-full"
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
    @endif

    <!-- Table Mode -->
    @if ($viewMode === 'table')
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
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
                        <th>Lost Reason</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->funnels as $funnel)
                        @php
                            $usesWonDataTable = $funnel->stage === 'won' && $funnel->nominal_won;
                            $usesClosingDataTable = in_array($funnel->stage, FoFunnel::CLOSED_STAGES, true) && $funnel->nominal_investasi;
                            $displayAmountTable = $usesClosingDataTable ? $funnel->nominal_investasi : ($usesWonDataTable ? $funnel->nominal_won : $funnel->proyeksi_amount);
                            $displayDateTable = $usesClosingDataTable ? $funnel->tgl_closing : ($usesWonDataTable ? $funnel->tgl_won : $funnel->proyeksi_close_date);
                        @endphp
                        <tr class="border-b border-base-300 hover:bg-base-200">
                            <td class="font-semibold text-base-content py-3">{{ $funnel->contact?->nama }}</td>
                            <td class="max-w-[220px] truncate">{{ $funnel->contact?->profesi }}</td>
                            <td><x-currency-abbr :value="$displayAmountTable" /></td>
                            <td>{{ $funnel->employee?->user?->name }}</td>
                            <td>{{ $funnel->sumber }}</td>
                            <td>
                                @php $meta = collect($stages)->firstWhere('key', $funnel->stage); @endphp
                                <span class="badge badge-sm {{ $meta['badge'] ?? 'badge-neutral' }}">{{ $meta['label'] ?? $funnel->stage }}</span>
                            </td>
                            <td>{{ $displayDateTable?->format('d M Y') ?? '-' }}</td>
                            <td>
                                @php $fuCountTable = $funnel->followup_count; @endphp
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
                            <td class="max-w-[220px] truncate">
                                @if ($funnel->lost_reason)
                                    <span class="text-error/80 text-xs" title="{{ $funnel->lost_reason }}">{{ $funnel->lost_reason }}</span>
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
                            <td colspan="11" class="text-center text-base-content/50 py-8">
                                Belum ada data funnel.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

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
                <x-input label="Proyeksi Amount (Rp)" type="text" inputmode="numeric" x-mask:dynamic="$money($input, ',', '.', 0)" x-model="addForm.proyeksiAmount" placeholder="Cth: 250.000.000" hint="Otomatis diformat, cth: 250.000.000 untuk Rp 250 Juta" />
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
                <x-input label="Proyeksi Amount (Rp)" type="text" inputmode="numeric" x-mask:dynamic="$money($input, ',', '.', 0)" x-model="editForm.proyeksiAmount" placeholder="Cth: 250.000.000" hint="Otomatis diformat, cth: 250.000.000 untuk Rp 250 Juta" />
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

    <!-- Edit Follow Up Modal -->
    <x-modal id="funnel-followup-edit-modal" title="Edit Follow Up" subtitle="Perubahan akan disimpan ke log perubahan." without-trap-focus>
        <form x-on:submit.prevent="submitEditFollowUp()" class="space-y-4">
            <div>
                <div class="flex items-center justify-between mb-0.5">
                    <span class="fieldset-legend text-sm font-medium">Tanggal Follow Up</span>
                    <x-button label="Hari Ini" icon="o-calendar-days" class="btn-ghost btn-xs" x-on:click="setEditFollowUpToday()" />
                </div>
                <x-input type="date" x-model="editFollowUpForm.tanggal" required />
            </div>

            <x-textarea label="Catatan" x-model="editFollowUpForm.catatan" placeholder="Ringkasan hasil follow up..." rows="4" />

            <div class="modal-action">
                <form method="dialog">
                    <x-button label="Batal" class="btn-ghost" />
                </form>
                <x-button label="Simpan" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Follow Up Change Log Modal -->
    <x-modal id="funnel-followup-log-modal" title="Log Perubahan Follow Up" subtitle="Terbaru ke terlama." without-trap-focus>
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th>Waktu</th>
                        <th>Diubah Oleh</th>
                        <th>Tanggal</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->activityLogs as $log)
                        <tr class="border-b border-base-300 align-top">
                            <td class="whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $log->changedBy?->name ?? '-' }}</td>
                            <td class="text-xs">
                                {{ $log->old_tanggal?->format('d M Y') ?? '-' }}
                                <x-icon name="o-arrow-right" class="w-3 h-3 inline text-base-content/30" />
                                {{ $log->new_tanggal?->format('d M Y') ?? '-' }}
                            </td>
                            <td class="text-xs max-w-[220px]">
                                <div class="text-base-content/50 truncate" title="{{ $log->old_catatan }}">{{ $log->old_catatan ?? 'Tidak ada catatan.' }}</div>
                                <x-icon name="o-arrow-down" class="w-3 h-3 text-base-content/30" />
                                <div class="truncate" title="{{ $log->new_catatan }}">{{ $log->new_catatan ?? 'Tidak ada catatan.' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-base-content/50 py-4">Belum ada perubahan tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="modal-action">
            <form method="dialog">
                <x-button label="Tutup" class="btn-ghost" wire:click="closeActivityLog" />
            </form>
        </div>
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

            <x-input label="Nominal Investasi (Rp)" type="text" inputmode="numeric" x-mask:dynamic="$money($input, ',', '.', 0)" wire:model="akadNominal" placeholder="Cth: 250.000.000" hint="Otomatis diformat, cth: 250.000.000 untuk Rp 250 Juta" required />

            <x-file label="Dokumen Akad" wire:model="akadFile" accept=".doc,.docx,.pdf" hint="Format DOC, DOCX, atau PDF. Maksimal 5MB." required />

            <div class="modal-action">
                <form method="dialog">
                    <x-button label="Batal" class="btn-ghost" />
                </form>
                <x-button label="Simpan" type="submit" class="btn-primary" spinner="submitAkad" />
            </div>
        </form>
    </x-modal>

    <!-- Won Modal (wajib diisi, gak bisa ditutup sebelum disimpan) -->
    <x-modal id="funnel-won-modal" title="Catat Won (Komitmen)" subtitle="Tanggal & nominal komitmen investor wajib diisi. Form ini gak bisa ditutup sebelum disimpan." persistent wire:ignore.self>
        <form wire:submit="submitWon" class="space-y-4">
            <div>
                <div class="flex items-center justify-between mb-0.5">
                    <span class="fieldset-legend text-sm font-medium">Tanggal Won</span>
                    <x-button label="Hari Ini" icon="o-calendar-days" class="btn-ghost btn-xs" x-on:click="setWonToday()" />
                </div>
                <x-input type="date" wire:model="wonTanggal" required />
            </div>

            <x-input label="Nominal Komitmen (Rp)" type="text" inputmode="numeric" x-mask:dynamic="$money($input, ',', '.', 0)" wire:model="wonNominal" placeholder="Cth: 250.000.000" hint="Nominal yang disepakati saat komitmen — bisa beda dari nominal final di Closing." required />

            <div class="modal-action">
                <x-button label="Simpan" type="submit" class="btn-primary w-full" spinner="submitWon" />
            </div>
        </form>
    </x-modal>

    <!-- Closing Modal (wajib diisi, gak bisa ditutup sebelum disimpan) -->
    <x-modal id="funnel-closing-modal" title="Catat Closing" subtitle="Uang investor sudah masuk PT — tanggal & nominal final wajib diisi. Form ini gak bisa ditutup sebelum disimpan." persistent wire:ignore.self>
        <form wire:submit="submitClosing" class="space-y-4">
            <div>
                <div class="flex items-center justify-between mb-0.5">
                    <span class="fieldset-legend text-sm font-medium">Tanggal Closing</span>
                    <x-button label="Hari Ini" icon="o-calendar-days" class="btn-ghost btn-xs" x-on:click="setClosingToday()" />
                </div>
                <x-input type="date" wire:model="closingTanggal" required />
            </div>

            <x-input label="Nominal Investasi Final (Rp)" type="text" inputmode="numeric" x-mask:dynamic="$money($input, ',', '.', 0)" wire:model="closingNominal" placeholder="Cth: 250.000.000" hint="Nominal final yang beneran masuk ke perusahaan." required />

            <div class="modal-action">
                <x-button label="Simpan" type="submit" class="btn-primary w-full" spinner="submitClosing" />
            </div>
        </form>
    </x-modal>

    <!-- Lost Modal -->
    <x-modal id="funnel-lost-modal" title="Catat Lost" subtitle="Kartu ini akan pindah ke kolom Lost setelah dicatat." without-trap-focus>
        <form x-on:submit.prevent="submitLost()" class="space-y-4">
            <div>
                <div class="flex items-center justify-between mb-0.5">
                    <span class="fieldset-legend text-sm font-medium">Tanggal Lost</span>
                    <x-button label="Hari Ini" icon="o-calendar-days" class="btn-ghost btn-xs" x-on:click="setLostToday()" />
                </div>
                <x-input type="date" x-model="lostForm.tanggal" required />
            </div>

            <x-textarea label="Lost Reason" x-model="lostForm.reason" placeholder="Kenapa lead ini gagal closing..." rows="4" />

            <div class="modal-action">
                <form method="dialog">
                    <x-button label="Batal" class="btn-ghost" />
                </form>
                <x-button label="Simpan" type="submit" class="btn-primary" />
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
                            <div class="flex items-start justify-between gap-2">
                                <div class="text-sm font-semibold text-base-content">{{ $activity->tanggal->format('d M Y') }}</div>
                                <div class="flex items-center gap-1 shrink-0">
                                    @if ($activity->logs_count > 0)
                                        <div class="tooltip" data-tip="Log Perubahan ({{ $activity->logs_count }})">
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-xs btn-circle"
                                                x-on:click="openFollowUpLog({{ $activity->id }})"
                                            >
                                                <x-icon name="o-clock" class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    @endif
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs btn-circle"
                                        x-on:click="editFollowUpFromHistory({{ $activity->id }}, '{{ $activity->tanggal->format('Y-m-d') }}', @js($activity->catatan))"
                                    >
                                        <x-icon name="o-pencil-square" class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs btn-circle text-error"
                                        x-on:click="deleteFollowUp({{ $activity->id }})"
                                    >
                                        <x-icon name="o-trash" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div class="text-sm text-base-content/70">{{ $activity->catatan ?? 'Tidak ada catatan.' }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-base-content/40 text-center py-8">Belum ada follow up tercatat.</div>
                    @endforelse
                </div>

                <x-button
                    :label="$showDeletedFollowUps ? 'Sembunyikan Terhapus' : 'Lihat Terhapus'"
                    icon="o-archive-box"
                    class="btn-ghost btn-xs w-full text-base-content/50"
                    wire:click="toggleDeletedFollowUps"
                />

                @if ($showDeletedFollowUps)
                    <div class="space-y-3">
                        <div class="text-xs font-semibold text-base-content/40 uppercase tracking-wide">Follow Up Terhapus</div>
                        @forelse ($this->deletedFollowUps as $activity)
                            <div class="card bg-base-200/50 border border-dashed border-base-300 p-3 space-y-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="text-sm font-semibold text-base-content/60 line-through">{{ $activity->tanggal->format('d M Y') }}</div>
                                    <div class="tooltip" data-tip="Pulihkan">
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-xs btn-circle text-success"
                                            wire:click="restoreFollowUp({{ $activity->id }})"
                                        >
                                            <x-icon name="o-arrow-uturn-left" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <div class="text-sm text-base-content/50">{{ $activity->catatan ?? 'Tidak ada catatan.' }}</div>
                                <div class="text-[11px] text-base-content/30">Dihapus {{ $activity->deleted_at->diffForHumans() }}</div>
                            </div>
                        @empty
                            <div class="text-sm text-base-content/40 text-center py-4">Tidak ada follow up terhapus.</div>
                        @endforelse
                    </div>
                @endif
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
        editFollowUpForm: { id: null, tanggal: '', catatan: '' },
        lostForm: { funnelId: null, tanggal: '', reason: '' },

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

            $wire.createLead(this.addForm.contactId, this.addForm.tanggal, normalizeCurrency(this.addForm.proyeksiAmount), this.addForm.sumber);
            document.getElementById('funnel-add-modal').close();
        },

        editCard(id, tanggal, proyeksiAmount, sumber) {
            this.editForm = { id, tanggal: tanggal ?? '', proyeksiAmount: proyeksiAmount ?? '', sumber };
            document.getElementById('funnel-edit-modal').showModal();
        },

        submitEdit() {
            $wire.updateLead(this.editForm.id, this.editForm.tanggal, normalizeCurrency(this.editForm.proyeksiAmount), this.editForm.sumber);
            document.getElementById('funnel-edit-modal').close();
        },

        deleteCard(id) {
            if (confirm('Hapus data funnel ini?')) {
                $wire.deleteFunnel(id);
            }
        },

        deleteFollowUp(id) {
            if (confirm('Hapus follow up ini? Masih bisa dipulihkan lewat tombol "Lihat Terhapus".')) {
                $wire.deleteFollowUp(id);
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

        stageOrder: { leads: 0, ql: 1, pitching: 2, followup: 3, won: 4, lost: 4, closing: 5, akad: 6 },

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

            // Mundur ke stage sebelumnya gak perlu isi form lagi — form cuma
            // muncul kalau majuin kartu (lagi) ke stage yang sama ke depannya.
            const isMovingBackward = this.stageOrder[stage] < this.stageOrder[this.draggingStage];

            if (isMovingBackward) {
                $wire.moveStage(this.draggingId, stage);
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
            } else if (stage === 'won') {
                $wire.openWonModal(this.draggingId).then(() => {
                    document.getElementById('funnel-won-modal').showModal();
                });
            } else if (stage === 'closing') {
                $wire.openClosingModal(this.draggingId).then(() => {
                    document.getElementById('funnel-closing-modal').showModal();
                });
            } else if (stage === 'lost') {
                this.lostForm = { funnelId: this.draggingId, tanggal: '', reason: '' };
                document.getElementById('funnel-lost-modal').showModal();
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

        setWonToday() {
            $wire.set('wonTanggal', new Date().toISOString().slice(0, 10));
        },

        setClosingToday() {
            $wire.set('closingTanggal', new Date().toISOString().slice(0, 10));
        },

        submitFollowUp() {
            if (!this.followUpForm.tanggal) {
                return;
            }

            $wire.logFollowUp(this.followUpForm.funnelId, this.followUpForm.tanggal, this.followUpForm.catatan, this.followUpMovesStage);
            document.getElementById('funnel-followup-modal').close();
        },

        editFollowUpFromHistory(id, tanggal, catatan) {
            this.editFollowUpForm = { id, tanggal: tanggal ?? '', catatan: catatan ?? '' };
            document.getElementById('funnel-followup-edit-modal').showModal();
        },

        setEditFollowUpToday() {
            this.editFollowUpForm.tanggal = new Date().toISOString().slice(0, 10);
        },

        submitEditFollowUp() {
            if (!this.editFollowUpForm.tanggal) {
                return;
            }

            $wire.updateFollowUp(this.editFollowUpForm.id, this.editFollowUpForm.tanggal, this.editFollowUpForm.catatan);
            document.getElementById('funnel-followup-edit-modal').close();
        },

        openFollowUpLog(id) {
            $wire.openActivityLog(id).then(() => {
                document.getElementById('funnel-followup-log-modal').showModal();
            });
        },

        setLostToday() {
            this.lostForm.tanggal = new Date().toISOString().slice(0, 10);
        },

        submitLost() {
            if (!this.lostForm.tanggal) {
                return;
            }

            $wire.logLostAndMove(this.lostForm.funnelId, this.lostForm.tanggal, this.lostForm.reason);
            document.getElementById('funnel-lost-modal').close();
        },
    }));
</script>
@endscript
