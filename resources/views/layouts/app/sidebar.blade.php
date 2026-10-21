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
                {{-- Floating chips at the bottom of the canvas (behind the content, so they only show on short pages); only with the Nakama Desk theme (resources/css/themes/desk.css) --}}
                <div class="desk-decor" aria-hidden="true">
                    @foreach ([
                        ['o-user-group', 'Kolaborasi', 'bottom:8%;right:5%;--d:6.5s;rotate:2deg'],
                        ['o-sparkles', 'Kerja Cerdas', 'bottom:5%;left:3%;--d:9s;rotate:-3deg'],
                        ['o-light-bulb', 'Ide Perbaikan', 'bottom:3%;left:42%;--d:7.5s;rotate:3deg'],
                    ] as [$icon, $label, $position])
                        <span class="desk-decor-chip" style="{{ $position }}">
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

                    {{-- Executive --}}
                    @can('view-executive-dashboard')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Executive</p>
                        <a @class([
                            'menu-item',
                            'active' => request()->routeIs('executive-dashboard'),
                        ]) href="{{ route('executive-dashboard') }}" wire:navigate
                            title="Executive Dashboard">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                            </svg>
                            <span class="grow">Executive Dashboard</span>
                        </a>
                    @endcan

                    {{-- Talent & OKRs --}}
                    @can('manage-talent-okr')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Talent & OKRs</p>
                        <a @class(['menu-item', 'active' => request()->routeIs('leaderboard')]) href="{{ route('leaderboard') }}" wire:navigate
                            title="Talent Leaderboard">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="grow">Talent Leaderboard</span>
                        </a>
                    @endcan

                    {{-- Sales Pipeline --}}
                    @can('access-partnership')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Sales Pipeline</p>
                        <div class="space-y-0.5">
                            <div class="group collapse">
                                <input aria-label="Partnership menu toggle" class="peer" type="checkbox"
                                    {{ request()->routeIs('contact.*', 'partnership.*', 'partnership-employees.*') ? 'checked' : '' }}>
                                <div class="collapse-title px-2.5 py-2" title="Partnership">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                    </svg>
                                    <span class="grow">Partnership</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon size-3.5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                                <div class="collapse-content ms-6.5 !p-0">
                                    <div class="mt-0.5 space-y-0.5">
                                        @can('partnership-dashboard.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('partnership.dashboard'),
                                        ]) href="{{ route('partnership.dashboard') }}"
                                            wire:navigate>
                                            <span class="grow">Dashboard</span>
                                        </a>
                                        @endcan
                                        @can('partnership-contacts.view')
                                        <a @class(['menu-item', 'active' => request()->routeIs('contact.*')]) href="{{ route('contact.index') }}" wire:navigate>
                                            <span class="grow">Contact</span>
                                        </a>
                                        @endcan
                                        @can('partnership-pitching.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('partnership.pitching'),
                                        ]) href="{{ route('partnership.pitching') }}"
                                            wire:navigate>
                                            <span class="grow">Pitching</span>
                                        </a>
                                        @endcan
                                        @can('partnership-realisasi.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('partnership.realisasi'),
                                        ]) href="{{ route('partnership.realisasi') }}"
                                            wire:navigate>
                                            <span class="grow">Realisasi</span>
                                        </a>
                                        @endcan
                                        @can('partnership-pmo.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('partnership-employees.*'),
                                        ]) href="{{ route('partnership-employees.index') }}"
                                            wire:navigate>
                                            <span class="grow">Management PMO</span>
                                        </a>
                                        @endcan
                                        @can('partnership-data-coverage.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('partnership.data-coverage'),
                                        ]) href="{{ route('partnership.data-coverage') }}"
                                            wire:navigate>
                                            <span class="grow">Data Coverage</span>
                                        </a>
                                        @endcan
                                        @can('partnership-activity-log.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('partnership.activity-log'),
                                        ]) href="{{ route('partnership.activity-log') }}"
                                            wire:navigate>
                                            <span class="grow">Activity Log</span>
                                        </a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endcan

                    {{-- Finance --}}
                    @can('access-finance')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Finance</p>
                        <div class="space-y-0.5">
                            <div class="group collapse">
                                <input aria-label="Finance menu toggle" class="peer" type="checkbox"
                                    {{ request()->routeIs('finance.*') ? 'checked' : '' }}>
                                <div class="collapse-title px-2.5 py-2" title="Finance">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span class="grow">Finance</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon size-3.5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                                <div class="collapse-content ms-6.5 !p-0">
                                    <div class="mt-0.5 space-y-0.5">
                                        @can('finance-intake.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('finance.intake'),
                                        ]) href="{{ route('finance.intake') }}"
                                            wire:navigate>
                                            <span class="grow">Intake Queue</span>
                                        </a>
                                        @endcan
                                        @can('finance-reconciliation.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('finance.reconciliation'),
                                        ]) href="{{ route('finance.reconciliation') }}"
                                            wire:navigate>
                                            <span class="grow">Reconciliation</span>
                                        </a>
                                        @endcan
                                        @can('finance-disbursements.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('finance.disbursements'),
                                        ]) href="{{ route('finance.disbursements') }}"
                                            wire:navigate>
                                            <span class="grow">Disbursements</span>
                                        </a>
                                        @endcan
                                        @can('finance-clearance-archive.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('finance.clearance-archive'),
                                        ]) href="{{ route('finance.clearance-archive') }}"
                                            wire:navigate>
                                            <span class="grow">Clearance Archive</span>
                                        </a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endcan

                    {{-- Funding --}}
                    @can('access-investors')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Funding</p>
                        <div class="space-y-0.5">
                            <div class="group collapse">
                                <input aria-label="Investors menu toggle" class="peer" type="checkbox"
                                    {{ request()->routeIs('investors.*') ? 'checked' : '' }}>
                                <div class="collapse-title px-2.5 py-2" title="Investors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="grow">Investors</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon size-3.5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                                <div class="collapse-content ms-6.5 !p-0">
                                    <div class="mt-0.5 space-y-0.5">
                                        @can('investors-dashboard.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('investors.index'),
                                        ]) href="{{ route('investors.index') }}"
                                            wire:navigate>
                                            <span class="grow">Dashboard</span>
                                        </a>
                                        @endcan
                                        @can('investors-monthly.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('investors.monthly'),
                                        ]) href="{{ route('investors.monthly') }}"
                                            wire:navigate>
                                            <span class="grow">Monthly Achievement</span>
                                        </a>
                                        @endcan
                                        @can('investors-conversion.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('investors.conversion'),
                                        ]) href="{{ route('investors.conversion') }}"
                                            wire:navigate>
                                            <span class="grow">Monthly Conversion</span>
                                        </a>
                                        @endcan
                                        @can('fo-funnel.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('investors.funnel'),
                                        ]) href="{{ route('investors.funnel') }}"
                                            wire:navigate>
                                            <span class="grow">Funnel</span>
                                        </a>
                                        @endcan
                                        @can('fo-contacts.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('investors.contacts'),
                                        ]) href="{{ route('investors.contacts') }}"
                                            wire:navigate>
                                            <span class="grow">Contacts</span>
                                        </a>
                                        @endcan
                                        @can('fo-targets.view')
                                            <a @class([
                                                'menu-item',
                                                'active' => request()->routeIs('investors.management-fo'),
                                            ]) href="{{ route('investors.management-fo') }}"
                                                wire:navigate>
                                                <span class="grow">Management FO</span>
                                            </a>
                                        @endcan
                                        @hasanyrole('Investor Admin|Super Admin|Funding Officer')
                                            @can('investors-activity-log.view')
                                            <a @class([
                                                'menu-item',
                                                'active' => request()->routeIs('investors.activity-log'),
                                            ]) href="{{ route('investors.activity-log') }}"
                                                wire:navigate>
                                                <span class="grow">Activity Log</span>
                                                {{-- <span class="badge badge-warning badge-xs">Beta</span> --}}
                                            </a>
                                            @endcan
                                        @endhasanyrole
                                        @can('investor-documents.view')
                                        <a @class([
                                            'menu-item',
                                            'active' => request()->routeIs('investors.docs'),
                                        ]) href="{{ route('investors.docs') }}"
                                            wire:navigate>
                                            <span class="grow">Master Document</span>
                                        </a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endcan

                    {{-- Technology --}}
                    @can('access-technology')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Technology</p>
                        <div class="space-y-0.5">
                            @can('tech-timeline-calc.view')
                            <a @class(['menu-item', 'active' => request()->routeIs('tech.timeline-calc')]) href="{{ route('tech.timeline-calc') }}"
                                wire:navigate title="Timeline Calculator">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 7h6m0 10v-3m-3 3v-6m-3 6v-1m5-16H8a2 2 0 00-2 2v18a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2z" />
                                </svg>
                                <span class="grow">Timeline Calculator</span>
                            </a>
                            @endcan
                            @can('tech-sentry.view')
                            <a @class(['menu-item', 'active' => request()->routeIs('dev.errors.*')]) href="{{ route('dev.errors.index') }}"
                                wire:navigate title="Sentry">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                                <span class="grow">Sentry</span>
                            </a>
                            @endcan
                            @can('tech-ai-monitor.view')
                            <a @class(['menu-item', 'active' => request()->routeIs('tech.ai-monitor')]) href="{{ route('tech.ai-monitor') }}"
                                wire:navigate title="AI Monitor">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="grow">AI Monitor</span>
                            </a>
                            @endcan
                            @can('tech-sso.view')
                            <a @class(['menu-item', 'active' => request()->routeIs('tech.sso')]) href="{{ route('tech.sso') }}"
                                wire:navigate title="SSO / Connected Apps">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                <span class="grow">SSO / Connected Apps</span>
                            </a>
                            @endcan
                            {{-- The page itself stays public (external devs read it without an account); this only controls the menu link. --}}
                            @can('tech-documentation.view')
                            <a @class(['menu-item', 'active' => request()->routeIs('tech.documentation')]) href="{{ route('tech.documentation') }}"
                                wire:navigate title="API Documentation">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                                <span class="grow">API Documentation</span>
                            </a>
                            @endcan
                        </div>
                    @endhasanyrole

                    {{-- Nakama Hub --}}
                    @if (auth()->check() && (auth()->user()->can('manage-chat') || auth()->user()->can('manage-ai-center')))
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Nakama Hub</p>
                        <div class="space-y-0.5">
                            @can('manage-chat')
                                <a @class(['menu-item', 'active' => request()->routeIs('chat')]) href="{{ route('chat') }}" wire:navigate
                                    title="Nakama Chat">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                    </svg>
                                    <span class="grow">Nakama Chat</span>
                                    <livewire:chat.unread-badge />
                                </a>
                            @endcan
                            @can('manage-ai-center')
                                <a @class(['menu-item', 'active' => request()->routeIs('ai-center')]) href="{{ route('ai-center') }}" wire:navigate
                                    title="AI Center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <span class="grow">AI Center</span>
                                </a>
                            @endcan
                        </div>
                    @endif

                    {{-- Data Master --}}
                    @if (auth()->check() &&
                            (auth()->user()->can('access-internal-org') ||
                                auth()->user()->can('access-partnership') ||
                                auth()->user()->can('access-products')))
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
                                                wire:navigate><span class="grow">Departments</span></a>
                                            @endcan
                                            @can('positions.view')
                                            <a @class(['menu-item', 'active' => request()->routeIs('positions.*')]) href="{{ route('positions.index') }}"
                                                wire:navigate><span class="grow">Positions</span></a>
                                            @endcan
                                            @can('employees.view')
                                            <a @class(['menu-item', 'active' => request()->routeIs('employees.*')]) href="{{ route('employees.index') }}"
                                                wire:navigate><span class="grow">Employees</span></a>
                                            @endcan
                                            <a @class(['menu-item', 'active' => request()->routeIs('admin.roles')]) href="{{ route('admin.roles') }}"
                                                wire:navigate><span class="grow">Manage Roles</span></a>
                                        </div>
                                    </div>
                                </div>
                            @endcan

                            @can('access-partnership')
                                <div class="group collapse">
                                    <input aria-label="Pipeline menu toggle" class="peer" type="checkbox"
                                        {{ request()->routeIs('region.*', 'opd.*', 'contact-positions.*', 'urusan.*') ? 'checked' : '' }}>
                                    <div class="collapse-title px-2.5 py-2" title="Pipeline">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                        </svg>
                                        <span class="grow">Pipeline</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon size-3.5"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                    <div class="collapse-content ms-6.5 !p-0">
                                        <div class="mt-0.5 space-y-0.5">
                                            @can('region.view')
                                            <a @class(['menu-item', 'active' => request()->routeIs('region.*')]) href="{{ route('region.index') }}"
                                                wire:navigate><span class="grow">Region</span></a>
                                            @endcan
                                            @can('opd.view')
                                            <a @class(['menu-item', 'active' => request()->routeIs('opd.*')]) href="{{ route('opd.index') }}"
                                                wire:navigate><span class="grow">OPD</span></a>
                                            @endcan
                                            @can('contact-positions.view')
                                            <a @class([
                                                'menu-item',
                                                'active' => request()->routeIs('contact-positions.*'),
                                            ]) href="{{ route('contact-positions.index') }}"
                                                wire:navigate><span class="grow">Contact Positions</span></a>
                                            @endcan
                                            @can('urusan.view')
                                            <a @class(['menu-item', 'active' => request()->routeIs('urusan.*')]) href="{{ route('urusan.index') }}"
                                                wire:navigate><span class="grow">Urusan</span></a>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            @endcan

                            @can('access-products')
                                @can('products.view')
                                <a @class(['menu-item', 'active' => request()->routeIs('products.*')]) href="{{ route('products.index') }}" wire:navigate
                                    title="Products">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                    <span class="grow">Products</span>
                                </a>
                                @endcan
                            @endcan
                        </div>
                    @endif
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

    <x-toast />
    <x-csv-download-manager />
</body>

</html>
