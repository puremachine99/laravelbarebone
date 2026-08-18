<x-layouts::auth :title="__('Set your password')">
 <div class="flex flex-col gap-6">
 <x-auth-header :title="__('Welcome')" :description="__('Create a password to activate your account')" />

 <!-- Session Status -->
 <x-auth-session-status class="text-center" :status="session('status')" />

 <form method="POST" action="{{ route('password.setup.store', ['token' => $token]) }}" class="flex flex-col gap-6">
 @csrf

 <!-- Email Address -->
 <x-input
 name="email"
 value="{{ $email }}"
 label="{{ __('Email') }}"
 type="email"
 readonly
 />

 <!-- Password -->
 <x-input
 name="password"
 label="{{ __('Password') }}"
 type="password"
 required
 autofocus
 autocomplete="new-password"
 placeholder="{{ __('Password') }}"
 />

 <!-- Confirm Password -->
 <x-input
 name="password_confirmation"
 label="{{ __('Confirm password') }}"
 type="password"
 required
 autocomplete="new-password"
 placeholder="{{ __('Confirm password') }}"
 />

 <div class="flex items-center justify-end">
 <x-button type="submit" label="{{ __('Activate account') }}" class="btn-primary w-full" data-test="set-password-button" />
 </div>
 </form>
 </div>
</x-layouts::auth>
