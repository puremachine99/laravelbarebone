<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public User $user;

    public function mount(User $user): void
    {
        if ($user->is(Auth::user())) {
            $this->redirectRoute('profile.show');

            return;
        }

        $this->user = $user;
    }

    #[Computed]
    public function employee()
    {
        return $this->user
            ->employee()
            ->with(['department', 'region', 'positionStruktural', 'positionFungsional'])
            ->first();
    }

    #[Computed]
    public function roleNames()
    {
        return $this->user->getRoleNames();
    }
};

?>

<div class="p-4 md:p-6 space-y-6">
    <div class="flex items-center gap-3">
        <button type="button" onclick="history.back()" class="btn btn-ghost btn-circle btn-sm">
            <x-icon name="o-arrow-left" class="w-4 h-4" />
        </button>
        <div>
            <h1 class="text-xl font-bold text-base-content">{{ __('Profil Pengguna') }}</h1>
            <p class="mt-1 text-sm text-base-content/50">{{ __('Informasi profil dan kepegawaian.') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Identity card --}}
        <div class="card bg-base-200 border border-base-300 shadow-sm rounded-2xl lg:col-span-1">
            <div class="card-body items-center text-center p-6 space-y-4">
                @if ($avatarUrl = $user->avatarUrl())
                    <div class="avatar">
                        <div class="w-28 rounded-full ring ring-primary/30 ring-offset-2 ring-offset-base-200">
                            <img src="{{ $avatarUrl }}" alt="{{ $user->name }}">
                        </div>
                    </div>
                @else
                    <x-avatar-initial :name="$user->name" size="w-28 h-28 text-3xl" />
                @endif

                <div>
                    <h2 class="font-semibold text-base-content">{{ $user->name }}</h2>
                    <p class="text-sm text-base-content/50">{{ $user->email }}</p>
                </div>

                @if ($this->roleNames->isNotEmpty())
                    <div class="flex flex-wrap justify-center gap-1">
                        @foreach ($this->roleNames as $roleName)
                            <span class="badge badge-primary badge-outline badge-sm">{{ $roleName }}</span>
                        @endforeach
                    </div>
                @endif

                @can('manage-chat')
                    <x-button label="{{ __('Kirim Pesan') }}" tag="a" href="{{ route('chat') }}" wire:navigate
                        icon="o-chat-bubble-left-right" class="btn-primary btn-sm w-full" />
                @endcan
            </div>
        </div>

        {{-- Employment info --}}
        <div class="lg:col-span-2 space-y-6">
            @if ($this->employee && ! $this->employee->hide_kpi_heatmap && $this->user->can('access-investors'))
                <livewire:kpi.employee-target-heatmap :employee="$this->employee" :key="'kpi-'.$this->employee->id" />
            @endif

            <div class="card bg-base-200 border border-base-300 shadow-sm rounded-2xl">
                <div class="card-body p-6 space-y-4">
                    <h3 class="text-lg font-medium text-base-content">{{ __('Informasi Kepegawaian') }}</h3>

                    @if ($this->employee)
                        @if ($this->employee->contract_start_date)
                            <p class="text-xs text-base-content/50 -mt-2">
                                {{ __('Bergabung sejak :date (:years thn)', [
                                    'date' => $this->employee->contract_start_date->format('d-m-Y'),
                                    'years' => number_format($this->employee->contract_start_date->diffInYears(now(), true), 1),
                                ]) }}
                            </p>
                        @endif

                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('NIS (Nomor Induk Staf)') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->nis ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Status Kepegawaian') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->employment_status ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Departemen') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->department->name ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Wilayah Tangani') }}</span>
                                <span class="font-medium">
                                    @if ($this->employee->region)
                                        <span class="badge badge-accent text-xs font-semibold px-2.5 py-1">{{ $this->employee->region->name }}
                                            ({{ $this->employee->region->code ?? '-' }})</span>
                                    @else
                                        <span class="text-base-content/40">-</span>
                                    @endif
                                </span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Jabatan Struktural') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->positionStruktural->name ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Jabatan Fungsional') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->positionFungsional->name ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center pb-1">
                                <span class="font-semibold text-base-content/70">{{ __('Nomor Telepon') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->phone_number ?? '-' }}</span>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-base-content/50">{{ __('Belum ada data kepegawaian untuk pengguna ini.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
