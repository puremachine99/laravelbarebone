<?php

use App\Concerns\ProfileValidationRules;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        session()->flash('status', 'profile-updated');
    }

    /* @chisel-email-verification */
    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && !Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return !Auth::user() instanceof MustVerifyEmail || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
    #[Computed]
    public function employee()
    {
        return Auth::user()
            ->employee()
            ->with(['department', 'region', 'positionStruktural', 'positionFungsional'])
            ->first();
    }
    /* @end-chisel-email-verification */
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        @if (session('status') === 'profile-updated')
            <div class="alert alert-success shadow-sm mb-4">
                <x-icon name="o-check-circle" class="w-5 h-5 text-white" />
                <span class="text-white">{{ __('Profile updated.') }}</span>
            </div>
        @endif

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <x-input wire:model="name" label="{{ __('Name') }}" type="text" required autofocus autocomplete="name" />

            <div>
                <x-input wire:model="email" label="{{ __('Email') }}" type="email" required autocomplete="email" />

                {{-- @chisel-email-verification --}}
                @if ($this->hasUnverifiedEmail)
                    <div class="mt-4 text-sm text-base-content/50">
                        <span>{{ __('Your email address is unverified.') }}</span>

                        <button type="button" class="link link-primary text-sm inline-block ml-1"
                            wire:click.prevent="resendVerificationNotification">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 font-medium text-success">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </p>
                        @endif
                    </div>
                @endif
                {{-- @end-chisel-email-verification --}}
            </div>

            <div class="flex items-center gap-4">
                <x-button label="{{ __('Save') }}" type="submit" class="btn-primary"
                    data-test="update-profile-button" />
            </div>
        </form>

        @if ($this->employee)
            <div class="divider my-8"></div>

            <div class="space-y-6">
                <div>
                    <h3 class="text-lg font-medium text-base-content">{{ __('Employee Information') }}</h3>
                    <p class="mt-1 text-sm text-base-content/50">
                        {{ __('Your official employment details and region assignment.') }}
                    </p>
                </div>

                <div class="card bg-base-200 border border-base-300 shadow-sm rounded-2xl">
                    <div class="card-body p-5 space-y-4 text-sm">
                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('NIS (Nomor Induk Staf)') }}</span>
                            <span class="font-medium text-base-content">{{ $this->employee->nis ?? '-' }}</span>
                        </div>

                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('Employment Status') }}</span>
                            <span
                                class="font-medium text-base-content">{{ $this->employee->employment_status ?? '-' }}</span>
                        </div>

                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('Department') }}</span>
                            <span
                                class="font-medium text-base-content">{{ $this->employee->department->name ?? '-' }}</span>
                        </div>

                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('Handled Region') }}</span>
                            <span class="font-medium">
                                @if ($this->employee->region)
                                    <span
                                        class="badge badge-accent text-xs font-semibold px-2.5 py-1">{{ $this->employee->region->name }}
                                        ({{ $this->employee->region->code ?? '-' }})</span>
                                @else
                                    <span class="text-base-content/40">-</span>
                                @endif
                            </span>
                        </div>

                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('Structural Position') }}</span>
                            <span
                                class="font-medium text-base-content">{{ $this->employee->positionStruktural->name ?? '-' }}</span>
                        </div>

                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('Functional Position') }}</span>
                            <span
                                class="font-medium text-base-content">{{ $this->employee->positionFungsional->name ?? '-' }}</span>
                        </div>

                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('NIK') }}</span>
                            <span class="font-medium text-base-content">{{ $this->employee->nik ?? '-' }}</span>
                        </div>

                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('NPWP') }}</span>
                            <span class="font-medium text-base-content">{{ $this->employee->npwp ?? '-' }}</span>
                        </div>

                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('Phone Number') }}</span>
                            <span
                                class="font-medium text-base-content">{{ $this->employee->phone_number ?? '-' }}</span>
                        </div>

                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('Education') }}</span>
                            <span
                                class="font-medium text-base-content">{{ $this->employee->education_level ?? '-' }}</span>
                        </div>

                        <div class="flex justify-between items-center border-b border-base-300 pb-2">
                            <span class="font-semibold text-base-content/70">{{ __('Gender') }}</span>
                            <span class="font-medium text-base-content">{{ $this->employee->gender ?? '-' }}</span>
                        </div>

                        <div class="flex justify-between items-center pb-1">
                            <span class="font-semibold text-base-content/70">{{ __('Marital Status') }}</span>
                            <span
                                class="font-medium text-base-content">{{ $this->employee->marital_status ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- @chisel-email-verification --}}
        @if ($this->showDeleteUser)
            {{-- @end-chisel-email-verification --}}
            <livewire:pages::settings.delete-user-form />
            {{-- @chisel-email-verification --}}
        @endif
        {{-- @end-chisel-email-verification --}}
    </x-pages::settings.layout>
</section>
