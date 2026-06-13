<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
 #[Locked]
 public array $recoveryCodes = [];

 /**
 * Mount the component.
 */
 public function mount(): void
 {
 $this->loadRecoveryCodes();
 }

 /**
 * Generate new recovery codes for the user.
 */
 public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
 {
 $generateNewRecoveryCodes(auth()->user());

 $this->loadRecoveryCodes();
 }

 /**
 * Load the recovery codes for the user.
 */
 private function loadRecoveryCodes(): void
 {
 $user = auth()->user();

 if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
 try {
 $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
 } catch (Exception) {
 $this->addError('recoveryCodes','Failed to load recovery codes');

 $this->recoveryCodes = [];
 }
 }
 }
}; ?>

<div
 class="py-6 space-y-6 border shadow-sm rounded-xl border-base-300"
 wire:cloak
 x-data="{ showRecoveryCodes: false }"
>
 <div class="px-6 space-y-2">
 <div class="flex items-center gap-2">
 <x-icon name="o-lock-closed" class="w-5 h-5 text-base-content/50" />
 <h3 class="text-lg font-bold text-base-content">{{ __('2FA recovery codes') }}</h3>
 </div>
 <p class="text-sm text-base-content/50">
 {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
 </p>
 </div>

 <div class="px-6">
 <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
 <x-button
 x-show="!showRecoveryCodes"
 icon="o-eye"
 variant="primary"
 @click="showRecoveryCodes = true;"
 aria-expanded="false"
 aria-controls="recovery-codes-section"
 label="{{ __('View recovery codes') }}"
 />

 <x-button
 x-show="showRecoveryCodes"
 icon="o-eye-slash"
 variant="primary"
 @click="showRecoveryCodes = false"
 aria-expanded="true"
 aria-controls="recovery-codes-section"
 label="{{ __('Hide recovery codes') }}"
 />

 @if (filled($recoveryCodes))
 <x-button
 x-show="showRecoveryCodes"
 icon="o-arrow-path"
 class="btn-outline"
 wire:click="regenerateRecoveryCodes"
 label="{{ __('Regenerate codes') }}"
 />
 @endif
 </div>

 <div
 x-show="showRecoveryCodes"
 x-transition
 id="recovery-codes-section"
 class="relative overflow-hidden"
 x-bind:aria-hidden="!showRecoveryCodes"
 >
 <div class="mt-3 space-y-3">
 @error('recoveryCodes')
 <div class="alert alert-error shadow-sm">
 <x-icon name="o-x-circle" class="w-5 h-5 text-white" />
 <span class="text-white">{{ $message }}</span>
 </div>
 @enderror

 @if (filled($recoveryCodes))
 <div
 class="grid gap-1 p-4 font-mono text-sm rounded-lg bg-base-300"
 role="list"
 aria-label="{{ __('Recovery codes') }}"
 >
 @foreach($recoveryCodes as $code)
 <div
 role="listitem"
 class="select-text"
 wire:loading.class="opacity-50 animate-pulse"
 >
 {{ $code }}
 </div>
 @endforeach
 </div>
 <p class="text-xs text-base-content/40 mt-2">
 {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.') }}
 </p>
 @endif
 </div>
 </div>
 </div>
</div>
