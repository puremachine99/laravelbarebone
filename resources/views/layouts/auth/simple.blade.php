<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white antialiased dark:bg-neutral-900">
    <div class="grid min-h-svh lg:grid-cols-2">

        {{-- Left: Branded Visual Panel --}}
        <div class="relative hidden overflow-hidden bg-[#0d2a4f] lg:flex lg:flex-col lg:items-center lg:justify-center"
            style='background-image:url("data:image/svg+xml,%3Csvg xmlns=&apos;http://www.w3.org/2000/svg&apos; width=&apos;64&apos; height=&apos;111&apos; viewBox=&apos;0 0 64 111&apos;%3E%3Cpath fill=&apos;none&apos; stroke=&apos;%23FFC226&apos; stroke-opacity=&apos;0.18&apos; stroke-width=&apos;1&apos; d=&apos;M32 0 L64 18.5 L64 55.5 L32 74 L0 55.5 L0 18.5 Z M32 37 L64 55.5 L64 92.5 L32 111 L0 92.5 L0 55.5 Z&apos;/%3E%3C/svg%3E"); background-repeat: repeat;'>
            <div class="pointer-events-none absolute inset-0 bg-linear-to-t from-[#0d2a4f] via-transparent to-[#0d2a4f]/50"></div>
            <div class="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-amber-400/10 blur-3xl"></div>

            <div class="relative z-10 flex flex-col items-center gap-6 px-10 text-center">
                <img src="{{ asset('smartid.png') }}" alt="SmartID" class="size-24 rounded-2xl object-contain drop-shadow-lg">
                <div>
                    <h1 class="text-3xl font-bold text-white">SmartID <span class="text-amber-400">ERP</span></h1>
                    <p class="mt-3 max-w-xs text-sm text-white/60">
                        Kelola operasional, funding, dan partnership dalam satu dashboard terintegrasi.
                    </p>
                </div>
            </div>

            <p class="absolute bottom-6 z-10 text-xs text-white/30">
                SmartID ERP v{{ config('app.version', '1.0.0') }} &middot; &copy; {{ now()->year }} SmartID
            </p>
        </div>

        {{-- Right: Form --}}
        <div class="flex flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-2">
                <a href="{{ route('home') }}" class="mb-2 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                    <img src="{{ asset('smartid.png') }}" alt="{{ config('app.name', 'SmartID') }}"
                        class="size-14 rounded-lg object-contain">
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>

            <p class="text-center text-xs text-base-content/40 lg:hidden">
                SmartID ERP v{{ config('app.version', '1.0.0') }} &middot; &copy; {{ now()->year }} SmartID
            </p>
        </div>
    </div>

    @persist('toast')
        <x-toast />
    @endpersist
</body>

</html>
