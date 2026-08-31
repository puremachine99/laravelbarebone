<?php

use function Livewire\Volt\{state, computed, uses};

uses([\Mary\Traits\Toast::class]);

state([
    'showCreateModal' => false,

    // Contact Cascading Fields
    'selectedProvinceId' => '',
    'selectedRegencyId' => '',
    'selectedOpdId' => '',
    'selectedContactId' => '',

    // Pitching specific fields
    'newProduct' => '',
    'sumberPotensi' => 'SIRUP',
    'newBudget' => '',
    'newDate' => '',
    'newMethod' => 'Online',
    'terlaksana' => false,
    'hasilPitching' => 'hot',
    'projectionMonth' => '',
    'projectionWeek' => 'Pekan 1',
    'driveLink' => '',
    'negativeReason' => 'No Budget',
]);

$provinceOptions = computed(function () {
    $employee = \App\Models\Employee::where('user_id', auth()->id())->first();
    if ($employee && $employee->region_id && auth()->user()->email !== 'supernakama@smartid.co.id') {
        return \App\Models\Province::whereHas('regencies', fn($q) => $q->where('region_id', $employee->region_id))->orderBy('name')->get();
    }
    return \App\Models\Province::orderBy('name')->get();
});

$regencyOptions = computed(function () {
    if (!$this->selectedProvinceId) {
        return collect();
    }
    return \App\Models\Regency::where('province_id', $this->selectedProvinceId)->orderBy('name')->get();
});

$opdOptions = computed(function () {
    if (!$this->selectedRegencyId) {
        return collect();
    }
    return \App\Models\Opd::where('regency_id', $this->selectedRegencyId)->orderBy('name')->get();
});

$contactOptions = computed(function () {
    if (!$this->selectedOpdId) {
        return collect();
    }
    return \App\Models\Contact::where('opd_id', $this->selectedOpdId)->with('contactPosition')->orderBy('name')->get();
});

$productOptions = computed(function () {
    return \App\Models\Output::orderBy('output_name')->get();
});

$updatedSelectedProvinceId = function ($value) {
    $this->selectedRegencyId = '';
    $this->selectedOpdId = '';
    $this->selectedContactId = '';
};

$updatedSelectedRegencyId = function ($value) {
    $this->selectedOpdId = '';
    $this->selectedContactId = '';
};

$updatedSelectedOpdId = function ($value) {
    $this->selectedContactId = '';
};

\Livewire\Volt\mount(function () {
    $this->newDate = date('Y-m-d');
    $this->projectionMonth = date('Y-m');
});

$addCard = function () {
    $rules = [
        'selectedContactId' => ['required', 'exists:pmo_contacts,id'],
        'newProduct' => ['required', 'exists:pmo_outputs,id'],
        'sumberPotensi' => ['required', 'in:SIRUP,SIPD,INAPROC,ANALISIS PS,PERMINTAAN MITRA'],
        'newBudget' => ['required', 'numeric', 'min:1'],
        'newDate' => ['required', 'date'],
        'newMethod' => ['required', 'in:Online,Offline'],
    ];

    if ($this->terlaksana) {
        $rules['hasilPitching'] = ['required', 'in:won,hot,warm,lost'];
        if (in_array($this->hasilPitching, ['won', 'hot', 'warm'])) {
            $rules['projectionMonth'] = ['required', 'string'];
            $rules['projectionWeek'] = ['required', 'string'];
            $rules['driveLink'] = ['nullable', 'url'];
        } else {
            $rules['negativeReason'] = ['required', 'in:No Budget,No Authority,No Need,Lost to Competitor'];
        }
    }

    $this->validate($rules, [
        'selectedContactId.required' => 'Kontak wajib dipilih.',
        'newProduct.required' => 'Produk wajib dipilih.',
        'sumberPotensi.required' => 'Sumber potensi wajib dipilih.',
        'newBudget.required' => 'Anggaran wajib diisi.',
        'newBudget.numeric' => 'Anggaran harus berupa angka.',
        'newDate.required' => 'Tanggal pitching wajib diisi.',
    ]);

    $isManager = auth()->user()->hasRole('Super Admin') || 
                 auth()->user()?->email === 'supernakama@smartid.co.id' ||
                 auth()->user()->hasPermissionTo('verify-manager');
    if ($this->terlaksana && $this->hasilPitching === 'won' && !$isManager) {
        $this->error('Status Won memerlukan verifikasi manager.');
        return;
    }

    $activeProfile = \App\Models\PipelineProfile::where('pipeline_name', 'Pitching')->where('is_active', true)->first();

    if (!$activeProfile) {
        $this->error('Tidak ada pipeline pitching yang aktif.');
        return;
    }

    $stageName = $this->terlaksana ? 'Terlaksana' : 'Rencana';
    $stage = $activeProfile->stages()->where('stage_name', $stageName)->first();
    if (!$stage) {
        $this->error("Tahap {$stageName} tidak ditemukan.");
        return;
    }

    $productObj = \App\Models\Output::find($this->newProduct);
    $productName = $productObj ? $productObj->output_name : 'Produk';

    $status = 'Active';
    if ($this->terlaksana) {
        $status = $this->hasilPitching;
    }

    $card = \App\Models\Card::create([
        'title' => $productName,
        'output_id' => (int) $this->newProduct,
        'stage_id' => $stage->id,
        'contact_id' => $this->selectedContactId,
        'nominal_value' => (float) $this->newBudget,
        'status' => $status,
        'sumber_proyeksi' => $this->sumberPotensi,
        'pitching_date' => $this->newDate,
        'communication_method' => $this->newMethod,
        'fiscal_year' => (int) date('Y'),
        'projection_month' => $this->terlaksana && in_array($this->hasilPitching, ['won', 'hot', 'warm']) ? $this->projectionMonth : null,
        'projection_week' => $this->terlaksana && in_array($this->hasilPitching, ['won', 'hot', 'warm']) ? $this->projectionWeek : null,
        'drive_link' => $this->terlaksana && in_array($this->hasilPitching, ['won', 'hot', 'warm']) ? $this->driveLink : null,
        'negative_reason' => $this->terlaksana && $this->hasilPitching === 'lost' ? $this->negativeReason : null,
        'manager_verified' => false,
    ]);
    $card->addHistoryLog('created', "Prospect created by user");

    $this->reset(['selectedProvinceId', 'selectedRegencyId', 'selectedOpdId', 'selectedContactId', 'newProduct', 'newBudget', 'driveLink', 'terlaksana']);
    $this->newDate = date('Y-m-d');
    $this->newMethod = 'Online';
    $this->sumberPotensi = 'SIRUP';
    $this->hasilPitching = 'hot';
    $this->showCreateModal = false;

    // Dispatch event to parent to refresh the card table
    $this->dispatch('card-added');

    $this->success('Prospek pitching baru berhasil ditambahkan!');
};

new class {};
?>

<div>
    <x-button wire:click="$set('showCreateModal', true)" label="Tambah Prospek" icon="o-plus" class="btn-primary btn-sm" />

    <x-modal wire:model="showCreateModal" title="Tambah Prospek Pitching Baru"
        subtitle="Input data prospek baru untuk ditugaskan di regional sales Anda.">
        <form wire:submit.prevent="addCard" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Cascading Dropdowns -->
                <x-select label="Provinsi" placeholder="Pilih Provinsi..." wire:model.live="selectedProvinceId"
                    :options="$this->provinceOptions" />

                <x-select label="Kabupaten / Kota" placeholder="Pilih Kabupaten / Kota..."
                    wire:model.live="selectedRegencyId" :options="$this->regencyOptions" :disabled="!$selectedProvinceId" />

                <div class="col-span-2">
                    <x-select label="Instansi (OPD)" placeholder="Pilih Instansi..." wire:model.live="selectedOpdId"
                        :options="$this->opdOptions" :disabled="!$selectedRegencyId" />
                </div>

                <div class="col-span-2">
                    <span class="label-text font-semibold mb-1 block">Pilih Kontak</span>
                    @if (!$selectedOpdId)
                        <div
                            class="p-3 text-xs text-base-content/50 border border-dashed border-base-300 rounded-lg text-center bg-base-100/50">
                            Silakan pilih Instansi (OPD) terlebih dahulu
                        </div>
                    @elseif($this->contactOptions->isEmpty())
                        <div
                            class="p-3 text-xs text-base-content/50 border border-dashed border-base-300 rounded-lg text-center bg-base-100/50">
                            Tidak ada kontak yang tersedia di instansi ini
                        </div>
                    @else
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            @foreach ($this->contactOptions as $contact)
                                <label
                                    class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors {{ $selectedContactId == $contact->id ? 'bg-indigo-50/50 dark:bg-indigo-950/20 border-indigo-500 dark:border-indigo-400' : 'bg-base-100 border-base-300 hover:bg-base-200' }}">
                                    <input type="radio" wire:model="selectedContactId" value="{{ $contact->id }}"
                                        class="radio radio-primary" />
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-semibold text-base-content truncate">
                                            {{ $contact->name }}</div>
                                        <div
                                            class="text-xs text-base-content/50 flex items-center justify-between mt-0.5">
                                            <span>{{ $contact->contactPosition?->name ?? 'Staf' }}</span>
                                            <span>{{ $contact->phone ?? '-' }}</span>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('selectedContactId')
                        <span class="text-error text-xs block mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Potensi (Outputs) -->
                <div class="col-span-2">
                    <x-select label="Potensi (Produk / Output)" placeholder="Cari & Pilih Produk..."
                        wire:model="newProduct" :options="$this->productOptions" option-label="output_name" />
                </div>

                <!-- Sumber Potensi -->
                @php
                    $sumberOptions = [
                        ['id' => 'SIRUP', 'name' => 'SIRUP'],
                        ['id' => 'SIPD', 'name' => 'SIPD'],
                        ['id' => 'INAPROC', 'name' => 'INAPROC'],
                        ['id' => 'ANALISIS PS', 'name' => 'ANALISIS PS'],
                        ['id' => 'PERMINTAAN MITRA', 'name' => 'PERMINTAAN MITRA'],
                    ];
                @endphp
                <x-select label="Sumber Potensi" wire:model="sumberPotensi" :options="$sumberOptions" />

                <!-- Anggaran Potensi (Rupiah format) -->
                <div>
                    <x-input label="Anggaran Potensi (Rp)" wire:model.live="newBudget" type="number"
                        placeholder="Cth: 75000000" />
                    <div class="text-[11px] text-base-content/50 mt-1 font-semibold">
                        Realisasi: Rp <span>{{ number_format((float) ($newBudget ?: 0), 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Tanggal & Metode -->
                <x-input label="Tanggal Pitching" wire:model="newDate" type="date" />

                <div>
                    <span class="label-text font-semibold mb-1 block">Metode Rencana</span>
                    <div class="flex gap-4 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="newMethod" value="Online" class="radio radio-primary" />
                            <span class="text-sm">Online</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="newMethod" value="Offline" class="radio radio-primary" />
                            <span class="text-sm">Offline</span>
                        </label>
                    </div>
                </div>

                <!-- Checkbox Terlaksana -->
                <div class="col-span-2">
                    <x-checkbox label="Apakah Pitching Sudah Terlaksana?" wire:model.live="terlaksana" />
                </div>

                @if ($terlaksana)
                    <!-- Hasil Pitching -->
                    <div class="col-span-2 border-t border-base-300 pt-4">
                        <span class="label-text font-semibold mb-1 block">Hasil Pitching</span>
                        <div class="flex gap-4 mt-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="hasilPitching" value="won"
                                    class="radio radio-success" />
                                <span class="text-sm font-medium">Won (Deal)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="hasilPitching" value="hot"
                                    class="radio radio-primary" />
                                <span class="text-sm font-medium">Hot Prospect</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="hasilPitching" value="warm"
                                    class="radio radio-info" />
                                <span class="text-sm font-medium">Warm Prospect</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="hasilPitching" value="lost"
                                    class="radio radio-error" />
                                <span class="text-sm font-medium">Lost (Negatif)</span>
                            </label>
                        </div>
                    </div>

                    @if (in_array($hasilPitching, ['won', 'hot', 'warm']))
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
                        <x-select label="Bulan Proyeksi Realisasi" wire:model="projectionMonth" :options="$months" />
                        <x-select label="Pekan Proyeksi" wire:model="projectionWeek" :options="$weeks" />
                        <x-input label="Link Google Drive (Dokumen Penawaran)" wire:model="driveLink"
                            placeholder="https://drive.google.com/..." />
                    @else
                        @php
                            $negReasons = [
                                ['id' => 'No Budget', 'name' => 'No Budget (Tidak ada anggaran)'],
                                [
                                    'id' => 'No Authority',
                                    'name' => 'No Authority (Tidak ada wewenang penentu kebijakan)',
                                ],
                                ['id' => 'No Need', 'name' => 'No Need (Belum memerlukan produk terkait)'],
                                [
                                    'id' => 'Lost to Competitor',
                                    'name' => 'Lost to Competitor (Kalah saing dengan kompetitor)',
                                ],
                            ];
                        @endphp
                        <div class="col-span-2">
                            <x-select label="Alasan Lost / Gagal" wire:model="negativeReason" :options="$negReasons" />
                        </div>
                    @endif
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-base-300">
                <x-button label="Batal" wire:click="$set('showCreateModal', false)" class="btn-ghost" />
                <x-button label="Simpan Prospek" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>
</div>
