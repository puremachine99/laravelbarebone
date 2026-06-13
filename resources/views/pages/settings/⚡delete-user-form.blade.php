<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 space-y-6">
 <div class="relative mb-5">
 <h3 class="text-lg font-bold text-base-content">{{ __('Delete account') }}</h3>
 <p class="text-sm text-base-content/50 mt-1">{{ __('Delete your account and all of its resources') }}</p>
 </div>

 <div>
 <x-button class="btn-error text-white" data-test="delete-user-button" wire:click="$dispatch('confirm-user-deletion')" label="{{ __('Delete account') }}" />
 </div>

 <livewire:pages::settings.delete-user-modal />
</section>
