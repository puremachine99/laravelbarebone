<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="bg-base-100 min-h-screen antialiased">
    <div class="drawer lg:drawer-open" x-data="{ sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true', sidebarHovered: false }" x-init="$watch('sidebarCollapsed', value => localStorage.setItem('sidebarCollapsed', value))">
        <input id="main-drawer" type="checkbox" class="drawer-toggle" />

        {{-- ─── Main Content Area ───────────────────────────────── --}}
        <div class="drawer-content flex min-w-0 flex-col">


            {{-- Mobile Top Bar --}}
            <div
                class="app-topbar border-base-300/80 bg-base-100 sticky -top-10 z-20 flex h-14 items-center gap-3 border-b border-dashed px-4 lg:hidden">
                <label for="main-drawer" aria-label="Open sidebar" class="btn btn-sm btn-square btn-ghost drawer-button">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        class="size-5 stroke-current">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </label>
                <div class="flex-1">
                    <a href="{{ route('dashboard') }}" wire:navigate class="text-lg font-semibold">
                        Smart ID Dashboard
                    </a>
                </div>
                @auth
                    <x-csv-export-button />

                    <x-dark-mode-toggle />

                    <livewire:notification-bell />

                    <div class="dropdown dropdown-end">
                        <div tabindex="0" role="button"
                            class="btn btn-ghost btn-circle btn-sm cursor-pointer">
                            <x-user-avatar size="size-8" />
                        </div>
                        <ul tabindex="0"
                            class="dropdown-content menu menu-sm bg-base-100 rounded-box border border-base-300 shadow-lg w-max min-w-56 max-w-sm mt-2 z-50 p-2">
                            <li>
                                <a href="{{ route('profile.show') }}" wire:navigate
                                    class="flex items-center gap-3 px-3 py-2 h-auto">
                                    <x-user-avatar size="size-12" department class="mb-1" />
                                    <div class="flex flex-col min-w-0 gap-1">
                                        <span
                                            class="text-sm font-semibold wrap-break-word">{{ auth()->user()->name }}</span>
                                        <x-position-badge :user="auth()->user()" class="w-fit" />
                                    </div>
                                </a>
                            </li>
                            <li>
                                <hr class="border-base-300 my-1">
                            </li>
                            <li>
                                <button type="button"
                                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                                    class="text-error w-full text-left flex items-center gap-2 cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Log out
                                </button>
                            </li>
                        </ul>
                    </div>
                @endauth
            </div>

            {{-- Desktop Top Navbar --}}
            <div
                class="app-topbar border-base-300/80 bg-base-200 sticky top-0 z-20 hidden h-16 items-center justify-between border-b border-dashed px-6 lg:flex lg:rounded-tl-2xl">
                <div class="flex items-center gap-3">
                    <button type="button" @click="sidebarCollapsed = !sidebarCollapsed"
                        aria-label="Toggle sidebar"
                        class="text-base-content/60 hover:bg-base-200 hover:text-base-content flex size-8 items-center justify-center rounded-lg transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-base-content text-lg font-semibold">{{ $title ?? 'Dashboard' }}</h1>
                </div>

                <div
                    class="absolute left-1/2 top-1/2 hidden -translate-x-1/2 -translate-y-1/2 items-center gap-2 rounded-lg bg-base-100 border border-base-300 px-3 py-1.5 xl:flex">
                    <svg xmlns="http://www.w3.org/2000/svg" class="text-base-content/50 size-4" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span
                        class="text-base-content text-sm font-semibold">{{ now()->translatedFormat('l, d F Y') }}</span>
                </div>

                @auth
                    <div class="flex items-center gap-1.5">
                        <x-csv-export-button />

                        <x-dark-mode-toggle />

                        <livewire:notification-bell />

                        <div class="dropdown dropdown-end">
                            <div tabindex="0" role="button"
                                class="btn btn-ghost btn-circle btn-sm cursor-pointer">
                                <x-user-avatar size="size-8" />
                            </div>
                            <ul tabindex="0"
                                class="dropdown-content menu bg-base-100 rounded-box border-base-300 z-50 mt-2 w-max min-w-56 max-w-sm border p-2 shadow-lg">
                                <li>
                                    <a href="{{ route('profile.show') }}" wire:navigate
                                        class="flex items-center gap-3 px-3 py-2 h-auto">
                                        <x-user-avatar size="size-12" department class="mb-1" />
                                        <div class="flex flex-col min-w-0 gap-1">
                                            <span
                                                class="text-sm font-semibold whitespace-nowrap">{{ auth()->user()->name }}</span>
                                            <x-position-badge :user="auth()->user()" class="w-fit" />
                                        </div>
                                    </a>
                                </li>
                                {{-- switch untuk darkmode, --}}
                                <hr class="border-base-300 my-1">
                                <li>
                                    
                                    <button type="button"
                                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                                        class="text-error hover:bg-error/10 flex w-full items-center gap-2 text-left cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        Logout
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-sm btn-primary">Login</a>
                @endauth
            </div>

            {{-- Page Content --}}
            <main class="min-w-0 flex-1 p-4 lg:p-6">
                {{-- Floating chips at the bottom of the canvas (behind the content, so they only show on short pages); only with the toon themes (resources/css/themes/toon.css) --}}
                <div class="toon-decor" aria-hidden="true">
                    @foreach ([
                        ['o-user-group', 'Kolaborasi', 'bottom:8%;right:5%;--d:6.5s;rotate:2deg'],
                        ['o-sparkles', 'Kerja Cerdas', 'bottom:5%;left:3%;--d:9s;rotate:-3deg'],
                        ['o-light-bulb', 'Ide Perbaikan', 'bottom:3%;left:42%;--d:7.5s;rotate:3deg'],
                    ] as [$icon, $label, $position])
                        <span class="toon-decor-chip" style="{{ $position }}">
                            <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
                        </span>
                    @endforeach
                </div>

                {{ $slot }}
            </main>
        </div>

        {{-- ─── Sidebar ─────────────────────────────────────────── --}}
        <div class="drawer-side z-30">
            <label for="main-drawer" aria-label="close sidebar" class="drawer-overlay"></label>

            <aside
                class="app-sidebar sticky top-0 bottom-0 relative flex h-dvh w-64 min-w-64 flex-col border-e text-sidebar-fg/90 transition-[width,min-width] duration-200"
                :class="sidebarCollapsed && !sidebarHovered ? 'lg:w-[76px] lg:min-w-[76px]' : 'lg:w-64 lg:min-w-64'"
                @mouseenter="sidebarCollapsed && (sidebarHovered = true)" @mouseleave="sidebarHovered = false">

                {{-- Header / Brand --}}
                <div class="flex flex-col shrink-0 gap-2 overflow-hidden border-b border-sidebar-fg/10 px-5 py-4"
                    :class="sidebarCollapsed && !sidebarHovered && 'lg:items-center lg:px-2'">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 overflow-hidden">
                        <img src="{{ asset('smartid.png') }}" alt="SmartID"
                            class="size-11 shrink-0 rounded-lg object-contain">
                        <span class="flex flex-col justify-center gap-1 leading-tight"
                            :class="sidebarCollapsed && !sidebarHovered && 'lg:hidden'">
                            <span class="text-base font-bold whitespace-nowrap text-sidebar-fg">SmartID</span>
                            @auth
                                @if ($sidebarRole = auth()->user()->getRoleNames()->first())
                                    <span
                                        class="inline-flex w-fit items-center gap-1 rounded-full border border-sidebar-accent/40 bg-linear-to-r from-sidebar-accent/20 via-sidebar-accent-soft/10 to-transparent px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider whitespace-nowrap text-sidebar-accent-soft shadow-[0_0_8px_-2px_var(--color-sidebar-accent)]">
                                        {{ $sidebarRole }}
                                    </span>
                                @endif
                            @endauth
                        </span>
                    </a>
                    <span
                        class="block w-full text-center text-[9px] font-semibold uppercase tracking-widest whitespace-nowrap text-sidebar-accent/70"
                        :class="sidebarCollapsed && !sidebarHovered && 'lg:hidden'">
                        Enterprise Resource Planner
                    </span>
                </div>

                {{-- Scrollable Navigation --}}
                <div class="sidebar-menu sidebar-dark flex-1 min-h-0 overflow-y-auto overflow-x-hidden px-2.5 pt-4 pb-4"
                    :class="sidebarCollapsed && !sidebarHovered && 'sidebar-collapsed'">

                    {{-- Dashboard --}}
                    <a @class(['menu-item', 'active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}" wire:navigate title="Dashboard">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span class="grow">Dashboard</span>
                    </a>

                    {{-- Technology --}}
                    @can('access-technology')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Technology</p>
                        <div class="space-y-0.5">
                            <div class="group collapse">
                                <input aria-label="Technology menu toggle" class="peer" type="checkbox"
                                    {{ request()->routeIs('tech.*', 'dev.errors.*') ? 'checked' : '' }}>
                                <div class="collapse-title px-2.5 py-2" title="Technology">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                    </svg>
                                    <span class="grow">Technology</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon size-3.5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                                <div class="collapse-content ms-6.5 !p-0">
                                    <div class="mt-0.5 space-y-0.5">
                                        @can('tech-sentry.view')
                                        <a @class(['menu-item', 'active' => request()->routeIs('dev.errors.*')]) href="{{ route('dev.errors.index') }}"
                                            wire:navigate><x-icon name="o-exclamation-triangle" class="size-4" /><span class="grow">Sentry</span></a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endcan

                    {{-- Data Master --}}
                    @can('access-internal-org')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Data Master</p>
                        <div class="space-y-0.5">

                            @can('access-internal-org')
                                <div class="group collapse">
                                    <input aria-label="Internal menu toggle" class="peer" type="checkbox"
                                        {{ request()->routeIs('departments.*', 'positions.*', 'employees.*', 'admin.*') ? 'checked' : '' }}>
                                    <div class="collapse-title px-2.5 py-2" title="Internal">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        <span class="grow">Internal</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon size-3.5"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                    <div class="collapse-content ms-6.5 !p-0">
                                        <div class="mt-0.5 space-y-0.5">
                                            @can('departments.view')
                                            <a @class(['menu-item', 'active' => request()->routeIs('departments.*')]) href="{{ route('departments.index') }}"
                                                wire:navigate><x-icon name="o-building-office-2" class="size-4" /><span class="grow">Departments</span></a>
                                            @endcan
                                            @can('positions.view')
                                            <a @class(['menu-item', 'active' => request()->routeIs('positions.*')]) href="{{ route('positions.index') }}"
                                                wire:navigate><x-icon name="o-briefcase" class="size-4" /><span class="grow">Positions</span></a>
                                            @endcan
                                            @can('employees.view')
                                            <a @class(['menu-item', 'active' => request()->routeIs('employees.*')]) href="{{ route('employees.index') }}"
                                                wire:navigate><x-icon name="o-users" class="size-4" /><span class="grow">Employees</span></a>
                                            @endcan
                                            <a @class(['menu-item', 'active' => request()->routeIs('admin.roles')]) href="{{ route('admin.roles') }}"
                                                wire:navigate><x-icon name="o-shield-check" class="size-4" /><span class="grow">Manage Roles</span></a>
                                        </div>
                                    </div>
                                </div>
                            @endcan

                        </div>
                    @endcan
                </div>

                {{-- Guest Login --}}
                @guest
                    <div class="shrink-0 border-t border-sidebar-fg/10 px-4 py-3">
                        <a href="{{ route('login') }}" title="Login ke Dashboard" class="btn btn-sm btn-primary w-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            <span :class="sidebarCollapsed && !sidebarHovered && 'lg:hidden'">Login ke Dashboard</span>
                        </a>
                    </div>
                @endguest

                {{-- Version & Copyright --}}
                <div class="shrink-0 border-t border-sidebar-fg/10 px-4 py-3 text-center">
                    <p class="text-[11px] font-medium text-sidebar-fg/40"
                        :class="sidebarCollapsed && !sidebarHovered && 'lg:hidden'">
                        SmartID ERP v{{ config('app.version', '1.0.0') }}
                    </p>
                    <p class="text-[10px] text-sidebar-fg/25"
                        :class="sidebarCollapsed && !sidebarHovered && 'lg:hidden'">
                        &copy; {{ now()->year }} SmartID
                    </p>
                </div>

            </aside>
        </div>
    </div>

    @auth
        <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
            @csrf
        </form>
    @endauth

    {{-- Toasts, alert/confirm popups and session flash messages: resources/js/notify.js --}}
    <x-nakama.toaster />
    <x-nakama.dialog-host />
    <x-nakama.flash />
    <x-csv-download-manager />
</body>

</html>
