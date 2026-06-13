<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
 #[Locked]
 public bool $requiresConfirmation;

 #[Locked]
 public string $qrCodeSvg ='';

 #[Locked]
 public string $manualSetupKey ='';

 public bool $showVerificationStep = false;

 public bool $setupComplete = false;

 public bool $showModal = false;

 #[Validate('required|string|size:6', onUpdate: false)]
 public string $code ='';

 /**
 * Mount the component.
 */
 public function mount(bool $requiresConfirmation): void
 {
 $this->requiresConfirmation = $requiresConfirmation;
 }

 #[On('start-two-factor-setup')]
 public function startTwoFactorSetup(): void
 {
 $enableTwoFactorAuthentication = app(EnableTwoFactorAuthentication::class);
 $enableTwoFactorAuthentication(auth()->user());

 $this->loadSetupData();
 $this->showModal = true;
 }

 /**
 * Load the two-factor authentication setup data for the user.
 */
 private function loadSetupData(): void
 {
 $user = auth()->user()?->fresh();

 try {
 if (! $user || ! $user->two_factor_secret) {
 throw new Exception('Two-factor setup secret is not available.');
 }

 $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
 $this->manualSetupKey = decrypt($user->two_factor_secret);
 } catch (Exception) {
 $this->addError('setupData','Failed to fetch setup data.');

 $this->reset('qrCodeSvg','manualSetupKey');
 }
 }

 /**
 * Show the two-factor verification step if necessary.
 */
 public function showVerificationIfNecessary(): void
 {
 if ($this->requiresConfirmation) {
 $this->showVerificationStep = true;

 $this->resetErrorBag();

 return;
 }

 $this->closeModal();
 $this->dispatch('two-factor-enabled');
 }

 /**
 * Confirm two-factor authentication for the user.
 */
 public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
 {
 $this->validate();

 $confirmTwoFactorAuthentication(auth()->user(), $this->code);

 $this->setupComplete = true;

 $this->closeModal();

 $this->dispatch('two-factor-enabled');
 }

 /**
 * Reset two-factor verification state.
 */
 public function resetVerification(): void
 {
 $this->reset('code','showVerificationStep');

 $this->resetErrorBag();
 }

 /**
 * Close the two-factor authentication modal.
 */
 public function closeModal(): void
 {
 $this->showModal = false;
 $this->reset(
'code',
'manualSetupKey',
'qrCodeSvg',
'showVerificationStep',
'setupComplete',
 );

 $this->resetErrorBag();
 }

 /**
 * Get the current modal configuration state.
 */
 #[Computed]
 public function modalConfig(): array
 {
 if ($this->setupComplete) {
 return [
'title' => __('Two-factor authentication enabled'),
'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
'buttonText' => __('Close'),
 ];
 }

 if ($this->showVerificationStep) {
 return [
'title' => __('Verify authentication code'),
'description' => __('Enter the 6-digit code from your authenticator app.'),
'buttonText' => __('Continue'),
 ];
 }

 return [
'title' => __('Enable two-factor authentication'),
'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
'buttonText' => __('Continue'),
 ];
 }
}; ?>

<x-modal
 wire:model="showModal"
 class="max-w-md"
>
 <div class="space-y-6">
 <div class="flex flex-col items-center space-y-4">
 <div class="p-0.5 w-auto rounded-full border border-base-300 bg-base-100 shadow-sm">
 <div class="p-2.5 rounded-full border border-base-300 overflow-hidden bg-base-200 relative">
 <div class="flex items-stretch absolute inset-0 w-full h-full divide-x [&>div]:flex-1 divide-base-300 justify-around opacity-50">
 @for ($i = 1; $i <= 5; $i++)
 <div></div>
 @endfor
 </div>

 <div class="flex flex-col items-stretch absolute w-full h-full divide-y [&>div]:flex-1 inset-0 divide-base-300 justify-around opacity-50">
 @for ($i = 1; $i <= 5; $i++)
 <div></div>
 @endfor
 </div>

 <x-icon name="o-qr-code" class="w-8 h-8 relative z-20 text-base-content/80" />
 </div>
 </div>

 <div class="space-y-2 text-center">
 <h3 class="text-lg font-bold text-base-content">{{ $this->modalConfig['title'] }}</h3>
 <p class="text-sm text-base-content/50">{{ $this->modalConfig['description'] }}</p>
 </div>
 </div>

 @if ($showVerificationStep)
 <div class="space-y-6">
 <div
 class="flex flex-col items-center space-y-3 justify-center"
 x-data
 x-init="$nextTick(() => $el.querySelector('input')?.focus())"
 >
 <x-input
 name="code"
 wire:model="code"
 maxlength="6"
 placeholder="000000"
 class="text-center font-mono text-xl tracking-widest max-w-[150px]"
 required
 />
 </div>

 <div class="flex items-center space-x-3">
 <x-button
 class="flex-1 btn-ghost"
 wire:click="resetVerification"
 label="{{ __('Back') }}"
 />

 <x-button
 class="flex-1 btn-primary"
 wire:click="confirmTwoFactor"
 label="{{ __('Confirm') }}"
 x-bind:disabled="$wire.code.length < 6"
 />
 </div>
 </div>
 @else
 @error('setupData')
 <div class="alert alert-error shadow-sm">
 <x-icon name="o-x-circle" class="w-5 h-5 text-white" />
 <span class="text-white">{{ $message }}</span>
 </div>
 @enderror

 <div class="flex justify-center">
 <div class="relative w-64 overflow-hidden border rounded-lg border-base-300 aspect-square">
 @empty($qrCodeSvg)
 <div class="absolute inset-0 flex items-center justify-center bg-base-100 animate-pulse">
 <span class="loading loading-spinner loading-md"></span>
 </div>
 @else
 <div x-data class="flex items-center justify-center h-full p-4">
 <div
 class="bg-white p-3 rounded"
 >
 {!! $qrCodeSvg !!}
 </div>
 </div>
 @endempty
 </div>
 </div>

 <div>
 <x-button
 :disabled="$errors->has('setupData')"
 class="w-full btn-primary"
 wire:click="showVerificationIfNecessary"
 label="{{ $this->modalConfig['buttonText'] }}"
 />
 </div>

 <div class="space-y-4">
 <div class="relative flex items-center justify-center w-full">
 <div class="absolute inset-0 w-full h-px top-1/2 bg-base-300"></div>
 <span class="relative px-2 text-sm bg-base-100 text-base-content/60">
 {{ __('or, enter the code manually') }}
 </span>
 </div>

 <div
 class="flex items-center space-x-2"
 x-data="{
 copied: false,
 async copy() {
 try {
 await navigator.clipboard.writeText('{{ $manualSetupKey }}');
 this.copied = true;
 setTimeout(() => this.copied = false, 1500);
 } catch (e) {
 console.warn('Could not copy to clipboard');
 }
 }
 }"
 >
 <div class="flex items-stretch w-full border rounded-xl">
 @empty($manualSetupKey)
 <div class="flex items-center justify-center w-full p-3 bg-base-200">
 <span class="loading loading-spinner loading-xs"></span>
 </div>
 @else
 <input
 type="text"
 readonly
 value="{{ $manualSetupKey }}"
 class="w-full p-3 bg-transparent outline-none text-base-content text-sm font-mono"
 />

 <button
 @click="copy()"
 class="px-3 transition-colors border-l cursor-pointer border-base-300"
 >
 <x-icon x-show="!copied" name="o-document-duplicate" class="w-5 h-5 text-base-content/50" />
 <x-icon x-show="copied" name="o-check" class="w-5 h-5 text-green-500" />
 </button>
 @endempty
 </div>
 </div>
 </div>
 @endif
 </div>
</x-modal>
