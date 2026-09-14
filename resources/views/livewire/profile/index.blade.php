<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component {
    use ProfileValidationRules, WithFileUploads;

    public string $name = '';
    public string $email = '';
    public ?string $secondary_email = null;
    public ?string $username = null;
    public $avatar = null;

    public function mount(): void
    {
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
        $this->secondary_email = auth()->user()->secondary_email;
        $this->username = auth()->user()->username;
    }

    #[Computed]
    public function employee()
    {
        return auth()->user()
            ->employee()
            ->with(['department', 'region', 'positionStruktural', 'positionFungsional'])
            ->first();
    }

    #[Computed]
    public function roleNames()
    {
        return auth()->user()->getRoleNames();
    }

    public function updateProfileInformation(): void
    {
        $user = auth()->user();

        $this->secondary_email = $this->secondary_email !== '' ? $this->secondary_email : null;
        $this->username = $this->username !== '' ? $this->username : null;

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        session()->flash('status', 'profile-updated');
    }

    public function updatedAvatar(): void
    {
        $this->uploadAvatar();
    }

    public function uploadAvatar(): void
    {
        $this->validate([
            'avatar' => ['required', 'image', 'max:1024'],
        ]);

        $user = auth()->user();
        $oldPath = $user->avatar_path;

        $user->avatar_path = $this->avatar->store('avatars', 's3');
        $user->save();

        if ($oldPath) {
            Storage::disk('s3')->delete($oldPath);
        }

        $this->reset('avatar');

        session()->flash('status', 'avatar-updated');
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk('s3')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        session()->flash('status', 'avatar-removed');
    }

    public function toggleKpiVisibility(): void
    {
        $employee = $this->employee;

        if (! $employee) {
            return;
        }

        $employee->update(['hide_kpi_heatmap' => ! $employee->hide_kpi_heatmap]);

        unset($this->employee);
    }
};

?>

<div class="p-4 md:p-6 space-y-6" x-data="{ tab: (new URLSearchParams(window.location.search)).get('tab') === 'appearance' ? 'appearance' : 'account' }">
    <div>
        <h1 class="text-xl font-bold text-base-content">{{ __('My Profile') }}</h1>
        <p class="mt-1 text-sm text-base-content/50">{{ __('Manage your personal information and profile photo.') }}</p>
    </div>

    <div role="tablist" class="tabs tabs-boxed w-fit">
        <a role="tab" href="{{ route('profile.show') }}" wire:navigate @click.prevent="tab = 'account'"
            :class="tab === 'account' && 'tab-active'" class="tab">{{ __('Account') }}</a>
        <a role="tab" href="{{ route('profile.show') }}?tab=appearance" @click.prevent="tab = 'appearance'"
            :class="tab === 'appearance' && 'tab-active'" class="tab">{{ __('Appearance') }}</a>
        <a role="tab" href="{{ route('profile.security') }}" wire:navigate class="tab">{{ __('Security') }}</a>
    </div>

    <div x-show="tab === 'account'" class="space-y-6">
    @if (session('status') === 'profile-updated')
        <div class="alert alert-success shadow-sm">
            <x-icon name="o-check-circle" class="w-5 h-5 text-white" />
            <span class="text-white">{{ __('Profile updated.') }}</span>
        </div>
    @elseif (session('status') === 'avatar-updated')
        <div class="alert alert-success shadow-sm">
            <x-icon name="o-check-circle" class="w-5 h-5 text-white" />
            <span class="text-white">{{ __('Profile photo updated.') }}</span>
        </div>
    @elseif (session('status') === 'avatar-removed')
        <div class="alert alert-success shadow-sm">
            <x-icon name="o-check-circle" class="w-5 h-5 text-white" />
            <span class="text-white">{{ __('Profile photo removed.') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Avatar Card --}}
        <div class="card bg-base-200 border border-base-300 shadow-sm rounded-2xl lg:col-span-1">
            <div class="card-body items-center text-center p-6 space-y-4">
                <x-file wire:model="avatar" hide-progress accept="image/*" omit-error>
                    <div class="relative inline-block" wire:loading.class="opacity-50" wire:target="avatar">
                        @if ($avatarUrl = auth()->user()->avatarUrl())
                            <div class="avatar">
                                <div class="w-28 rounded-full ring ring-primary/30 ring-offset-2 ring-offset-base-200">
                                    <img src="{{ $avatarUrl }}" alt="{{ auth()->user()->name }}">
                                </div>
                            </div>
                        @else
                            <x-avatar-initial :name="auth()->user()->name" size="w-28 h-28 text-3xl" />
                        @endif

                        <span class="absolute bottom-0 right-0 flex items-center justify-center size-8 rounded-full bg-primary text-primary-content border-2 border-base-200 shadow-sm hover:bg-primary/90 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </span>
                    </div>
                </x-file>

                @if ($errors->has('avatar'))
                    <p class="text-xs text-error">{{ $errors->first('avatar') }}</p>
                @endif

                <div>
                    <h2 class="font-semibold text-base-content">{{ auth()->user()->name }}</h2>
                    <p class="text-xs text-base-content/40">{{ __('User ID: :id', ['id' => auth()->user()->id]) }}</p>
                    <p class="text-sm text-base-content/50">{{ auth()->user()->email }}</p>
                </div>

                @if ($this->roleNames->isNotEmpty())
                    <div class="flex flex-wrap justify-center gap-1">
                        @foreach ($this->roleNames as $roleName)
                            <span class="badge badge-primary badge-outline badge-sm">{{ $roleName }}</span>
                        @endforeach
                    </div>
                @endif

                @if (auth()->user()->avatar_path)
                    <button type="button" wire:click="removeAvatar" wire:confirm="{{ __('Remove your profile photo?') }}"
                        class="link link-error text-xs">
                        {{ __('Remove Photo') }}
                    </button>
                @endif

                @if ($this->employee)
                    <div class="divider my-1"></div>

                    <div class="w-full text-left text-sm flex items-center justify-between gap-3">
                        <span class="text-xs text-base-content/50">{{ __('Sembunyikan KPI dari rekan kerja') }}</span>
                        <input type="checkbox" class="toggle toggle-sm toggle-primary"
                            wire:click="toggleKpiVisibility"
                            @checked($this->employee->hide_kpi_heatmap) />
                    </div>

                    <div class="w-full text-left text-sm" x-data="{ revealSensitive: false }">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <h3 class="text-sm font-semibold text-base-content/70 tracking-wide">{{ __('Employee Information') }}</h3>
                            <button type="button" @click="revealSensitive = !revealSensitive"
                                class="btn btn-ghost btn-xs btn-square shrink-0" :title="revealSensitive ? '{{ __('Hide sensitive info') }}' : '{{ __('Reveal sensitive info') }}'">
                                <svg x-show="!revealSensitive" xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg x-show="revealSensitive" xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 012.132-3.411m3.09-2.687A9.958 9.958 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.958 9.958 0 01-1.563 3.029M3 3l18 18M9.878 9.878a3 3 0 104.243 4.243" /></svg>
                            </button>
                        </div>

                        @if ($this->employee->contract_start_date)
                            <p class="mb-3 text-xs text-base-content/50">
                                {{ __('Joined since :date (:years yrs)', [
                                    'date' => $this->employee->contract_start_date->format('d-m-Y'),
                                    'years' => number_format($this->employee->contract_start_date->diffInYears(now(), true), 1),
                                ]) }}
                            </p>
                        @endif

                        <div class="space-y-3">
                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('NIS (Nomor Induk Staf)') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->nis ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Employment Status') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->employment_status ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Department') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->department->name ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Handled Region') }}</span>
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
                                <span class="font-semibold text-base-content/70">{{ __('Structural Position') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->positionStruktural->name ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Functional Position') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->positionFungsional->name ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('NIK') }}</span>
                                <span class="font-medium text-base-content">
                                    <span x-show="!revealSensitive">••••••••••••••••</span>
                                    <span x-show="revealSensitive" x-cloak>{{ $this->employee->nik ?? '-' }}</span>
                                </span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('NPWP') }}</span>
                                <span class="font-medium text-base-content">
                                    <span x-show="!revealSensitive">••••••••••••••••</span>
                                    <span x-show="revealSensitive" x-cloak>{{ $this->employee->npwp ?? '-' }}</span>
                                </span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Phone Number') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->phone_number ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Education') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->education_level ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Gender') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->gender ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Marital Status') }}</span>
                                <span class="font-medium text-base-content">
                                    <span x-show="!revealSensitive">••••••••</span>
                                    <span x-show="revealSensitive" x-cloak>{{ $this->employee->marital_status ?? '-' }}</span>
                                </span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Birth Date') }}</span>
                                <span class="font-medium text-base-content">
                                    <span x-show="!revealSensitive">••-••-••••</span>
                                    <span x-show="revealSensitive" x-cloak>{{ $this->employee->birth_date?->format('d-m-Y') ?? '-' }}</span>
                                </span>
                            </div>

                            <div class="flex justify-between items-center border-b border-base-300 pb-2">
                                <span class="font-semibold text-base-content/70">{{ __('Contract Start') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->contract_start_date?->format('d-m-Y') ?? '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center pb-1">
                                <span class="font-semibold text-base-content/70">{{ __('Contract End') }}</span>
                                <span class="font-medium text-base-content">{{ $this->employee->contract_end_date?->format('d-m-Y') ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Info + Edit --}}
        <div class="lg:col-span-2 space-y-6">
            @if ($this->employee)
                <livewire:kpi.employee-target-heatmap :employee="$this->employee" :key="'kpi-self-'.$this->employee->id" />
            @endif

            <div class="card bg-base-200 border border-base-300 shadow-sm rounded-2xl">
                <div class="card-body p-6 space-y-4">
                    <h3 class="text-lg font-medium text-base-content">{{ __('Account Information') }}</h3>

                    <form wire:submit="updateProfileInformation" class="space-y-4">
                        <x-input wire:model="name" label="{{ __('Name') }}" type="text" required
                            autocomplete="name" />
                        <x-input wire:model="email" label="{{ __('Email') }}" type="email" required
                            autocomplete="email" />
                        <x-input wire:model="secondary_email" label="{{ __('Secondary Email (Company)') }}"
                            hint="{{ __('Optional. Must be a @smartid.co.id address.') }}" type="email"
                            autocomplete="email" />
                        <x-input wire:model="username" label="{{ __('Username') }}"
                            hint="{{ __('Optional. Letters and numbers only, no symbols or spaces.') }}"
                            type="text" autocomplete="username" />

                        <div class="flex items-center gap-4">
                            <x-button label="{{ __('Save') }}" type="submit" class="btn-primary"
                                spinner="updateProfileInformation" />
                        </div>
                    </form>
                </div>
            </div>

            <div class="card bg-base-200 border border-base-300 shadow-sm rounded-2xl">
                <div class="card-body p-6 space-y-4">
                    <div>
                        <h3 class="text-lg font-medium text-base-content">{{ __('Delete account') }}</h3>
                        <p class="mt-1 text-sm text-base-content/50">{{ __('Delete your account and all of its resources') }}</p>
                    </div>

                    <div>
                        <x-button class="btn-error text-white" data-test="delete-user-button"
                            wire:click="$dispatch('confirm-user-deletion')" label="{{ __('Delete account') }}" />
                    </div>

                    <livewire:profile.delete-user-modal />
                </div>
            </div>
        </div>
    </div>
    </div>

    <div x-show="tab === 'appearance'" x-cloak
        x-data="{
            appearance: localStorage.getItem('theme') || 'system',
            setTheme(theme) {
                this.appearance = theme;
                localStorage.setItem('theme', theme);
                this.applyTheme();
            },
            applyTheme() {
                let isDark = this.appearance === 'dark' || (this.appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (isDark) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            }
        }"
        x-init="applyTheme()"
        class="card bg-base-200 border border-base-300 shadow-sm rounded-2xl max-w-lg">
        <div class="card-body p-6 space-y-4">
            <div>
                <h3 class="text-lg font-medium text-base-content">{{ __('Appearance') }}</h3>
                <p class="mt-1 text-sm text-base-content/50">{{ __('Update the appearance settings for your account') }}</p>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <button type="button" @click="setTheme('light')"
                    :class="appearance === 'light' ? 'border-primary ring-2 ring-primary/20 bg-primary/5 text-primary' : 'border-base-300 text-base-content/80 bg-base-200'"
                    class="flex flex-col items-center gap-2 p-4 border rounded-xl hover:bg-base-300 transition-all font-semibold shadow-sm">
                    <x-icon name="o-sun" class="w-6 h-6" />
                    <span>{{ __('Light') }}</span>
                </button>
                <button type="button" @click="setTheme('dark')"
                    :class="appearance === 'dark' ? 'border-primary ring-2 ring-primary/20 bg-primary/5 text-primary' : 'border-base-300 text-base-content/80 bg-base-200'"
                    class="flex flex-col items-center gap-2 p-4 border rounded-xl hover:bg-base-300 transition-all font-semibold shadow-sm">
                    <x-icon name="o-moon" class="w-6 h-6" />
                    <span>{{ __('Dark') }}</span>
                </button>
                <button type="button" @click="setTheme('system')"
                    :class="appearance === 'system' ? 'border-primary ring-2 ring-primary/20 bg-primary/5 text-primary' : 'border-base-300 text-base-content/80 bg-base-200'"
                    class="flex flex-col items-center gap-2 p-4 border rounded-xl hover:bg-base-300 transition-all font-semibold shadow-sm">
                    <x-icon name="o-computer-desktop" class="w-6 h-6" />
                    <span>{{ __('System') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
