<x-layouts::auth :title="__('Log in')">
 <div class="flex flex-col gap-6">
 <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

 <!-- Session Status -->
 <x-auth-session-status class="text-center" :status="session('status')" />

 <x-passkey-verify />

 <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
 @csrf

 <!-- Email Address -->
 <x-input
 name="email"
 label="{{ __('Email address') }}"
 value="{{ old('email') }}"
 type="email"
 required
 autofocus
 autocomplete="email"
 placeholder="email@example.com"
 />

 <!-- Password -->
 <div class="relative" x-data="{ showPassword: false }">
 <x-input
 name="password"
 label="{{ __('Password') }}"
 type="password"
 x-bind:type="showPassword ? 'text' : 'password'"
 required
 autocomplete="current-password"
 placeholder="{{ __('Password') }}"
 >
 <x-slot:append>
 <button type="button" tabindex="-1" @click="showPassword = !showPassword" class="btn btn-ghost join-item px-3">
 <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="size-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
 <svg x-show="showPassword" xmlns="http://www.w3.org/2000/svg" class="size-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 012.132-3.411m3.09-2.687A9.958 9.958 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.958 9.958 0 01-1.563 3.029M3 3l18 18M9.878 9.878a3 3 0 104.243 4.243" /></svg>
 </button>
 </x-slot:append>
 </x-input>

 @if (Route::has('password.request'))
 <a class="absolute top-0 text-sm right-0 link link-primary" href="{{ route('password.request') }}" wire:navigate>
 {{ __('Forgot your password?') }}
 </a>
 @endif
 </div>

 <!-- Remember Me -->
 <x-checkbox name="remember" label="{{ __('Remember me') }}" />

 <div class="flex items-center justify-end">
 <x-button label="{{ __('Log in') }}" type="submit" class="btn-primary w-full" data-test="login-button" />
 </div>
 </form>

 <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-base-content/70">
 <span>{{ __('Don\'t have an account?') }}</span>
 <a class="link link-primary" href="{{ route('register') }}" wire:navigate>{{ __('Sign up') }}</a>
 </div>
 </div>
</x-layouts::auth>
