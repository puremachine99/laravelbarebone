<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';
    public bool $showModal = false;

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->forceDelete();

        $this->redirect('/', navigate: true);
    }

    #[On('confirm-user-deletion')]
    public function showDeletionModal(): void
    {
        $this->password = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->password = '';
    }
}; ?>

<x-modal wire:model="showModal" class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <h3 class="text-lg font-bold text-base-content">{{ __('Are you sure you want to delete your account?') }}</h3>
            <p class="text-sm text-base-content/50 mt-1">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>
        </div>

        <x-input wire:model="password" label="{{ __('Password') }}" type="password" required />

        <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
            <x-button class="btn-ghost" wire:click="closeModal" label="{{ __('Cancel') }}" />
            <x-button class="btn-error text-white" type="submit" label="{{ __('Delete account') }}" data-test="confirm-delete-user-button" />
        </div>
    </form>
</x-modal>
