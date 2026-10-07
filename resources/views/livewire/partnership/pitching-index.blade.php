<?php

use function Livewire\Volt\{state, layout, computed, usesPagination, on, uses};
use App\Notifications\DealClosedNotification;

usesPagination();

state(['perPage' => 10]);
$updatedPerPage = fn () => $this->resetPage();
layout('layouts.app');
uses([\Mary\Traits\Toast::class]);

state([
    'pendingDecisionCard' => null,
    'decisionType' => 'hot',
    'projectionMonth' => '',
    'projectionWeek' => 'Pekan 1',
    'driveLink' => '',
    'negativeReason' => 'No Budget',
    'showTerlaksanaModal' => false,
    'showTidakTerlaksanaModal' => false,
    'tidakTerlaksanaReason' => 'reschedule - bureaucracy',
    'rescheduleOption' => 'now',
    'newPitchingDate' => '',

    // Detail Drawer State
    'showDetailDrawer' => false,
    'selectedCardForDetail' => null,

    // Filters
    'filterFiscalYear' => (string) date('Y'),
    'filterSumberProyeksi' => '',
    'filterStageId' => '',
    'filterStatus' => '',
    'filterManagerVerified' => '',
    'showRescheduled' => false,
    'search' => '',

    // Edit Modal State
    'editingCard' => null,
    'showEditModal' => false,
    'editTitle' => '',
    'editBudget' => '',
    'editDate' => '',
    'editMethod' => 'Online',
    'editSumber' => 'SIRUP',
    'editStatus' => 'warm',
    'editProjectionMonth' => '',
    'editProjectionWeek' => 'Pekan 1',
    'editDriveLink' => '',

    // Follow Up State
    'followUpTujuan' => 'Penawaran',
    'followUpHasil' => '',
    'terlaksanaModalTitle' => 'Hasil Pitching Terlaksana',
    'showWonConfirmModal' => false,
    'wonConfirmType' => 'terlaksana',
]);

\Livewire\Volt\mount(function () {
    $this->projectionMonth = date('Y-m');
});

// Refresh component when a new card is added
on([
    'card-added' => function () {
        // Triggers refresh automatically
    },
]);

$stageOptions = computed(function () {
    $activeProfile = \App\Models\PipelineProfile::where('pipeline_name', 'Pitching')->where('is_active', true)->first();
    return $activeProfile ? $activeProfile->stages()->where('is_active', true)->orderBy('order_index')->get() : collect();
});

$cards = computed(function () {
    $user = auth()->user();
    $employee = $user->employee;
    $isSpv = $user->hasRole('Partnership Admin');
    $spvRegionId = $isSpv ? $employee?->region_id : null;

    return \App\Models\Card::forPitching()
        ->where('fiscal_year', (int) $this->filterFiscalYear)
        ->when(!$this->canVerify && $isSpv && $spvRegionId, fn($q) => $q->whereHas('employee', fn($e) => $e->where('region_id', $spvRegionId)))
        ->when(!$this->canVerify && !$isSpv && $employee, fn($q) => $q->where('employee_id', $employee->id))
        ->when($this->filterSumberProyeksi, fn($q) => $q->where('sumber_proyeksi', $this->filterSumberProyeksi))
        ->when($this->filterStageId, fn($q) => $q->where('stage_id', $this->filterStageId))
        ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
        ->when($this->filterManagerVerified !== '', fn($q) => $q->where('manager_verified', $this->filterManagerVerified === '1'))
        ->when(!$this->showRescheduled, function ($q) {
            $q->where(function ($sub) {
                $sub->whereNull('negative_reason')->orWhere('negative_reason', 'not like', 'reschedule%');
            });
        })
        ->when($this->search, function ($q) {
            $q->where(function ($sub) {
                $sub->where('title', 'ilike', '%' . $this->search . '%')
                    ->orWhereHas('contact', function ($c) {
                        $c->where('name', 'ilike', '%' . $this->search . '%')
                            ->orWhereHas('opd', function ($o) {
                                $o->where('name', 'ilike', '%' . $this->search . '%');
                            });
                    })
                    ->orWhereHas('employee.user', function ($u) {
                        $u->where('name', 'ilike', '%' . $this->search . '%');
                    });
            });
        })
        ->where(function ($q) {
            $q->where('status', '!=', 'won')
              ->orWhere('manager_verified', false);
        })
        ->with(['stage', 'contact.regency.province', 'contact.opd', 'contact.region', 'employee.user'])
        ->orderBy('created_at', 'desc')
        ->paginate(\App\Support\PerPage::resolve($this->perPage));
});

$resetFilters = function () {
    $this->filterFiscalYear = (string) date('Y');
    $this->filterSumberProyeksi = '';
    $this->filterStageId = '';
    $this->filterStatus = '';
    $this->filterManagerVerified = '';
    $this->showRescheduled = false;
    $this->search = '';
    $this->resetPage();
};

$updatedSearch = function () {
    $this->resetPage();
};

$updatedFilterSumberProyeksi = function () {
    $this->resetPage();
};

$updatedFilterStageId = function () {
    $this->resetPage();
};

$updatedFilterStatus = function () {
    $this->resetPage();
};

$updatedFilterManagerVerified = function () {
    $this->resetPage();
};

$updatedShowRescheduled = function () {
    $this->resetPage();
};

$moveCard = function (int $cardId, string $newStageName) {
    $activeProfile = \App\Models\PipelineProfile::where('pipeline_name', 'Pitching')->where('is_active', true)->first();

    if (!$activeProfile) {
        return;
    }

    $stage = $activeProfile->stages()->where('stage_name', $newStageName)->first();
    if (!$stage) {
        return;
    }

    $card = \App\Models\Card::findOrFail($cardId);

    if ($newStageName === 'Terlaksana') {
        $this->pendingDecisionCard = [
            'id' => $card->id,
            'contact_name' => $card->contact ? $card->contact->name : $card->title,
        ];
        $this->decisionType = 'hot';
        $this->terlaksanaModalTitle = 'Hasil Pitching Terlaksana';
        $this->showTerlaksanaModal = true;
        return;
    }

    if ($newStageName === 'Tidak Terlaksana') {
        $this->pendingDecisionCard = [
            'id' => $card->id,
            'contact_name' => $card->contact ? $card->contact->name : $card->title,
            'pitching_date' => $card->pitching_date ? $card->pitching_date->format('Y-m-d') : date('Y-m-d'),
        ];
        $this->tidakTerlaksanaReason = 'reschedule - bureaucracy';
        $this->rescheduleOption = 'now';
        $this->newPitchingDate = date('Y-m-d', strtotime(($card->pitching_date ? $card->pitching_date->format('Y-m-d') : date('Y-m-d')) . ' +7 days'));
        $this->showTidakTerlaksanaModal = true;
        return;
    }

    $oldStageName = $card->stage?->stage_name ?? 'Unknown';
    $card->update([
        'stage_id' => $stage->id,
        'status' => 'Active',
    ]);
    $card->addHistoryLog('stage_changed', "Stage changed from {$oldStageName} to {$newStageName}");
};

$saveTerlaksanaDecision = function () {
    if (!$this->pendingDecisionCard) {
        return;
    }

    $card = \App\Models\Card::findOrFail($this->pendingDecisionCard['id']);

    if (in_array($this->decisionType, ['won', 'hot', 'warm'])) {
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

    $activeProfile = \App\Models\PipelineProfile::where('pipeline_name', 'Pitching')->where('is_active', true)->first();
    if (!$activeProfile) {
        return;
    }

    $targetStage = $activeProfile->stages()->where('stage_name', 'Terlaksana')->first();
    if (!$targetStage) {
        return;
    }

    $card = \App\Models\Card::findOrFail($this->pendingDecisionCard['id']);

    if (in_array($this->decisionType, ['won', 'hot', 'warm'])) {
        $card->update([
            'stage_id' => $targetStage->id,
            'status' => $this->decisionType,
            'projection_month' => $this->projectionMonth,
            'projection_week' => $this->projectionWeek,
            'drive_link' => $this->driveLink,
            'negative_reason' => null,
            'manager_verified' => $this->canVerify,
        ]);
        $card->addHistoryLog('stage_changed', "Stage changed to Terlaksana with status {$this->decisionType}");

        if ($this->decisionType === 'won') {
            DealClosedNotification::sendToManagers($card);
            $this->dispatch('show-confetti');
            $this->success('Selamat! Kerja sama telah berhasil disepakati (Won / Deal)! 🥳🎉');
        } else {
            $this->success('Keputusan Terlaksana berhasil disimpan!');
        }
    } else {
        $card->update([
            'stage_id' => $targetStage->id,
            'status' => 'lost',
            'negative_reason' => $this->negativeReason,
            'projection_month' => null,
            'projection_week' => null,
            'drive_link' => null,
            'manager_verified' => false,
        ]);
        $card->addHistoryLog('stage_changed', "Stage changed to Terlaksana with status lost (reason: {$this->negativeReason})");
        $this->success('Keputusan Terlaksana berhasil disimpan!');
    }

    $this->showTerlaksanaModal = false;
    $this->pendingDecisionCard = null;
    $this->driveLink = '';
};

$saveTidakTerlaksanaDecision = function () {
    if (!$this->pendingDecisionCard) {
        return;
    }

    $activeProfile = \App\Models\PipelineProfile::where('pipeline_name', 'Pitching')->where('is_active', true)->first();

    if (!$activeProfile) {
        return;
    }

    $targetStage = $activeProfile->stages()->where('stage_name', 'Tidak Terlaksana')->first();
    if (!$targetStage) {
        return;
    }

    $card = \App\Models\Card::findOrFail($this->pendingDecisionCard['id']);

    $this->validate([
        'tidakTerlaksanaReason' => ['required', 'in:reschedule - bureaucracy,canceled - unresponsive,canceled - internal issue'],
    ]);

    $card->update([
        'stage_id' => $targetStage->id,
        'status' => 'lost',
        'negative_reason' => $this->tidakTerlaksanaReason,
        'manager_verified' => false,
    ]);
    $card->addHistoryLog('stage_changed', "Stage changed to Tidak Terlaksana (Lost, reason: {$this->tidakTerlaksanaReason})");

    // Handle rescheduling / duplication
    if (str_starts_with($this->tidakTerlaksanaReason, 'reschedule')) {
        $rescheduleDate = $this->rescheduleOption === 'now' ? $this->newPitchingDate : date('Y-m-d', strtotime(($card->pitching_date ? $card->pitching_date->format('Y-m-d') : date('Y-m-d')) . ' +7 days'));

        $rencanaStage = $activeProfile->stages()->where('stage_name', 'Rencana')->first();
        if ($rencanaStage) {
            $newCard = \App\Models\Card::create([
                'title' => $card->title,
                'stage_id' => $rencanaStage->id,
                'contact_id' => $card->contact_id,
                'nominal_value' => $card->nominal_value,
                'status' => 'Active',
                'sumber_proyeksi' => $card->sumber_proyeksi,
                'pitching_date' => $rescheduleDate,
                'communication_method' => $card->communication_method,
                'fiscal_year' => $card->fiscal_year,
            ]);
            $newCard->addHistoryLog('created', "Prospect created via reschedule from unfulfilled card #{$card->id}");
        }
    }

    $this->showTidakTerlaksanaModal = false;
    $this->pendingDecisionCard = null;

    $this->success('Keputusan Tidak Terlaksana berhasil disimpan!');
};

$pitchingStats = computed(function () {
    $user = auth()->user();
    $employee = $user->employee;
    $isSpv = $user->hasRole('Partnership Admin');
    $spvRegionId = $isSpv ? $employee?->region_id : null;

    $baseQuery = \App\Models\Card::forPitching()
        ->where('fiscal_year', (int) $this->filterFiscalYear)
        ->when(!$this->canVerify && $isSpv && $spvRegionId, fn($q) => $q->whereHas('employee', fn($e) => $e->where('region_id', $spvRegionId)))
        ->when(!$this->canVerify && !$isSpv && $employee, fn($q) => $q->where('employee_id', $employee->id))
        ->when($this->filterSumberProyeksi, fn($q) => $q->where('sumber_proyeksi', $this->filterSumberProyeksi))
        ->when($this->filterStageId, fn($q) => $q->where('stage_id', $this->filterStageId))
        ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
        ->when($this->filterManagerVerified !== '', fn($q) => $q->where('manager_verified', $this->filterManagerVerified === '1'))
        ->when(!$this->showRescheduled, function ($q) {
            $q->where(function ($sub) {
                $sub->whereNull('negative_reason')->orWhere('negative_reason', 'not like', 'reschedule%');
            });
        })
        ->when($this->search, function ($q) {
            $q->where(function ($sub) {
                $sub->where('title', 'ilike', '%' . $this->search . '%')
                    ->orWhereHas('contact', function ($c) {
                        $c->where('name', 'ilike', '%' . $this->search . '%')
                            ->orWhereHas('opd', function ($o) {
                                $o->where('name', 'ilike', '%' . $this->search . '%');
                            });
                    })
                    ->orWhereHas('employee.user', function ($u) {
                        $u->where('name', 'ilike', '%' . $this->search . '%');
                    });
            });
        })
        ->where(function ($q) {
            $q->where('status', '!=', 'won')
              ->orWhere('manager_verified', false);
        })
        ->with('stage');

    $allCards = $baseQuery->get();
    
    $totalCount = $allCards->count();
    $rencanaCount = $allCards->filter(fn($c) => $c->stage?->stage_name === 'Rencana')->count();
    $terlaksanaCount = $allCards->filter(fn($c) => $c->stage?->stage_name === 'Terlaksana')->count();
    $tidakTerlaksanaCount = $allCards->filter(fn($c) => $c->stage?->stage_name === 'Tidak Terlaksana')->count();
    
    $totalBudget = $allCards->sum('nominal_value');

    $completedCount = $allCards->filter(fn($c) => in_array($c->status, ['won', 'hot', 'warm']))->count();
    $negatifCount = $allCards->filter(fn($c) => $c->status === 'lost' && $c->stage?->stage_name === 'Terlaksana')->count();
    
    $conversionRate = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0.0;

    $verifiedCount = $allCards->filter(fn($c) => $c->manager_verified)->count();
    $unverifiedCount = $totalCount - $verifiedCount;

    return [
        'rencana_count' => $rencanaCount,
        'terlaksana_count' => $terlaksanaCount,
        'tidak_terlaksana_count' => $tidakTerlaksanaCount,
        'total_budget' => $totalBudget,
        'conversion_rate' => $conversionRate,
        'completed_count' => $completedCount,
        'negatif_count' => $negatifCount,
        'verified_count' => $verifiedCount,
        'unverified_count' => $unverifiedCount,
        'total_count' => $totalCount,
    ];
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
        $this->error('Hanya Manager atau Super Admin yang dapat memverifikasi.');
        return;
    }

    $card = \App\Models\Card::findOrFail($cardId);
    $card->update([
        'manager_verified' => !$card->manager_verified,
    ]);

    $action = $card->manager_verified ? 'verified' : 'unverified';
    $card->addHistoryLog('verification_changed', "Manager {$action} the prospect");

    if ($card->manager_verified && $card->status === 'won') {
        $this->dispatch('show-confetti');
        $this->success('Kerja sama (Won / Deal) berhasil diverifikasi oleh Manager! 🥳🎉');
    } else {
        $this->success($card->manager_verified ? 'Prospek berhasil diverifikasi oleh Manager!' : 'Verifikasi manager dibatalkan.');
    }
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

    $this->followUpHasil = '';
    
    // Refresh the detail drawer data
    if ($this->selectedCardForDetail && $this->selectedCardForDetail->id === $cardId) {
        $this->selectedCardForDetail = \App\Models\Card::with(['stage', 'contact.regency.province', 'contact.opd', 'contact.region'])->findOrFail($cardId);
    }

    $this->success('Catatan Follow Up berhasil disimpan!');
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
    
    $statusTitle = match($card->status) {
        'won' => 'Update Hasil Won (Deal)',
        'hot' => 'Tindak Lanjut Prospek Hot',
        'warm' => 'Tindak Lanjut Prospek Warm',
        'lost' => 'Update Hasil Lost (Negatif)',
        default => 'Update Hasil Keputusan Pitching'
    };
    $this->terlaksanaModalTitle = $statusTitle;
    $this->showTerlaksanaModal = true;
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
    $this->editDriveLink = $card->drive_link ?: '';
    $this->showEditModal = true;
};
$saveEdit = function () {
    if (!$this->editingCard) {
        return;
    }

    $this->validate([
        'editTitle' => ['required', 'string', 'max:255'],
        'editBudget' => ['required', 'numeric', 'min:1'],
        'editDate' => ['required', 'date'],
        'editMethod' => ['required', 'in:Online,Offline'],
        'editSumber' => ['required', 'string'],
        'editStatus' => ['required', 'in:Active,won,hot,warm,lost'],
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

    if (in_array($this->editStatus, ['won', 'hot', 'warm'])) {
        $activeProfile = \App\Models\PipelineProfile::where('pipeline_name', 'Pitching')->where('is_active', true)->first();
        $targetStage = $activeProfile ? $activeProfile->stages()->where('stage_name', 'Terlaksana')->first() : null;
        if ($targetStage) {
            $data['stage_id'] = $targetStage->id;
        }
        $data['projection_month'] = $this->editProjectionMonth;
        $data['projection_week'] = $this->editProjectionWeek;
        $data['drive_link'] = $this->editDriveLink;
        $data['negative_reason'] = null;
        $data['manager_verified'] = ($this->editStatus === 'won') ? $this->canVerify : false;
    } elseif ($this->editStatus === 'lost') {
        $activeProfile = \App\Models\PipelineProfile::where('pipeline_name', 'Pitching')->where('is_active', true)->first();
        $lostStage = $activeProfile ? $activeProfile->stages()->where('stage_name', 'Tidak Terlaksana')->first() : null;
        if ($lostStage) {
            $data['stage_id'] = $lostStage->id;
        }
        $data['projection_month'] = null;
        $data['projection_week'] = null;
        $data['drive_link'] = null;
        $data['negative_reason'] = 'No Budget';
        $data['manager_verified'] = false;
    }

    $card->update($data);
    $card->addHistoryLog('card_edited', "Prospect details updated by user");
    
    if ($this->editStatus === 'won') {
        if ($this->canVerify) {
            $this->dispatch('show-confetti');
            $this->success('Selamat! Kerja sama telah berhasil disepakati (Won / Deal)! 🥳🎉');
        } else {
            $this->success('Data pitching berhasil diperbarui & menunggu verifikasi manager!');
        }
    } else {
        $this->success('Data pitching berhasil diperbarui!');
    }

    $this->showEditModal = false;
    $this->editingCard = null;
};

new class {};
?>
<div class="space-y-6">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Ringkasan Prospek -->
        <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-sm rounded-2xl">
            <div>
                <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">RINGKASAN PROSPEK</h3>
                <h2 class="text-2xl mt-1 font-extrabold text-base-content">
                    {{ $this->pitchingStats['total_count'] }} Prospek
                </h2>
                <div class="text-xs text-base-content/50 mt-1">
                    Rencana: {{ $this->pitchingStats['rencana_count'] }} | Terlaksana: {{ $this->pitchingStats['terlaksana_count'] }}
                </div>
            </div>

            <!-- Stacked Horizontal Bar Chart: Status Pitching -->
            @php
                $statusTotal = max($this->pitchingStats['rencana_count'] + $this->pitchingStats['terlaksana_count'] + $this->pitchingStats['tidak_terlaksana_count'], 1);
                $terlaksanaPercent = ($this->pitchingStats['terlaksana_count'] / $statusTotal) * 100;
                $tidakPercent = ($this->pitchingStats['tidak_terlaksana_count'] / $statusTotal) * 100;
                $rencanaPercent = ($this->pitchingStats['rencana_count'] / $statusTotal) * 100;
            @endphp
            <div class="h-4 w-full bg-base-200 rounded-full overflow-hidden flex">
                <div class="h-full bg-emerald-500" style="width: {{ $terlaksanaPercent }}%" title="Terlaksana: {{ $this->pitchingStats['terlaksana_count'] }}"></div>
                <div class="h-full bg-rose-500" style="width: {{ $tidakPercent }}%" title="Tidak Terlaksana: {{ $this->pitchingStats['tidak_terlaksana_count'] }}"></div>
                <div class="h-full bg-blue-500" style="width: {{ $rencanaPercent }}%" title="Rencana: {{ $this->pitchingStats['rencana_count'] }}"></div>
            </div>

            <div class="grid grid-cols-3 gap-1 text-[11px] text-base-content/70">
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                    <span class="truncate">Terlaksana ({{ $this->pitchingStats['terlaksana_count'] }})</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span>
                    <span class="truncate">Gagal ({{ $this->pitchingStats['tidak_terlaksana_count'] }})</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>
                    <span class="truncate">Rencana ({{ $this->pitchingStats['rencana_count'] }})</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Anggaran & Hasil -->
        <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-sm rounded-2xl">
            <div>
                <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">ANGGARAN & KONVERSI</h3>
                <h2 class="text-2xl mt-1 font-extrabold text-base-content text-indigo-600 dark:text-indigo-400">
                    Rp {{ number_format($this->pitchingStats['total_budget'], 0, ',', '.') }}
                </h2>
                <div class="text-xs text-base-content/50 mt-1">
                    Konversi Realisasi: {{ $this->pitchingStats['conversion_rate'] }}%
                </div>
            </div>

            <!-- Stacked Horizontal Bar Chart: Hasil Pitching -->
            @php
                $hasilTotal = max($this->pitchingStats['completed_count'] + $this->pitchingStats['negatif_count'], 1);
                $positifPercent = ($this->pitchingStats['completed_count'] / $hasilTotal) * 100;
                $negatifPercent = ($this->pitchingStats['negatif_count'] / $hasilTotal) * 100;
            @endphp
            <div class="h-4 w-full bg-base-200 rounded-full overflow-hidden flex">
                <div class="h-full bg-emerald-500" style="width: {{ $positifPercent }}%" title="Positif: {{ $this->pitchingStats['completed_count'] }}"></div>
                <div class="h-full bg-rose-500" style="width: {{ $negatifPercent }}%" title="Negatif: {{ $this->pitchingStats['negatif_count'] }}"></div>
            </div>

            <div class="grid grid-cols-2 gap-1 text-[11px] text-base-content/70">
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                    <span class="truncate">Positif ({{ $this->pitchingStats['completed_count'] }})</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span>
                    <span class="truncate">Negatif ({{ $this->pitchingStats['negatif_count'] }})</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Verifikasi Manager -->
        <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-sm rounded-2xl">
            <div>
                <h3 class="text-xs text-base-content/50 font-bold uppercase tracking-wider">VERIFIKASI MANAGER</h3>
                <h2 class="text-2xl mt-1 font-extrabold text-base-content">
                    {{ $this->pitchingStats['verified_count'] }} / {{ $this->pitchingStats['total_count'] }}
                </h2>
                <div class="text-xs text-base-content/50 mt-1">
                    Persentase: {{ $this->pitchingStats['total_count'] > 0 ? round(($this->pitchingStats['verified_count'] / $this->pitchingStats['total_count']) * 100, 1) : 0 }}% Terverifikasi
                </div>
            </div>

            <!-- Stacked Horizontal Bar Chart: Verifikasi -->
            @php
                $verifTotal = max($this->pitchingStats['total_count'], 1);
                $vPercent = ($this->pitchingStats['verified_count'] / $verifTotal) * 100;
                $uvPercent = ($this->pitchingStats['unverified_count'] / $verifTotal) * 100;
            @endphp
            <div class="h-4 w-full bg-base-200 rounded-full overflow-hidden flex">
                <div class="h-full bg-indigo-500" style="width: {{ $vPercent }}%" title="Terverifikasi: {{ $this->pitchingStats['verified_count'] }}"></div>
                <div class="h-full bg-zinc-400" style="width: {{ $uvPercent }}%" title="Belum: {{ $this->pitchingStats['unverified_count'] }}"></div>
            </div>

            <div class="grid grid-cols-2 gap-1 text-[11px] text-base-content/70">
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-indigo-500 inline-block"></span>
                    <span class="truncate">Verified ({{ $this->pitchingStats['verified_count'] }})</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-zinc-400 inline-block"></span>
                    <span class="truncate">Unverified ({{ $this->pitchingStats['unverified_count'] }})</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300 p-6 space-y-6 shadow-xs rounded-2xl">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-base-content">Partnership Pitching</h2>
                <p class="text-sm text-base-content/50 mt-1">Kelola dan pantau progres penawaran kerja sama dengan
                    instansi dalam bentuk tabel.</p>
            </div>
            <div class="flex items-center gap-3">
                <x-button wire:click="resetFilters" label="Reset Filter" icon="o-arrow-path" class="btn-ghost btn-sm" />
                <livewire:partnership.pitching-create-modal />
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
                        $stageFilters = [['id' => '', 'name' => 'Semua Tahap']];
                        foreach ($this->stageOptions as $stg) {
                            $stageFilters[] = ['id' => $stg->id, 'name' => $stg->stage_name];
                        }
                    @endphp
                    <x-select label="Status / Tahap" wire:model.live="filterStageId" :options="$stageFilters" />
                </div>
                <div class="w-44">
                    @php
                        $statusFilters = [
                            ['id' => '', 'name' => 'Semua Hasil'],
                            ['id' => 'hot', 'name' => 'HOT'],
                            ['id' => 'warm', 'name' => 'WARM'],
                            ['id' => 'lost', 'name' => 'LOST'],
                            ['id' => 'won', 'name' => 'WON (PENDING)'],
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
                <div class="flex items-center h-12 pb-1.5">
                    <x-checkbox label="Tampilkan Semua Rencana" wire:model.live="showRescheduled"
                        class="checkbox-primary checkbox-sm" />
                </div>
            </div>
            <div class="w-64">
                <x-input label="Cari Instansi / Kontak / Produk" wire:model.live.debounce.300ms="search" placeholder="Cari..." icon="o-magnifying-glass" />
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
                        <th>Status / Tahap</th>
                        <th>Hasil / Keputusan</th>
                        <th class="text-center">Verif Manager</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->cards as $card)
                        <tr :key="$card->id" class="border-base-300 hover:bg-base-200/50">
                            <!-- Instansi / Kontak -->
                            <td>
                                <div class="space-y-1">
                                    <div class="font-semibold text-sm text-base-content">
                                        {{ $card->contact?->name ?? $card->title }}
                                        @if ($card->contact?->is_ql)
                                            <span class="badge badge-success badge-xs font-bold ml-1 text-white text-[9px] rounded">QL</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-base-content/50">
                                        {{ $card->contact?->opd?->name ?? '-' }}
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
                                    {{ $card->title }}
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

                            <!-- Status / Tahap (Interactive Dropdown) -->
                             <td>
                                 @php
                                     $canUpdateStage = $this->canVerify || $card->manager_verified;
                                 @endphp
                                 <div class="dropdown dropdown-bottom dropdown-end">
                                     @if ($canUpdateStage)
                                         <label tabindex="0" class="btn btn-ghost btn-xs gap-1 font-semibold normal-case">
                                             @if ($card->stage?->stage_name === 'Terlaksana')
                                                 <span class="text-success">Terlaksana</span>
                                             @elseif ($card->stage?->stage_name === 'Tidak Terlaksana')
                                                 <span class="text-error">Tidak Terlaksana</span>
                                             @else
                                                 <span class="text-base-content/80">{{ $card->stage?->stage_name ?? '-' }}</span>
                                             @endif
                                             <x-icon name="o-chevron-down" class="w-3 h-3" />
                                         </label>
                                         <ul tabindex="0" class="dropdown-content menu p-2 shadow-lg bg-base-100 border border-base-300 rounded-xl w-52 z-30">
                                             @foreach ($this->stageOptions as $stageOption)
                                                 <li>
                                                     <a wire:click="moveCard({{ $card->id }},'{{ $stageOption->stage_name }}')" class="text-xs">
                                                         {{ $stageOption->stage_name }}
                                                     </a>
                                                 </li>
                                             @endforeach
                                         </ul>
                                     @else
                                         <span class="text-xs font-semibold px-2 py-1 bg-base-200 text-base-content/40 rounded cursor-not-allowed" title="Menunggu verifikasi manager untuk mengubah tahap">
                                             {{ $card->stage?->stage_name ?? '-' }}
                                         </span>
                                     @endif
                                 </div>
                             </td>

                            <!-- Hasil / Keputusan -->
                            <td>
                                @if ($card->stage?->stage_name === 'Terlaksana')
                                    @if ($card->status === 'won')
                                        @if ($card->manager_verified)
                                            <span class="badge badge-success text-white badge-sm font-semibold rounded text-[10px] cursor-pointer" wire:click="openHotUpdate({{ $card->id }})">WON</span>
                                        @else
                                            <span class="badge bg-amber-500 text-white border-amber-500 badge-sm font-semibold rounded text-[10px] cursor-pointer" wire:click="openHotUpdate({{ $card->id }})" title="Menunggu verifikasi manager">WON (PENDING)</span>
                                        @endif
                                    @elseif ($card->status === 'hot')
                                        <span class="badge bg-orange-500 text-white border-orange-500 badge-sm font-semibold rounded text-[10px] cursor-pointer" wire:click="openHotUpdate({{ $card->id }})">HOT</span>
                                    @elseif ($card->status === 'warm')
                                        <span class="badge bg-blue-500 text-white border-blue-500 badge-sm font-semibold rounded text-[10px] cursor-pointer" wire:click="openHotUpdate({{ $card->id }})">WARM</span>
                                    @elseif ($card->status === 'lost')
                                        <span class="badge badge-error text-white badge-sm font-semibold rounded text-[10px] cursor-pointer" wire:click="openHotUpdate({{ $card->id }})">LOST</span>
                                    @else
                                        <span class="badge badge-ghost badge-sm font-semibold rounded text-[10px] cursor-pointer" wire:click="openHotUpdate({{ $card->id }})">{{ strtoupper($card->status) }}</span>
                                    @endif
                                @elseif ($card->stage?->stage_name === 'Tidak Terlaksana')
                                    <span class="badge badge-error text-white badge-sm font-semibold rounded text-[10px] cursor-pointer" wire:click="openHotUpdate({{ $card->id }})">LOST</span>
                                @else
                                    <span class="text-xs text-base-content/40 italic">Dalam Proses</span>
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
                                     @if (!$card->manager_verified)
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
                            <td colspan="9" class="text-center py-8 text-xs text-base-content/40">
                                Belum ada prospek pitching. Silakan tambah prospek baru.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <x-pagination-footer :paginator="$this->cards" />
        </div>
    </div>

    <!-- Terlaksana Modal -->
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
                    <x-input label="Link Google Drive Dokumen" wire:model="driveLink"
                        placeholder="https://drive.google.com/..." />
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

    <!-- Tidak Terlaksana Modal -->
    <x-modal wire:model="showTidakTerlaksanaModal" class="z-[9999]" title="Hasil Pitching Tidak Terlaksana"
        subtitle="Tentukan alasan tidak terlaksananya pitching dengan {{ $pendingDecisionCard['contact_name'] ?? '' }}.">
        <form wire:submit.prevent="saveTidakTerlaksanaDecision" class="space-y-4">
            @php
                $tidakTerlaksanaReasons = [
                    ['id' => 'reschedule - bureaucracy', 'name' => 'Reschedule - Bureaucracy'],
                    ['id' => 'canceled - unresponsive', 'name' => 'Canceled - Unresponsive'],
                    ['id' => 'canceled - internal issue', 'name' => 'Canceled - Internal Issue'],
                ];
            @endphp
            <x-select label="Alasan Tidak Terlaksana" wire:model.live="tidakTerlaksanaReason" :options="$tidakTerlaksanaReasons" />

            @if (str_starts_with($tidakTerlaksanaReason, 'reschedule'))
                <div
                    class="p-4 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 space-y-4">
                    <span class="label-text font-semibold block">Reschedule Sekarang?</span>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="rescheduleOption" value="now"
                                class="radio radio-primary" />
                            <span class="text-sm">Ya, Tentukan Tanggal Baru</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="rescheduleOption" value="next_week"
                                class="radio radio-primary" />
                            <span class="text-sm">Tidak, Jadwalkan Otomatis Minggu Depan</span>
                        </label>
                    </div>

                    @if ($rescheduleOption === 'now')
                        <x-input label="Tanggal Pitching Baru" wire:model="newPitchingDate" type="date" />
                    @else
                        <div class="text-xs text-base-content/50">
                            Sistem akan membuat salinan prospek ini secara otomatis dengan tanggal default:
                            <strong>{{ date('d M Y', strtotime(($pendingDecisionCard['pitching_date'] ?? date('Y-m-d')) . ' +7 days')) }}</strong>.
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-base-300">
                <x-button label="Batal" wire:click="$set('showTidakTerlaksanaModal', false)" class="btn-ghost" />
                <x-button label="Simpan Alasan" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Edit Modal -->
    <x-modal wire:model="showEditModal" class="z-[9999]" title="Edit Prospek Pitching"
        subtitle="Ubah detail informasi prospek pitching.">
        <form wire:submit.prevent="saveEdit" class="space-y-4">
            <x-input label="Potensi (Produk / Output)" wire:model="editTitle" required />
            <x-input label="Anggaran (Rp)" wire:model="editBudget" type="number" required />
            <x-input label="Tanggal Pitching" wire:model="editDate" type="date" required />
            
            <div class="grid grid-cols-2 gap-4">
                <x-select label="Metode" wire:model="editMethod" :options="[['id' => 'Online', 'name' => 'Online'], ['id' => 'Offline', 'name' => 'Offline']]" />
                <x-select label="Sumber Potensi" wire:model="editSumber" :options="[['id' => 'SIRUP', 'name' => 'SIRUP'], ['id' => 'SIPD', 'name' => 'SIPD'], ['id' => 'INAPROC', 'name' => 'INAPROC'], ['id' => 'ANALISIS PS', 'name' => 'ANALISIS PS'], ['id' => 'PERMINTAAN MITRA', 'name' => 'PERMINTAAN MITRA']]" />
            </div>

            <x-select label="Status Pitching" wire:model.live="editStatus" :options="[
                ['id' => 'Active', 'name' => 'Active (Rencana)'],
                ['id' => 'won', 'name' => 'Won (Deal)'],
                ['id' => 'hot', 'name' => 'Hot Prospect'],
                ['id' => 'warm', 'name' => 'Warm Prospect'],
                ['id' => 'lost', 'name' => 'Lost (Negatif)']
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
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-select label="Bulan Proyeksi Realisasi" wire:model="editProjectionMonth" :options="$months" />
                    <x-select label="Pekan Proyeksi" wire:model="editProjectionWeek" :options="$weeks" />
                    <x-input label="Link Google Drive Dokumen" wire:model="editDriveLink" placeholder="https://drive.google.com/..." />
                </div>
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
                            <h3 class="text-lg font-bold text-base-content">Detail Prospek Pitching</h3>
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

                        <h4 class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Detail Pitching</h4>
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
                            <div>
                                <span class="text-base-content/40 block text-xs">Tahap Prospek</span>
                                <span class="font-semibold text-base-content">{{ $selectedCardForDetail->stage?->stage_name ?? '-' }}</span>
                            </div>
                        </div>

                        @if ($selectedCardForDetail->stage?->stage_name === 'Terlaksana')
                            <hr class="border-base-300" />
                            <h4 class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Hasil Terlaksana & Proyeksi</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <span class="text-base-content/40 block text-xs">Status Hasil</span>
                                    <span class="badge badge-sm font-semibold rounded text-[10px] {{ in_array($selectedCardForDetail->status, ['won', 'hot', 'warm']) ? 'badge-success' : 'badge-error' }}">
                                        {{ strtoupper($selectedCardForDetail->status) }}
                                    </span>
                                </div>
                                @if (in_array($selectedCardForDetail->status, ['won', 'hot', 'warm']))
                                    <div>
                                        <span class="text-base-content/40 block text-xs">Proyeksi Realisasi</span>
                                        <span class="font-medium text-base-content/90">{{ $selectedCardForDetail->projection_month }} ({{ $selectedCardForDetail->projection_week }})</span>
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
                                @else
                                    <div>
                                        <span class="text-base-content/40 block text-xs">Alasan Negatif</span>
                                        <span class="font-medium text-base-content/90">{{ $selectedCardForDetail->negative_reason }}</span>
                                    </div>
                                @endif
                            </div>
                        @elseif ($selectedCardForDetail->stage?->stage_name === 'Tidak Terlaksana')
                            <hr class="border-base-300" />
                            <h4 class="text-xs font-bold text-base-content/40 uppercase tracking-wider">Alasan Tidak Terlaksana</h4>
                            <div>
                                <span class="text-base-content/40 block text-xs">Alasan</span>
                                <span class="font-medium text-rose-600 dark:text-rose-400">{{ $selectedCardForDetail->negative_reason }}</span>
                            </div>
                        @endif

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
