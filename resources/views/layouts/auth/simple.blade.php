<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    {{-- Auth pages always use the default light look: no user theme, no dark mode --}}
    @include('partials.head', ['lockTheme' => true])
</head>

<body class="auth-paper min-h-screen antialiased">
    <div class="grid min-h-svh lg:grid-cols-2">

        {{-- Left: photo collage of SmartID events, slowly drifting (animations: .auth-hero-* in resources/css/app.css) --}}
        <div class="auth-hero relative hidden overflow-hidden bg-[#0d2a4f] lg:block">
            <img src="{{ asset('images/auth/login-collage.webp') }}" alt="" aria-hidden="true"
                class="auth-hero-image absolute inset-0 size-full object-cover" decoding="async" fetchpriority="high">

            {{-- Navy wash so the wordmark stays readable over the faces --}}
            <div class="pointer-events-none absolute inset-0 bg-linear-to-t from-[#071730] via-[#0d2a4f]/50 to-[#0d2a4f]/10"></div>
            <div class="pointer-events-none absolute inset-0 bg-linear-to-r from-[#071730]/60 via-transparent to-transparent"></div>
            <div class="auth-hero-sheen pointer-events-none absolute inset-0"></div>

            <div class="absolute inset-x-0 bottom-0 z-10 p-10 xl:p-14">
                {{-- The logo is sized in em, so it is exactly as tall as the "SmartID" type --}}
                <h1 class="auth-hero-reveal flex items-center gap-4 text-6xl leading-none xl:text-7xl" style="--reveal-delay: 200ms">
                    <img src="{{ asset('smartid.png') }}" alt="" aria-hidden="true" class="h-[1em] w-auto object-contain drop-shadow-lg">
                    <span class="flex items-baseline gap-4">
                        <span class="font-extrabold tracking-tight text-white drop-shadow-lg">SmartID</span>
                        <span class="text-2xl font-semibold tracking-[0.35em] text-amber-400 xl:text-3xl">ERP</span>
                    </span>
                </h1>
            </div>
        </div>

        {{-- Right: Form on lightly textured white paper (.auth-paper) --}}
        <div class="flex flex-col p-6 md:p-10">
            <div class="flex flex-1 flex-col items-center justify-center">
                <div class="flex w-full max-w-sm flex-col gap-2">
                    <a href="{{ route('home') }}" class="mb-4 flex items-baseline gap-2 lg:hidden" wire:navigate>
                        <span class="text-3xl font-extrabold tracking-tight text-[#0d2a4f]">SmartID</span>
                        <span class="text-sm font-semibold tracking-[0.3em] text-amber-500">ERP</span>
                    </a>
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>
            </div>

            <p class="pt-6 text-center text-xs text-base-content/40">
                SmartID ERP v{{ config('app.version', '1.0.0') }} &middot; &copy; {{ now()->year }} SmartID
            </p>
        </div>
    </div>

    <x-nakama.toaster />
    <x-nakama.dialog-host />
</body>

</html>
