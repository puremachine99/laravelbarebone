<?php

use function Livewire\Volt\{state, layout, computed, usesPagination};
use App\Notifications\ManagerVerifiedNotification;
use Flux\Flux;

usesPagination();
layout('layouts.app');

state([
    'filterFiscalYear' => (string) date('Y'),
    'filterSumberProyeksi' => '',
    'search' => '',
    'filterStatus' => '',
    'filterManagerVerified' => '',
    'filterEmployee' => '',

    // Detail Drawer State
    'showDetailDrawer' => false,
    'selectedCardForDetail' => null,

    // Edit Modal State
    'editingCard' => null,
    'showEditModal' => false,
    'editTitle' => '',
    'editBudget' => '',
    'editDate' => '',
    'editMethod' => 'Online',
    'editSumber' => 'SIRUP',
    'editStatus' => 'won',
    'editProjectionMonth' => '',
    'editProjectionWeek' => 'Pekan 1',
    'editProjectionStatus' => 'Optimis',
    'editDriveLink' => '',
    'editNegativeReason' => 'No Budget',

    // Follow Up State
    'followUpTujuan' => 'Penawaran',
    'followUpHasil' => '',

    // Realisasi Hot update states
    'pendingDecisionCard' => null,
    'decisionType' => 'hot',
    'projectionMonth' => '',
    'projectionWeek' => 'Pekan 1',
    'driveLink' => '',
    'negativeReason' => 'No Budget',
    'showTerlaksanaModal' => false,
    'terlaksanaModalTitle' => 'Tindak Lanjut Prospek Hot',
    'showWonConfirmModal' => false,
    'wonConfirmType' => 'terlaksana',
]);

// Refresh component when a card is updated
\Livewire\Volt\mount(function () {
    $this->editProjectionMonth = date('Y-m');

    if ($cardId = request()->integer('card')) {
        $card = \App\Models\Card::with(['stage', 'contact.regency.province', 'contact.opd', 'contact.region', 'employee.user'])->find($cardId);
        if ($card) {
            $this->selectedCardForDetail = $card;
            $this->showDetailDrawer = true;
        }
    }
});

$cards = computed(function () {
    $user = auth()->user();
    $isAdmin = $user->hasRole('Super Admin');
    $isSpv = $user->hasRole('Partnership Admin');

    return \App\Models\Card::forRealisasi()
        ->where('fiscal_year', (int) $this->filterFiscalYear)
        ->when($this->filterSumberProyeksi, fn($q) => $q->where('sumber_proyeksi', $this->filterSumberProyeksi))
        ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
        ->when($this->filterManagerVerified !== '', fn($q) => $q->where('manager_verified', $this->filterManagerVerified === '1'))
        ->when($isSpv, function ($q) use ($user) {
            $regionId = $user->employee?->region_id;
            if ($regionId) {
                $q->whereHas('employee', fn($e) => $e->where('region_id', $regionId));
            }
        })
        ->when(!$isAdmin && !$isSpv, fn($q) => $q->where('employee_id', $user->employee?->id ?? 0))
        ->when($this->filterEmployee, fn($q) => $q->where('employee_id', (int) $this->filterEmployee))
        ->when($this->search, function ($q) {
            $q->where(function ($sub) {
                $sub->where('title', 'ilike', '%' . $this->search . '%')
                    ->orWhereHas('contact', function ($c) {
                        $c->where('name', 'ilike', '%' . $this->search . '%')
                            ->orWhereHas('opd', function ($o) {
                                $o->where('name', 'ilike', '%' . $this->search . '%');
                            });
                    });
            });
        })
        ->with(['contact.regency.province', 'contact.opd', 'contact.region', 'employee.user'])
        ->orderBy('updated_at', 'desc')
        ->paginate(10);
});

$realisasiStats = computed(function () {
    $user = auth()->user();
    $isAdmin = $user->hasRole('Super Admin');
    $isSpv = $user->hasRole('Partnership Admin');

    $baseQuery = \App\Models\Card::forRealisasi()
        ->where('fiscal_year', (int) $this->filterFiscalYear)
        ->when($this->filterSumberProyeksi, fn($q) => $q->where('sumber_proyeksi', $this->filterSumberProyeksi))
        ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
        ->when($this->filterManagerVerified !== '', fn($q) => $q->where('manager_verified', $this->filterManagerVerified === '1'))
        ->when($isSpv, function ($q) use ($user) {
            $regionId = $user->employee?->region_id;
            if ($regionId) {
                $q->whereHas('employee', fn($e) => $e->where('region_id', $regionId));
            }
        })
        ->when(!$isAdmin && !$isSpv, fn($q) => $q->where('employee_id', $user->employee?->id ?? 0))
        ->when($this->filterEmployee, fn($q) => $q->where('employee_id', (int) $this->filterEmployee))
        ->when($this->search, function ($q) {
            $q->where(function ($sub) {
                $sub->where('title', 'ilike', '%' . $this->search . '%')
                    ->orWhereHas('contact', function ($c) {
                        $c->where('name', 'ilike', '%' . $this->search . '%')
                            ->orWhereHas('opd', function ($o) {
                                $o->where('name', 'ilike', '%' . $this->search . '%');
                            });
                    });
            });
        });

    $allCards = $baseQuery->with('stage')->get();
    
    $totalCount = $allCards->count();
    $realisasiCount = $allCards->count();
    $totalBudget = $allCards->sum('nominal_value');

    $wonCount = $allCards->filter(fn($c) => $c->status === 'won')->count();
    $hotCount = $allCards->filter(fn($c) => $c->status === 'hot')->count();

    $verifiedCount = $allCards->filter(fn($c) => $c->manager_verified)->count();
    $pendingCount = $allCards->filter(fn($c) => !$c->manager_verified)->count();

    return [
        'total_count' => $totalCount,
        'realisasi_count' => $realisasiCount,
        'total_budget' => $totalBudget,
        'won_count' => $wonCount,
        'hot_count' => $hotCount,
        'verified_count' => $verifiedCount,
        'pending_count' => $pendingCount,
    ];
});

$resetFilters = function () {
    $this->filterFiscalYear = (string) date('Y');
    $this->filterSumberProyeksi = '';
    $this->filterStatus = '';
    $this->filterManagerVerified = '';
    $this->filterEmployee = '';
    $this->search = '';
};

$employeeOptions = computed(function () {
    $user = auth()->user();
    $query = \App\Models\Employee::join('users', 'users.id', '=', 'employees.user_id')
        ->select('employees.id', 'users.name');

    if ($user->hasRole('Partnership Admin')) {
        $regionId = $user->employee?->region_id;
        if ($regionId) {
            $query->where('employees.region_id', $regionId);
        }
    } elseif (!$user->hasRole('Super Admin')) {
        // Plain PMO: only their own record, no cross-employee visibility.
        return collect([$user->employee ? ['id' => (string) $user->employee->id, 'name' => $user->name] : ['id' => '', 'name' => 'Semua PMO']])->toArray();
    }

    return $query->orderBy('users.name')->get()
        ->map(fn($e) => ['id' => (string) $e->id, 'name' => $e->name])
        ->prepend(['id' => '', 'name' => 'Semua PMO'])
        ->toArray();
});

$canVerify = computed(fn() => 
    auth()->user()->hasRole('Super Admin') || 
    auth()->user()?->email === 'supernakama@smartid.co.id' ||
    auth()->user()->hasPermissionTo('verify-manager')
);

$toggleVerification = function (int $cardId) {
    $isSuper = auth()->user()?->email === 'supernakama@smartid.co.id';
    $canVerify = $isSuper || 
                 auth()->user()->hasRole('Super Admin') || 
                 auth()->user()->hasPermissionTo('verify-manager');
    
    if (!$canVerify) {
        Flux::toast(variant: 'danger', text: 'Hanya Manager atau Super Admin yang dapat memverifikasi.');
        return;
    }

    $card = \App\Models\Card::findOrFail($cardId);
    $card->update([
        'manager_verified' => !$card->manager_verified,
    ]);

    $action = $card->manager_verified ? 'verified' : 'unverified';
    $card->addHistoryLog('verification_changed', "Manager {$action} the prospect during Realisasi");

    if ($card->manager_verified) {
        ManagerVerifiedNotification::sendToOwner($card);
    }

    Flux::toast(variant: 'success', text: $card->manager_verified ? 'Prospek berhasil diverifikasi oleh Manager!' : 'Verifikasi manager dibatalkan.');
};

$viewCard = function (int $cardId) {
    $this->selectedCardForDetail = \App\Models\Card::with(['stage', 'contact.regency.province', 'contact.opd', 'contact.region', 'employee.user'])->findOrFail($cardId);
    $this->showDetailDrawer = true;
};

$addFollowUp = function (int $cardId) {
    $this->validate([
        'followUpTujuan' => ['required', 'string'],
        'followUpHasil' => ['required', 'string'],
    ]);

    $card = \App\Models\Card::findOrFail($cardId);
    $followUps = $card->follow_ups ?? [];
    $followUps[] = [
        'timestamp' => now()->toIso8601String(),
        'user' => auth()->user()?->name ?? 'System',
        'tujuan' => $this->followUpTujuan,
        'hasil' => $this->followUpHasil,
    ];

    $card->update(['follow_ups' => $followUps]);
    $card->addHistoryLog('follow_up_added', "Follow up PMO Pratama added for target: {$this->followUpTujuan}");

    Flux::toast(variant: 'success', text: 'Catatan Follow Up berhasil disimpan!');
};

$confirmWonAction = function () {
    $this->showWonConfirmModal = false;
    if ($this->wonConfirmType === 'terlaksana') {
        $this->executeSaveDecision();
    } elseif ($this->wonConfirmType === 'edit') {
        $this->executeSaveEdit();
    }
};

$openHotUpdate = function (int $cardId) {
    $card = \App\Models\Card::findOrFail($cardId);
    $this->pendingDecisionCard = [
        'id' => $card->id,
        'contact_name' => $card->contact ? $card->contact->name : $card->title,
    ];
    $this->decisionType = $card->status;
    $this->projectionMonth = $card->projection_month ? (\strlen($card->projection_month) <= 7 ? $card->projection_month : \Carbon\Carbon::parse($card->projection_month)->format('Y-m')) : date('Y-m');
    $this->projectionWeek = $card->projection_week ?: '1';
    $this->driveLink = $card->drive_link ?: '';
    $this->terlaksanaModalTitle = 'Tindak Lanjut Prospek Hot';
    $this->showTerlaksanaModal = true;
};

$saveTerlaksanaDecision = function () {
    if (!$this->pendingDecisionCard) {
        return;
    }

    $card = \App\Models\Card::findOrFail($this->pendingDecisionCard['id']);

    if (in_array($this->decisionType, ['won', 'hot', 'warm'])) {
        if ($this->decisionType === 'won' && !$card->manager_verified) {
            Flux::toast(variant: 'danger', text: 'Perubahan status ke Won memerlukan verifikasi manager.');
            return;
        }

        $this->validate([
            'projectionMonth' => ['required', 'string'],
            'projectionWeek' => ['required', 'string'],
            'driveLink' => ['nullable', 'url'],
        ]);

        if ($this->decisionType === 'won') {
            $this->wonConfirmType = 'terlaksana';
            $this->showWonConfirmModal = true;
            return;
        }
    } else {
        $this->validate([
            'negativeReason' => ['required', 'in:No Budget,No Authority,No Need,Lost to Competitor'],
        ]);
    }

    $this->executeSaveDecision();
};

$executeSaveDecision = function () {
    if (!$this->pendingDecisionCard) {
        return;
    }

    $card = \App\Models\Card::findOrFail($this->pendingDecisionCard['id']);

    if (in_array($this->decisionType, ['won', 'hot', 'warm'])) {
        $card->update([
            'status' => $this->decisionType,
            'projection_month' => $this->projectionMonth,
            'projection_week' => $this->projectionWeek,
            'drive_link' => $this->driveLink,
            'negative_reason' => null,
        ]);
        $card->addHistoryLog('card_edited', "Realisasi status updated to {$this->decisionType}");

        if ($this->decisionType === 'won') {
            $this->dispatch('show-confetti');
            Flux::toast(variant: 'success', text: 'Selamat! Kerja sama telah berhasil disepakati (Won / Deal)! 🥳🎉');
        } else {
            Flux::toast(variant: 'success', text: 'Tindak lanjut realisasi berhasil disimpan!');
        }
    } else {
        $activeProfile = \App\Models\PipelineProfile::where('pipeline_name', 'Pitching')->where('is_active', true)->first();
        $lostStage = $activeProfile ? $activeProfile->stages()->where('stage_name', 'Tidak Terlaksana')->first() : null;

        $card->update([
            'stage_id' => $lostStage ? $lostStage->id : $card->stage_id,
            'status' => 'lost',
            'negative_reason' => $this->negativeReason,
            'projection_month' => null,
            'projection_week' => null,
            'drive_link' => null,
        ]);
        $card->addHistoryLog('stage_changed', "Status changed to lost during Realisasi (reason: {$this->negativeReason})");
        Flux::toast(variant: 'success', text: 'Tindak lanjut realisasi berhasil disimpan!');
    }

    $this->showTerlaksanaModal = false;
    $this->pendingDecisionCard = null;
    $this->driveLink = '';
};

$editCard = function (int $cardId) {
    $card = \App\Models\Card::findOrFail($cardId);
    $this->editingCard = $card;
    $this->editTitle = $card->title;
    $this->editBudget = $card->nominal_value;
    $this->editDate = $card->pitching_date ? $card->pitching_date->format('Y-m-d') : date('Y-m-d');
    $this->editMethod = $card->communication_method ?: 'Online';
    $this->editSumber = $card->sumber_proyeksi ?: 'SIRUP';
    $this->editStatus = $card->status;
    $this->editProjectionMonth = $card->projection_month ? (\strlen($card->projection_month) <= 7 ? $card->projection_month : \Carbon\Carbon::parse($card->projection_month)->format('Y-m')) : date('Y-m');
    $this->editProjectionWeek = $card->projection_week ?: '1';
    $this->editProjectionStatus = $card->projection_status ?: 'Optimis';
    $this->editDriveLink = $card->drive_link ?: '';
    $this->editNegativeReason = $card->negative_reason ?: 'No Budget';
    $this->showEditModal = true;
};

$saveEdit = function () {
    if (!$this->editingCard) {
        return;
    }

    if ($this->editStatus === 'won' && !$this->editingCard->manager_verified) {
        Flux::toast(variant: 'danger', text: 'Perubahan status ke Won memerlukan verifikasi manager.');
        return;
    }

    $this->validate([
        'editTitle' => ['required', 'string', 'max:255'],
        'editBudget' => ['required', 'numeric', 'min:1'],
        'editDate' => ['required', 'date'],
        'editMethod' => ['required', 'in:Online,Offline'],
        'editSumber' => ['required', 'string'],
        'editStatus' => ['required', 'in:won,hot,warm,lost'],
    ]);

    if ($this->editStatus === 'won') {
        $this->wonConfirmType = 'edit';
        $this->showWonConfirmModal = true;
        return;
    }

    $this->executeSaveEdit();
};

$executeSaveEdit = function () {
    if (!$this->editingCard) {
        return;
    }

    $card = \App\Models\Card::findOrFail($this->editingCard->id);

    $data = [
        'title' => $this->editTitle,
        'nominal_value' => (float) $this->editBudget,
        'pitching_date' => $this->editDate,
        'communication_method' => $this->editMethod,
        'sumber_proyeksi' => $this->editSumber,
        'status' => $this->editStatus,
    ];

    $activeProfile = \App\Models\PipelineProfile::where('pipeline_name', 'Pitching')->where('is_active', true)->first();

    if ($this->editStatus === 'lost') {
        $lostStage = $activeProfile ? $activeProfile->stages()->where('stage_name', 'Tidak Terlaksana')->first() : null;
        
        $this->validate([
            'editNegativeReason' => ['required', 'in:No Budget,No Authority,No Need,Lost to Competitor'],
        ]);

        $data['stage_id'] = $lostStage ? $lostStage->id : $card->stage_id;
        $data['negative_reason'] = $this->editNegativeReason;
        $data['projection_month'] = null;
        $data['projection_week'] = null;
        $data['projection_status'] = null;
        $data['drive_link'] = null;
        $card->addHistoryLog('stage_changed', "Status changed to lost (Tidak Terlaksana) during Realisasi follow-up (reason: {$this->editNegativeReason})");
    } elseif ($this->editStatus === 'warm') {
        $rencanaStage = $activeProfile ? $activeProfile->stages()->where('stage_name', 'Rencana')->first() : null;
        
        $data['stage_id'] = $rencanaStage ? $rencanaStage->id : $card->stage_id;
        $data['projection_month'] = $this->editProjectionMonth;
        $data['projection_week'] = $this->editProjectionWeek;
        $data['projection_status'] = $this->editProjectionStatus;
        $data['drive_link'] = $this->editDriveLink;
        $data['negative_reason'] = null;
        $card->addHistoryLog('stage_changed', "Status updated to warm and moved back to Rencana");
    } else {
        $this->validate([
            'editProjectionMonth' => ['required', 'string'],
            'editProjectionWeek' => ['required', 'string'],
            'editProjectionStatus' => ['required', 'string'],
            'editDriveLink' => ['nullable', 'url'],
        ]);

        $data['projection_month'] = $this->editProjectionMonth;
        $data['projection_week'] = $this->editProjectionWeek;
        $data['projection_status'] = $this->editProjectionStatus;
        $data['drive_link'] = $this->editDriveLink;
        $data['negative_reason'] = null;
        $card->addHistoryLog('card_edited', "Realisasi details updated");
    }

    $card->update($data);
    
    if ($this->editStatus === 'won') {
        $this->dispatch('show-confetti');
        Flux::toast(variant: 'success', text: 'Selamat! Kerja sama telah berhasil disepakati (Won / Deal)! 🥳🎉');
    } else {
        Flux::toast(variant: 'success', text: 'Data realisasi berhasil diperbarui!');
    }

    $this->showEditModal = false;
    $this->editingCard = null;
};

new class {};
?>

<div class="space-y-6">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Ringkasan Realisasi -->
        <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-sm rounded-2xl">
            <div>
                <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">RINGKASAN REALISASI</h3>
                <h2 class="text-2xl mt-1 font-extrabold text-base-content">
                    {{ $this->realisasiStats['realisasi_count'] }} Realisasi
                </h2>
                <div class="text-xs text-base-content/50 mt-1">
                    Won: {{ $this->realisasiStats['won_count'] }} | Hot: {{ $this->realisasiStats['hot_count'] }}
                </div>
            </div>

            <!-- Stacked Horizontal Bar Chart: Status Realisasi -->
            @php
                $statusTotal = max($this->realisasiStats['won_count'] + $this->realisasiStats['hot_count'], 1);
                $wonPercent = ($this->realisasiStats['won_count'] / $statusTotal) * 100;
                $hotPercent = ($this->realisasiStats['hot_count'] / $statusTotal) * 100;
            @endphp
            <div class="h-4 w-full bg-base-200 rounded-full overflow-hidden flex">
                <div class="h-full bg-emerald-500" style="width: {{ $wonPercent }}%" title="Won: {{ $this->realisasiStats['won_count'] }}"></div>
                <div class="h-full bg-orange-500" style="width: {{ $hotPercent }}%" title="Hot: {{ $this->realisasiStats['hot_count'] }}"></div>
            </div>

            <div class="grid grid-cols-2 gap-1 text-[11px] text-base-content/70">
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                    <span class="truncate">Won ({{ $this->realisasiStats['won_count'] }})</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-orange-500 inline-block"></span>
                    <span class="truncate">Hot ({{ $this->realisasiStats['hot_count'] }})</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Anggaran Realisasi -->
        <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-sm rounded-2xl">
            <div>
                <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">ANGGARAN REALISASI</h3>
                <h2 class="text-2xl mt-1 font-extrabold text-base-content text-indigo-600 dark:text-indigo-400">
                    Rp {{ number_format($this->realisasiStats['total_budget'], 0, ',', '.') }}
                </h2>
                <div class="text-xs text-base-content/50 mt-1">
                    Dari total prospek bernilai positif dan deal.
                </div>
            </div>

            <div class="h-4 w-full bg-base-200 rounded-full overflow-hidden flex">
                <div class="h-full bg-indigo-500 w-full" title="Realisasi"></div>
            </div>

            <div class="text-[11px] text-base-content/70 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-indigo-500 inline-block"></span>
                <span>Prospek Terverifikasi & Terlaksana</span>
            </div>
        </div>

        <!-- Card 3: Verifikasi Manager -->
        <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-sm rounded-2xl">
            <div>
                <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">VERIFIKASI MANAGER</h3>
                <h2 class="text-2xl mt-1 font-extrabold text-base-content">
                    {{ $this->realisasiStats['pending_count'] }} Menunggu
                </h2>
                <div class="text-xs text-base-content/50 mt-1">
                    Terverifikasi: {{ $this->realisasiStats['verified_count'] }}
                </div>
            </div>

            <!-- Stacked Horizontal Bar Chart: Verifikasi -->
            @php
                $verifTotal = max($this->realisasiStats['verified_count'] + $this->realisasiStats['pending_count'], 1);
                $vPercent = ($this->realisasiStats['verified_count'] / $verifTotal) * 100;
                $pPercent = ($this->realisasiStats['pending_count'] / $verifTotal) * 100;
            @endphp
            <div class="h-4 w-full bg-base-200 rounded-full overflow-hidden flex">
                <div class="h-full bg-emerald-500" style="width: {{ $vPercent }}%" title="Terverifikasi: {{ $this->realisasiStats['verified_count'] }}"></div>
                <div class="h-full bg-amber-500" style="width: {{ $pPercent }}%" title="Menunggu: {{ $this->realisasiStats['pending_count'] }}"></div>
            </div>

            <div class="grid grid-cols-2 gap-1 text-[11px] text-base-content/70">
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                    <span class="truncate">Terverifikasi ({{ $this->realisasiStats['verified_count'] }})</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                    <span class="truncate">Menunggu ({{ $this->realisasiStats['pending_count'] }})</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="card bg-base-100 border border-base-300 p-6 space-y-6 shadow-xs rounded-2xl">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-base-content">Partnership Realisasi</h2>
                <p class="text-sm text-base-content/50 mt-1">
                    Kelola dan pantau prospek yang telah disetujui (Deal / Positif) dan terverifikasi.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <x-button wire:click="resetFilters" label="Reset Filter" icon="o-arrow-path" class="btn-ghost btn-sm" />
                <x-button label="Kembali ke Pitching" link="{{ route('partnership.pitching') }}" icon="o-arrow-left" class="btn-ghost btn-sm" />
            </div>
        </div>

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="w-48">
                    @php
                        $sumberFilters = [
                            ['id' => '', 'name' => 'Semua Sumber'],
                            ['id' => 'SIRUP', 'name' => 'SIRUP'],
                            ['id' => 'SIPD', 'name' => 'SIPD'],
                            ['id' => 'INAPROC', 'name' => 'INAPROC'],
                            ['id' => 'ANALISIS PS', 'name' => 'ANALISIS PS'],
                            ['id' => 'PERMINTAAN MITRA', 'name' => 'PERMINTAAN MITRA'],
                        ];
                    @endphp
                    <x-select label="Sumber Proyeksi" wire:model.live="filterSumberProyeksi" :options="$sumberFilters" />
                </div>
                <div class="w-44">
                    @php
                        $statusFilters = [
                            ['id' => '', 'name' => 'Semua Hasil'],
                            ['id' => 'hot', 'name' => 'HOT'],
                            ['id' => 'won', 'name' => 'WON'],
                        ];
                    @endphp
                    <x-select label="Hasil / Keputusan" wire:model.live="filterStatus" :options="$statusFilters" />
                </div>
                <div class="w-44">
                    @php
                        $verifFilters = [
                            ['id' => '', 'name' => 'Semua Verifikasi'],
                            ['id' => '1', 'name' => 'Terverifikasi'],
                            ['id' => '0', 'name' => 'Belum Diverifikasi'],
                        ];
                    @endphp
                    <x-select label="Verif Manager" wire:model.live="filterManagerVerified" :options="$verifFilters" />
                </div>
                @if(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Partnership Admin'))
                <div class="w-52">
                    <x-select label="Nama PMO" wire:model.live="filterEmployee" :options="$this->employeeOptions" />
                </div>
                @endif
            </div>
            <div class="w-64">
                <x-input label="Cari Prospek" wire:model.live.debounce.300ms="search" placeholder="Cari..." icon="o-magnifying-glass" clearable />
            </div>
        </div>

        <div class="divider my-0"></div>

        <div class="overflow-x-auto pb-24">
            <table class="table table-zebra w-full">
                <thead>
                    <tr class="text-base-content/50 border-base-300">
                        <th>Instansi / Kontak</th>
                        <th>Potensi Produk</th>
                        <th>Anggaran</th>
                        <th>Tanggal & Metode</th>
                        <th>Sumber</th>
                        <th>Proyeksi Closing</th>
                        <th>Status Proyeksi</th>
                        <th class="text-center">Verif Manager</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $hl = function (?string $text, string $search): string {
                            $safe = e($text ?? '');
                            if ($search === '' || $safe === '') return $safe;
                            $pattern = '/(' . preg_quote(e($search), '/') . ')/iu';
                            return preg_replace($pattern, '<mark class="bg-yellow-200 dark:bg-yellow-700 rounded-sm px-0.5 not-italic">$1</mark>', $safe) ?? $safe;
                        };
                    @endphp
                    @forelse ($this->cards as $card)
                        <tr :key="$card->id" class="border-base-300 hover:bg-base-200/50">
                            <!-- Instansi / Kontak -->
                            <td>
                                <div class="space-y-1">
                                    <div class="font-semibold text-sm text-base-content">
                                        {!! $hl($card->contact?->name ?? $card->title, $this->search) !!}
                                        @if ($card->contact?->is_ql)
                                            <span class="badge badge-success badge-xs font-bold ml-1 text-white text-[9px] rounded">QL</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-base-content/50">
                                        {!! $hl($card->contact?->opd?->name ?? '-', $this->search) !!}
                                    </div>
                                    <div class="flex items-center gap-1 text-[11px] text-base-content/40">
                                        <x-icon name="o-map-pin" class="w-3.5 h-3.5" />
                                        <span>{{ $card->contact?->regency?->name ?? '-' }},
                                            {{ $card->contact?->regency?->province?->name ?? '-' }}
                                            ({{ $card->contact?->region?->name ?? '-' }})</span>
                                    </div>
                                    @if ($card->employee?->user)
                                        <div class="flex items-center gap-1 text-[11px] text-indigo-500/70">
                                            <x-icon name="o-user-circle" class="w-3.5 h-3.5" />
                                            <span>PMO: {{ $card->employee->user->name }}</span>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Potensi Produk -->
                            <td>
                                <span class="text-xs font-medium text-base-content/90">
                                    {!! $hl($card->title, $this->search) !!}
                                </span>
                            </td>

                            <!-- Anggaran -->
                            <td>
                                <span class="font-semibold text-xs text-indigo-600 dark:text-indigo-400">
                                    Rp {{ number_format($card->nominal_value, 0, ',', '.') }}
                                </span>
                            </td>

                            <!-- Tanggal & Metode -->
                            <td>
                                <div class="space-y-1 text-xs">
                                    <div>
                                        {{ $card->pitching_date ? $card->pitching_date->format('d M Y') : ($card->created_at ? $card->created_at->format('d M Y') : date('d M Y')) }}
                                    </div>
                                    <div class="text-[10px] text-base-content/50 font-mono">
                                        {{ $card->communication_method ?: 'Online' }}</div>
                                </div>
                            </td>

                            <!-- Sumber -->
                            <td>
                                <span class="badge badge-neutral badge-sm font-semibold rounded text-[10px]">
                                    {{ $card->sumber_proyeksi }}
                                </span>
                            </td>

                            <!-- Proyeksi Closing -->
                            <td>
                                <div class="space-y-0.5 text-xs text-base-content/80">
                                    <div class="font-medium">{{ $card->projection_month ? Carbon\Carbon::parse(\strlen($card->projection_month) <= 7 ? $card->projection_month . '-01' : $card->projection_month)->translatedFormat('F Y') : '-' }}</div>
                                    <div class="text-[10px] text-base-content/50 font-mono">{{ $card->projection_week }}</div>
                                </div>
                            </td>

                            <!-- Status Proyeksi -->
                            <td>
                                @if ($card->status === 'won')
                                    <span class="badge badge-success text-white badge-sm font-semibold rounded text-[10px]">WON</span>
                                @elseif ($card->status === 'hot')
                                    <span class="badge bg-orange-500 text-white border-orange-500 badge-sm font-semibold rounded text-[10px] cursor-pointer" wire:click="openHotUpdate({{ $card->id }})">HOT</span>
                                @elseif ($card->status === 'warm')
                                    <span class="badge bg-blue-500 text-white border-blue-500 badge-sm font-semibold rounded text-[10px]">WARM</span>
                                @elseif ($card->status === 'lost')
                                    <span class="badge badge-error text-white badge-sm font-semibold rounded text-[10px]">LOST</span>
                                @else
                                    <span class="badge badge-ghost badge-sm font-semibold rounded text-[10px]">{{ strtoupper($card->status) }}</span>
                                @endif
                            </td>

                            <!-- Verif Manager -->
                            <td class="text-center">
                                @if ($this->canVerify)
                                    <input type="checkbox" class="toggle toggle-primary toggle-sm"
                                        wire:click="toggleVerification({{ $card->id }})"
                                        {{ $card->manager_verified ? 'checked' : '' }} />
                                @else
                                    @if ($card->manager_verified)
                                        <x-icon name="o-check-circle" class="w-5 h-5 text-blue-500 inline-block" />
                                    @else
                                        <span class="text-xs text-base-content/30">-</span>
                                    @endif
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <x-button icon="o-magnifying-glass" class="btn-ghost btn-xs text-primary"
                                        wire:click="viewCard({{ $card->id }})" />
                                    @if (!$card->manager_verified || $this->canVerify)
                                        <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-info"
                                            wire:click="editCard({{ $card->id }})" />
                                    @else
                                        <div class="tooltip tooltip-left" data-tip="Sudah diverifikasi manager, tidak dapat diedit">
                                            <x-button icon="o-pencil-square" class="btn-ghost btn-xs opacity-30 cursor-not-allowed pointer-events-none" />
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-8 text-xs text-base-content/40">
                                Belum ada prospek pitching yang terealisasi (deal).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->cards->links() }}
        </div>
    </div>

    <!-- Edit Modal -->
    <x-modal wire:model="showEditModal" class="z-[9999]" title="Edit Prospek Realisasi"
        subtitle="Ubah detail informasi prospek realisasi.">
        <form wire:submit.prevent="saveEdit" class="space-y-4">
            <x-input label="Potensi (Produk / Output)" wire:model="editTitle" required />
            <x-input label="Anggaran (Rp)" wire:model="editBudget" type="number" required />
            <x-input label="Tanggal Pitching" wire:model="editDate" type="date" required />
            
            <div class="grid grid-cols-2 gap-4">
                <x-select label="Metode" wire:model="editMethod" :options="[['id' => 'Online', 'name' => 'Online'], ['id' => 'Offline', 'name' => 'Offline']]" />
                <x-select label="Sumber Potensi" wire:model="editSumber" :options="[['id' => 'SIRUP', 'name' => 'SIRUP'], ['id' => 'SIPD', 'name' => 'SIPD'], ['id' => 'INAPROC', 'name' => 'INAPROC'], ['id' => 'ANALISIS PS', 'name' => 'ANALISIS PS'], ['id' => 'PERMINTAAN MITRA', 'name' => 'PERMINTAAN MITRA']]" />
            </div>

            <x-select label="Status Realisasi" wire:model.live="editStatus" :options="[
                ['id' => 'won', 'name' => 'Won (Deal)'],
                ['id' => 'hot', 'name' => 'Hot Prospect'],
                ['id' => 'warm', 'name' => 'Warm Prospect (Dipindah ke Rencana)'],
                ['id' => 'lost', 'name' => 'Lost (Negatif / Gagal)']
            ]" />

            @if (in_array($editStatus, ['won', 'hot', 'warm']))
                @php
                    $months = [];
                    $current = Carbon\Carbon::now()->startOfMonth();
                    for ($i = 0; $i < 12; $i++) {
                        $months[] = [
                            'id' => $current->format('Y-m'),
                            'name' => $current->translatedFormat('F Y'),
                        ];
                        $current->addMonth();
                    }
                    $weeks = [
                        ['id' => 'Pekan 1', 'name' => 'Pekan 1'],
                        ['id' => 'Pekan 2', 'name' => 'Pekan 2'],
                        ['id' => 'Pekan 3', 'name' => 'Pekan 3'],
                        ['id' => 'Pekan 4', 'name' => 'Pekan 4'],
                        ['id' => 'Pekan 5', 'name' => 'Pekan 5'],
                    ];
                    $projStatuses = [
                        ['id' => 'Optimis', 'name' => 'Optimis'],
                        ['id' => 'Moderate', 'name' => 'Moderate'],
                    ];
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-select label="Bulan Proyeksi Realisasi" wire:model="editProjectionMonth" :options="$months" />
                    <x-select label="Pekan Proyeksi" wire:model="editProjectionWeek" :options="$weeks" />
                    <x-select label="Status Proyeksi" wire:model="editProjectionStatus" :options="$projStatuses" />
                    <div class="col-span-2">
                        <x-input label="Link Google Drive Dokumen" wire:model="editDriveLink" placeholder="https://drive.google.com/..." />
                    </div>
                </div>
            @else
                @php
                    $negReasons = [
                        ['id' => 'No Budget', 'name' => 'No Budget (Tidak ada anggaran)'],
                        ['id' => 'No Authority', 'name' => 'No Authority (Tidak ada wewenang penentu kebijakan)'],
                        ['id' => 'No Need', 'name' => 'No Need (Belum memerlukan produk terkait)'],
                        ['id' => 'Lost to Competitor', 'name' => 'Lost to Competitor (Kalah saing dengan kompetitor)'],
                    ];
                @endphp
                <x-select label="Alasan Negatif / Lost" wire:model="editNegativeReason" :options="$negReasons" />
            @endif

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-base-300">
                <x-button label="Batal" wire:click="$set('showEditModal', false)" class="btn-ghost" />
                <x-button label="Simpan Perubahan" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Slide-over Drawer Detail Pitching -->
    <div x-data="{ show: @entangle('showDetailDrawer') }" x-show="show" class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
        <!-- Backdrop -->
        <div x-show="show" 
            x-transition:enter="ease-in-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-zinc-900/50 backdrop-blur-sm transition-opacity" 
            @click="show = false"></div>

        <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
            <!-- Slide-over Panel -->
            <div x-show="show" 
                x-transition:enter="transform transition ease-in-out duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in-out duration-300"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="w-screen max-w-lg bg-base-100 shadow-2xl border-l border-base-300 flex flex-col justify-between">
                
                @if ($selectedCardForDetail)
                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Drawer Header -->
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-base-content">Detail Prospek Realisasi</h3>
                            <p class="text-xs text-base-content/50">Informasi lengkap dan riwayat aktivitas</p>
                        </div>
                        <button @click="show = false" class="text-base-content/40 hover:text-base-content/50">
                            <x-icon name="o-x-mark" class="w-6 h-6" />
                        </button>
                    </div>

                    <hr class="border-base-300" />

                    <!-- Info Section -->
                    <div class="space-y-4 text-sm">
                        <h4 class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Informasi Kontak & Instansi</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <span class="text-base-content/40 block text-xs">Instansi / OPD</span>
                                <span class="font-semibold text-base-content">{{ $selectedCardForDetail->contact?->opd?->name ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="text-base-content/40 block text-xs">Wilayah / Region</span>
                                <span class="font-medium text-base-content/90">{{ $selectedCardForDetail->contact?->regency?->name ?? '-' }}, {{ $selectedCardForDetail->contact?->regency?->province?->name ?? '-' }} ({{ $selectedCardForDetail->contact?->region?->name ?? '-' }})</span>
                            </div>
                            <div>
                                <span class="text-base-content/40 block text-xs">Nama Kontak</span>
                                <span class="font-semibold text-base-content">{{ $selectedCardForDetail->contact?->name ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="text-base-content/40 block text-xs">Jabatan & Telepon</span>
                                <span class="font-medium text-base-content/90">{{ $selectedCardForDetail->contact?->contactPosition?->name ?? 'Staf' }} ({{ $selectedCardForDetail->contact?->phone ?? '-' }})</span>
                            </div>
                            <div>
                                <span class="text-base-content/40 block text-xs">Staff PMO</span>
                                <span class="font-medium text-indigo-600 dark:text-indigo-400">{{ $selectedCardForDetail->employee?->user?->name ?? '-' }}</span>
                            </div>
                        </div>

                        <hr class="border-base-300" />

                        <h4 class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Detail Realisasi</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <span class="text-base-content/40 block text-xs">Potensi (Produk / Output)</span>
                                <span class="font-bold text-base text-indigo-600 dark:text-indigo-400">{{ $selectedCardForDetail->title }}</span>
                            </div>
                            <div>
                                <span class="text-base-content/40 block text-xs">Nilai Anggaran</span>
                                <span class="font-bold text-base-content">Rp {{ number_format($selectedCardForDetail->nominal_value, 0, ',', '.') }}</span>
                            </div>
                            <div>
                                <span class="text-base-content/40 block text-xs">Tanggal Pitching</span>
                                <span class="font-medium text-base-content/90">{{ $selectedCardForDetail->pitching_date ? $selectedCardForDetail->pitching_date->format('d M Y') : '-' }}</span>
                            </div>
                            <div>
                                <span class="text-base-content/40 block text-xs">Metode & Sumber</span>
                                <span class="font-medium text-base-content/90">{{ $selectedCardForDetail->communication_method ?: 'Online' }} ({{ $selectedCardForDetail->sumber_proyeksi }})</span>
                            </div>
                        </div>

                        <hr class="border-base-300" />
                        <h4 class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Proyeksi Closing & Hasil</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <span class="text-base-content/40 block text-xs">Status Proyeksi</span>
                                @if ($selectedCardForDetail->status === 'won')
                                    <span class="badge badge-success text-white badge-sm font-semibold rounded text-[10px]">WON</span>
                                @elseif ($selectedCardForDetail->status === 'hot')
                                    <span class="badge bg-orange-500 text-white border-orange-500 badge-sm font-semibold rounded text-[10px]">HOT</span>
                                @elseif ($selectedCardForDetail->status === 'warm')
                                    <span class="badge bg-blue-500 text-white border-blue-500 badge-sm font-semibold rounded text-[10px]">WARM</span>
                                @elseif ($selectedCardForDetail->status === 'lost')
                                    <span class="badge badge-error text-white badge-sm font-semibold rounded text-[10px]">LOST</span>
                                @else
                                    <span class="badge badge-ghost badge-sm font-semibold rounded text-[10px]">{{ strtoupper($selectedCardForDetail->status) }}</span>
                                @endif
                            </div>
                            <div>
                                <span class="text-base-content/40 block text-xs">Bulan Proyeksi Realisasi</span>
                                <span class="font-medium text-base-content/90">{{ $selectedCardForDetail->projection_month ? Carbon\Carbon::parse(\strlen($selectedCardForDetail->projection_month) <= 7 ? $selectedCardForDetail->projection_month . '-01' : $selectedCardForDetail->projection_month)->translatedFormat('F Y') : '-' }}</span>
                            </div>
                            <div>
                                <span class="text-base-content/40 block text-xs">Pekan Proyeksi</span>
                                <span class="font-medium text-base-content/90">{{ $selectedCardForDetail->projection_week ?? '-' }}</span>
                            </div>
                            @if (!empty($selectedCardForDetail->drive_link))
                                <div class="col-span-2">
                                    <span class="text-base-content/40 block text-xs">Link Dokumen</span>
                                    <a href="{{ $selectedCardForDetail->drive_link }}" target="_blank" class="inline-flex items-center gap-1 text-xs underline text-indigo-600 dark:text-indigo-400 font-medium mt-1">
                                        <x-icon name="o-document-text" class="w-4 h-4" />
                                        Buka Google Drive Dokumen
                                    </a>
                                </div>
                            @endif
                        </div>

                        <hr class="border-base-300" />

                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-base-content/40 block text-xs">Verifikasi Manager</span>
                                @if ($selectedCardForDetail->manager_verified)
                                    <span class="badge badge-success badge-sm font-semibold rounded text-[10px] gap-1">
                                        <x-icon name="o-check-circle" class="w-3.5 h-3.5" />
                                        VERIFIED
                                    </span>
                                @else
                                    <span class="badge badge-warning badge-sm font-semibold rounded text-[10px]">
                                        PENDING VERIFICATION
                                    </span>
                                @endif
                            </div>
                        </div>

                        <hr class="border-base-300" />

                        <!-- Follow Up Section -->
                        <h4 class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Aktivitas Follow Up PMO Pratama</h4>
                        
                        <!-- List Follow Up -->
                        @if (empty($selectedCardForDetail->follow_ups))
                            <p class="text-xs text-base-content/40 italic">Belum ada catatan follow up.</p>
                        @else
                            <div class="space-y-3 mt-2">
                                @foreach (array_reverse($selectedCardForDetail->follow_ups) as $fu)
                                    <div class="p-3 rounded-xl bg-base-200 border border-base-300 space-y-1.5">
                                        <div class="flex items-center justify-between text-xs text-base-content/50">
                                            <span class="font-bold text-base-content/80">{{ $fu['user'] ?? 'System' }}</span>
                                            <span>{{ ($fu['timestamp'] ?? $fu['date'] ?? null) ? \Carbon\Carbon::parse($fu['timestamp'] ?? $fu['date'])->translatedFormat('d M Y H:i') : '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="badge badge-info badge-sm font-semibold rounded text-[10px] text-white">{{ $fu['tujuan'] }}</span>
                                        </div>
                                        <p class="text-xs text-base-content/70 whitespace-pre-line leading-relaxed">
                                            {{ $fu['hasil'] }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Form Follow Up -->
                        <div class="p-4 rounded-xl border border-dashed border-base-300 bg-base-50/50 space-y-3 mt-4">
                            <span class="text-xs font-bold text-base-content block">Catat Follow Up Baru</span>
                            <div class="space-y-2">
                                <x-select label="Tujuan Follow Up" wire:model="followUpTujuan" :options="[
                                    ['id' => 'Penawaran', 'name' => 'Penawaran'],
                                    ['id' => 'Koordinasi Audiensi', 'name' => 'Koordinasi Audiensi'],
                                    ['id' => 'Koordinasi Administrasi', 'name' => 'Koordinasi Administrasi'],
                                    ['id' => 'Pengawalan Kegiatan', 'name' => 'Pengawalan Kegiatan'],
                                    ['id' => 'Koordinasi CPDS', 'name' => 'Koordinasi CPDS'],
                                    ['id' => 'Negosiasi Harga', 'name' => 'Negosiasi Harga'],
                                    ['id' => 'Negosiasi Kesepakatan Output', 'name' => 'Negosiasi Kesepakatan Output'],
                                    ['id' => 'Review Kontrak', 'name' => 'Review Kontrak']
                                ]" />
                                
                                <x-textarea label="Hasil Follow Up" wire:model="followUpHasil" placeholder="Tulis hasil kegiatan follow up..." rows="3" />
                                
                                <div class="flex justify-end pt-1">
                                    <x-button label="Simpan Follow Up" wire:click="addFollowUp({{ $selectedCardForDetail->id }})" class="btn-primary btn-sm" icon="o-plus" />
                                </div>
                            </div>
                        </div>

                        <hr class="border-base-300" />

                        <!-- History Section -->
                        <h4 class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Riwayat Perubahan & Aktivitas</h4>
                        @if (empty($selectedCardForDetail->history_logs))
                            <p class="text-xs text-base-content/40 italic">Belum ada riwayat aktivitas tercatat.</p>
                        @else
                            <div class="space-y-4 mt-2">
                                @foreach (array_reverse($selectedCardForDetail->history_logs) as $log)
                                    <div class="relative pl-6 pb-2 border-l border-indigo-100 dark:border-indigo-900 last:border-0 last:pb-0">
                                        <!-- Timeline dot -->
                                        <span class="absolute left-[-5px] top-1.5 w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                        
                                        <div class="flex items-center justify-between text-xs text-base-content/50">
                                            <span class="font-semibold text-base-content">{{ $log['user'] ?? 'System' }}</span>
                                            <span>{{ ($log['timestamp'] ?? $log['date'] ?? null) ? \Carbon\Carbon::parse($log['timestamp'] ?? $log['date'])->translatedFormat('d M Y H:i') : '-' }}</span>
                                        </div>
                                        <div class="text-xs font-medium text-base-content/90 mt-0.5">
                                            {{ ucwords(str_replace('_', ' ', $log['action'] ?? 'action')) }}
                                        </div>
                                        @if (!empty($log['details']))
                                            <div class="text-[11px] text-base-content/60 mt-0.5 italic">
                                                {{ $log['details'] }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <!-- Footer -->
                <div class="p-6 border-t border-base-300 bg-base-100 flex justify-end">
                    <x-button label="Tutup Detail" @click="show = false" class="btn-ghost" />
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Terlaksana / Hot Update Modal -->
    <x-modal wire:model="showTerlaksanaModal" class="z-[9999]" :title="$terlaksanaModalTitle"
        subtitle="Tentukan hasil akhir pitching dengan {{ $pendingDecisionCard['contact_name'] ?? '' }}.">
        <form wire:submit.prevent="saveTerlaksanaDecision" class="space-y-4">
            <div>
                <span class="label-text font-semibold mb-2 block">Hasil Akhir Kerja Sama</span>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model.live="decisionType" value="won"
                            class="radio radio-success" />
                        <span class="text-sm font-medium">Won (Deal)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model.live="decisionType" value="hot"
                            class="radio checked:bg-orange-500 checked:border-orange-500" />
                        <span class="text-sm font-medium">Hot Prospect</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model.live="decisionType" value="warm"
                            class="radio radio-info" />
                        <span class="text-sm font-medium">Warm Prospect</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model.live="decisionType" value="lost"
                            class="radio radio-error" />
                        <span class="text-sm font-medium">Lost (Negatif)</span>
                    </label>
                </div>
            </div>

            @if (in_array($decisionType, ['won', 'hot', 'warm']))
                @php
                    $months = [];
                    $current = Carbon\Carbon::now()->startOfMonth();
                    for ($i = 0; $i < 12; $i++) {
                        $months[] = [
                            'id' => $current->format('Y-m'),
                            'name' => $current->translatedFormat('F Y'),
                        ];
                        $current->addMonth();
                    }
                    $weeks = [
                        ['id' => 'Pekan 1', 'name' => 'Pekan 1'],
                        ['id' => 'Pekan 2', 'name' => 'Pekan 2'],
                        ['id' => 'Pekan 3', 'name' => 'Pekan 3'],
                        ['id' => 'Pekan 4', 'name' => 'Pekan 4'],
                        ['id' => 'Pekan 5', 'name' => 'Pekan 5'],
                    ];
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-select label="Bulan Proyeksi Realisasi" wire:model="projectionMonth" :options="$months" />
                    <x-select label="Pekan Proyeksi" wire:model="projectionWeek" :options="$weeks" />
                    <div class="col-span-2">
                        <x-input label="Link Google Drive Dokumen" wire:model="driveLink"
                            placeholder="https://drive.google.com/..." />
                    </div>
                </div>
            @else
                @php
                    $negReasons = [
                        ['id' => 'No Budget', 'name' => 'No Budget (Tidak ada anggaran)'],
                        ['id' => 'No Authority', 'name' => 'No Authority (Tidak ada wewenang penentu kebijakan)'],
                        ['id' => 'No Need', 'name' => 'No Need (Belum memerlukan produk terkait)'],
                        ['id' => 'Lost to Competitor', 'name' => 'Lost to Competitor (Kalah saing dengan kompetitor)'],
                    ];
                @endphp
                <x-select label="Alasan Negatif / Lost" wire:model="negativeReason" :options="$negReasons" />
            @endif

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-base-300">
                <x-button label="Batal" wire:click="$set('showTerlaksanaModal', false)" class="btn-ghost" />
                <x-button label="Simpan Hasil" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Confirmation Modal Won -->
    <x-modal wire:model="showWonConfirmModal" class="z-[9999]" title="Konfirmasi Deal (Won)"
        subtitle="Apakah Anda yakin ingin menyepakati kerja sama ini?">
        <div class="space-y-4">
            <p class="text-sm text-base-content/70">
                Tindakan ini akan menetapkan prospek ini ke status <strong>Won (Deal)</strong>.
            </p>
            <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                <x-button label="Batal" wire:click="$set('showWonConfirmModal', false)" class="btn-ghost" />
                <x-button label="Ya, Deal!" wire:click="confirmWonAction" class="btn-success text-white" />
            </div>
        </div>
    </x-modal>

    <!-- Confetti & Event Listener -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('show-confetti', () => {
                if (typeof confetti === 'function') {
                    // Center blast
                    confetti({
                        particleCount: 150,
                        spread: 80,
                        origin: { y: 0.6 }
                    });
                    // Left cannon
                    confetti({
                        particleCount: 80,
                        angle: 60,
                        spread: 55,
                        origin: { x: 0, y: 0.8 }
                    });
                    // Right cannon
                    confetti({
                        particleCount: 80,
                        angle: 120,
                        spread: 55,
                        origin: { x: 1, y: 0.8 }
                    });
                }
            });
        });
    </script>
</div>
