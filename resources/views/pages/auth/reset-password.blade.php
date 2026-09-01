<x-layouts::auth :title="__('Reset password')">
 <div class="flex flex-col gap-6">
 <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

 <!-- Session Status -->
 <x-auth-session-status class="text-center" :status="session('status')" />

 <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
 @csrf
 <!-- Token -->
 <input type="hidden" name="token" value="{{ request()->route('token') }}">

 <!-- Email Address -->
 <x-input
 name="email"
 value="{{ old('email', request('email')) }}"
 label="{{ __('Email') }}"
 type="email"
 required
 autocomplete="email"
 error-field="email"
 />

 <!-- Password -->
 <div
 x-data="{
 password: '',
 get hasMinLength() { return this.password.length >= 12 },
 get hasMixedCase() { return /[a-z]/.test(this.password) && /[A-Z]/.test(this.password) },
 get hasNumber() { return /[0-9]/.test(this.password) },
 get hasSymbol() { return /[^A-Za-z0-9]/.test(this.password) },
 get score() { return [this.hasMinLength, this.hasMixedCase, this.hasNumber, this.hasSymbol].filter(Boolean).length },
 get strengthLabel() {
 if (this.password.length === 0) return '';
 if (this.score <= 1) return '{{ __('Weak') }}';
 if (this.score <= 3) return '{{ __('Medium') }}';
 return '{{ __('Strong') }}';
 },
 get strengthTextClass() {
 if (this.score <= 1) return 'text-error';
 if (this.score <= 3) return 'text-warning';
 return 'text-success';
 },
 get strengthBarClass() {
 if (this.score <= 1) return 'bg-error';
 if (this.score <= 3) return 'bg-warning';
 return 'bg-success';
 },
 }"
 class="flex flex-col gap-2"
 >
 <x-password
 name="password"
 x-model="password"
 label="{{ __('Password') }}"
 required
 autocomplete="new-password"
 placeholder="{{ __('Password') }}"
 error-field="password"
 right
 />

 <div x-show="password.length > 0" x-cloak class="flex flex-col gap-1.5 rounded-lg border border-base-300 p-3 text-sm">
 <div class="flex items-center justify-between">
 <span class="font-medium">{{ __('Password strength') }}</span>
 <span :class="[strengthTextClass, 'font-semibold']" x-text="strengthLabel"></span>
 </div>

 <div class="flex gap-1">
 <template x-for="i in 4">
 <div class="h-1.5 flex-1 rounded-full" :class="i <= score ? strengthBarClass : 'bg-base-300'"></div>
 </template>
 </div>

 <ul class="mt-1 flex flex-col gap-1">
 <li class="flex items-center gap-2" :class="hasMinLength ? 'text-success' : 'text-base-content/50'">
 <x-mary-icon name="o-check-circle" x-show="hasMinLength" class="w-4 h-4" />
 <x-mary-icon name="o-x-circle" x-show="!hasMinLength" class="w-4 h-4" />
 <span>{{ __('At least 12 characters') }}</span>
 </li>
 <li class="flex items-center gap-2" :class="hasMixedCase ? 'text-success' : 'text-base-content/50'">
 <x-mary-icon name="o-check-circle" x-show="hasMixedCase" class="w-4 h-4" />
 <x-mary-icon name="o-x-circle" x-show="!hasMixedCase" class="w-4 h-4" />
 <span>{{ __('Uppercase and lowercase letters') }}</span>
 </li>
 <li class="flex items-center gap-2" :class="hasNumber ? 'text-success' : 'text-base-content/50'">
 <x-mary-icon name="o-check-circle" x-show="hasNumber" class="w-4 h-4" />
 <x-mary-icon name="o-x-circle" x-show="!hasNumber" class="w-4 h-4" />
 <span>{{ __('At least one number') }}</span>
 </li>
 <li class="flex items-center gap-2" :class="hasSymbol ? 'text-success' : 'text-base-content/50'">
 <x-mary-icon name="o-check-circle" x-show="hasSymbol" class="w-4 h-4" />
 <x-mary-icon name="o-x-circle" x-show="!hasSymbol" class="w-4 h-4" />
 <span>{{ __('At least one symbol') }}</span>
 </li>
 </ul>
 </div>
 </div>

 <!-- Confirm Password -->
 <x-password
 name="password_confirmation"
 label="{{ __('Confirm password') }}"
 required
 autocomplete="new-password"
 placeholder="{{ __('Confirm password') }}"
 right
 />

 <div class="flex items-center justify-end">
 <x-button type="submit" label="{{ __('Reset password') }}" class="btn-primary w-full" data-test="reset-password-button" />
 </div>
 </form>
 </div>
</x-layouts::auth>
