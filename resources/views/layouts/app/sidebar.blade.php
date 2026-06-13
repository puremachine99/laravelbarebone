<!DOCTYPE html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="bg-base-100 min-h-screen antialiased">
    <div class="drawer lg:drawer-open">
        <input id="main-drawer" type="checkbox" class="drawer-toggle" />

        {{-- ─── Main Content Area ───────────────────────────────── --}}
        <div class="drawer-content flex flex-col">

            {{-- Mobile Top Bar --}}
            <div class="border-base-300/80 bg-base-100 sticky -top-10 z-20 flex h-14 items-center gap-3 border-b border-dashed px-4 lg:hidden">
                <label for="main-drawer" class="btn btn-sm btn-square btn-ghost drawer-button">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-5 stroke-current">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </label>
                <div class="flex-1">
                    <a href="{{ route('dashboard') }}" wire:navigate class="text-lg font-semibold">
                        Smart ID Dashboard
                    </a>
                </div>
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle btn-sm avatar placeholder">
                        <div class="bg-base-300 text-base-content rounded-full size-8 flex items-center justify-center text-xs font-bold">
                            {{ auth()->user()->initials() }}
                        </div>
                    </div>
                    <ul tabindex="0" class="dropdown-content menu menu-sm bg-base-100 rounded-box border border-base-300 shadow-lg w-48 mt-2 z-50 p-2">
                        <li>
                            <a href="{{ route('profile.edit') }}" wire:navigate>
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                Settings
                            </a>
                        </li>
                        <li>
                            <button type="button" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="text-error w-full text-left flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                Log out
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Page Content --}}
            <main class="flex-1 p-4 lg:p-6">
                {{ $slot }}
            </main>
        </div>

        {{-- ─── Sidebar ─────────────────────────────────────────── --}}
        <div class="drawer-side z-30">
            <label for="main-drawer" aria-label="close sidebar" class="drawer-overlay"></label>

            <aside class="border-base-300/80 bg-base-100 sticky top-0 bottom-0 flex h-[100dvh] w-64 min-w-64 flex-col border-e border-dashed">

                {{-- Header / Brand --}}
                <div class="border-base-300 flex h-14 min-h-14 shrink-0 items-center gap-3 border-b border-dashed px-5">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-accent-content text-accent-foreground">
                            <x-app-logo-icon class="size-5 fill-current text-white" />
                        </div>
                        <span class="text-base-content text-base font-semibold">Smart ID</span>
                    </a>
                    <hr class="border-base-300 h-5 border-e">
                    <p class="text-base-content/50 text-sm font-medium">Dashboard</p>
                </div>

                {{-- Scrollable Navigation --}}
                <div class="sidebar-menu flex-1 min-h-0 overflow-y-auto px-2.5 pt-4 pb-4">

                    {{-- Dashboard --}}
                    <a @class([
                        'menu-item',
                        'active' => request()->routeIs('dashboard'),
                    ]) href="{{ route('dashboard') }}" wire:navigate>
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                        <span class="grow">Dashboard</span>
                    </a>

                    {{-- Executive --}}
                    @can('view-executive-dashboard')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Executive</p>
                        <a @class([
                            'menu-item',
                            'active' => request()->routeIs('executive-dashboard'),
                        ]) href="{{ route('executive-dashboard') }}" wire:navigate>
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" /></svg>
                            <span class="grow">Executive Dashboard</span>
                        </a>
                    @endcan

                    {{-- Talent & OKRs --}}
                    @can('manage-talent-okr')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Talent & OKRs</p>
                        <a @class([
                            'menu-item',
                            'active' => request()->routeIs('leaderboard'),
                        ]) href="{{ route('leaderboard') }}" wire:navigate>
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <span class="grow">Talent Leaderboard</span>
                        </a>
                    @endcan

                    {{-- Sales Pipeline --}}
                    @can('manage-pipeline')
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Sales Pipeline</p>
                        <div class="space-y-0.5">
                            <div class="group collapse">
                                <input aria-label="Partnership menu toggle" class="peer" type="checkbox"
                                    {{ request()->routeIs('contact.*','partnership.*','partnership-employees.*') ? 'checked' : '' }}>
                                <div class="collapse-title px-2.5 py-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                                    <span class="grow">Partnership</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </div>
                                <div class="collapse-content ms-6.5 !p-0">
                                    <div class="mt-0.5 space-y-0.5">
                                        <a @class(['menu-item', 'active' => request()->routeIs('contact.*')]) href="{{ route('contact.index') }}" wire:navigate>
                                            <span class="grow">Contact</span>
                                        </a>
                                        <a @class(['menu-item', 'active' => request()->routeIs('partnership.pitching')]) href="{{ route('partnership.pitching') }}" wire:navigate>
                                            <span class="grow">Pitching</span>
                                        </a>
                                        <a @class(['menu-item', 'active' => request()->routeIs('partnership.realisasi')]) href="{{ route('partnership.realisasi') }}" wire:navigate>
                                            <span class="grow">Realisasi</span>
                                        </a>
                                        @can('manage-partnership')
                                        <a @class(['menu-item', 'active' => request()->routeIs('partnership-employees.*')]) href="{{ route('partnership-employees.index') }}" wire:navigate>
                                            <span class="grow">PMO</span>
                                        </a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endcan

                    {{-- Finance --}}
                    @if(auth()->user()->can('manage-finance-intake') || auth()->user()->can('manage-finance-reconciliation') || auth()->user()->can('manage-finance-disbursements') || auth()->user()->can('view-finance-clearance'))
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Finance</p>
                        <div class="space-y-0.5">
                            <div class="group collapse">
                                <input aria-label="Finance menu toggle" class="peer" type="checkbox"
                                    {{ request()->routeIs('finance.*') ? 'checked' : '' }}>
                                <div class="collapse-title px-2.5 py-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                    <span class="grow">Finance</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </div>
                                <div class="collapse-content ms-6.5 !p-0">
                                    <div class="mt-0.5 space-y-0.5">
                                        @can('manage-finance-intake')
                                        <a @class(['menu-item', 'active' => request()->routeIs('finance.intake')]) href="{{ route('finance.intake') }}" wire:navigate>
                                            <span class="grow">Intake Queue</span>
                                        </a>
                                        @endcan
                                        @can('manage-finance-reconciliation')
                                        <a @class(['menu-item', 'active' => request()->routeIs('finance.reconciliation')]) href="{{ route('finance.reconciliation') }}" wire:navigate>
                                            <span class="grow">Reconciliation</span>
                                        </a>
                                        @endcan
                                        @can('manage-finance-disbursements')
                                        <a @class(['menu-item', 'active' => request()->routeIs('finance.disbursements')]) href="{{ route('finance.disbursements') }}" wire:navigate>
                                            <span class="grow">Disbursements</span>
                                        </a>
                                        @endcan
                                        @can('view-finance-clearance')
                                        <a @class(['menu-item', 'active' => request()->routeIs('finance.clearance-archive')]) href="{{ route('finance.clearance-archive') }}" wire:navigate>
                                            <span class="grow">Clearance Archive</span>
                                        </a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Nakama Hub --}}
                    @if(auth()->user()->can('manage-chat') || auth()->user()->can('manage-ai-center'))
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Nakama Hub</p>
                        <div class="space-y-0.5">
                            @can('manage-chat')
                            <a @class(['menu-item', 'active' => request()->routeIs('chat')]) href="{{ route('chat') }}" wire:navigate>
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                                <span class="grow">Nakama Chat</span>
                            </a>
                            @endcan
                            @can('manage-ai-center')
                            <a @class(['menu-item', 'active' => request()->routeIs('ai-center')]) href="{{ route('ai-center') }}" wire:navigate>
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                <span class="grow">AI Center</span>
                            </a>
                            @endcan
                        </div>
                    @endif

                    {{-- Data Master --}}
                    @if(auth()->user()->can('manage-internal-org') || auth()->user()->can('manage-partnership') || auth()->user()->can('manage-products') || auth()->user()->can('manage-investors'))
                        <p class="menu-label px-2.5 pt-4 pb-1.5">Data Master</p>
                        <div class="space-y-0.5">

                            @can('manage-internal-org')
                            <div class="group collapse">
                                <input aria-label="Internal menu toggle" class="peer" type="checkbox"
                                    {{ request()->routeIs('departments.*','positions.*','employees.*','admin.*') ? 'checked' : '' }}>
                                <div class="collapse-title px-2.5 py-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    <span class="grow">Internal</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </div>
                                <div class="collapse-content ms-6.5 !p-0">
                                    <div class="mt-0.5 space-y-0.5">
                                        <a @class(['menu-item', 'active' => request()->routeIs('departments.*')]) href="{{ route('departments.index') }}" wire:navigate><span class="grow">Departments</span></a>
                                        <a @class(['menu-item', 'active' => request()->routeIs('positions.*')]) href="{{ route('positions.index') }}" wire:navigate><span class="grow">Positions</span></a>
                                        <a @class(['menu-item', 'active' => request()->routeIs('employees.*')]) href="{{ route('employees.index') }}" wire:navigate><span class="grow">Employees</span></a>
                                        <a @class(['menu-item', 'active' => request()->routeIs('admin.users')]) href="{{ route('admin.users') }}" wire:navigate><span class="grow">Manage Users</span></a>
                                        <a @class(['menu-item', 'active' => request()->routeIs('admin.roles')]) href="{{ route('admin.roles') }}" wire:navigate><span class="grow">Manage Roles</span></a>
                                    </div>
                                </div>
                            </div>
                            @endcan

                            @can('manage-partnership')
                            <div class="group collapse">
                                <input aria-label="Pipeline menu toggle" class="peer" type="checkbox"
                                    {{ request()->routeIs('region.*','opd.*','contact-positions.*','urusan.*') ? 'checked' : '' }}>
                                <div class="collapse-title px-2.5 py-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                                    <span class="grow">Pipeline</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </div>
                                <div class="collapse-content ms-6.5 !p-0">
                                    <div class="mt-0.5 space-y-0.5">
                                        <a @class(['menu-item', 'active' => request()->routeIs('region.*')]) href="{{ route('region.index') }}" wire:navigate><span class="grow">Region</span></a>
                                        <a @class(['menu-item', 'active' => request()->routeIs('opd.*')]) href="{{ route('opd.index') }}" wire:navigate><span class="grow">OPD</span></a>
                                        <a @class(['menu-item', 'active' => request()->routeIs('contact-positions.*')]) href="{{ route('contact-positions.index') }}" wire:navigate><span class="grow">Contact Positions</span></a>
                                        <a @class(['menu-item', 'active' => request()->routeIs('urusan.*')]) href="{{ route('urusan.index') }}" wire:navigate><span class="grow">Urusan</span></a>
                                    </div>
                                </div>
                            </div>
                            @endcan

                            @can('manage-products')
                            <a @class(['menu-item', 'active' => request()->routeIs('products.*')]) href="{{ route('products.index') }}" wire:navigate>
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                <span class="grow">Products</span>
                            </a>
                            @endcan

                            @can('manage-investors')
                            <a @class(['menu-item', 'active' => request()->routeIs('investors.*')]) href="{{ route('investors.index') }}" wire:navigate>
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span class="grow">Investors</span>
                            </a>
                            @endcan
                        </div>
                    @endif
                </div>

                {{-- User Profile Footer --}}
                <div class="border-base-300 shrink-0 border-t border-dashed">
                    <div class="dropdown dropdown-top w-full">
                        <div tabindex="0" role="button"
                            class="bg-base-200/60 hover:bg-base-200 flex w-full cursor-pointer items-center gap-3 px-4 py-3 transition-all">
                            <div class="avatar placeholder">
                                <div class="bg-neutral text-neutral-content rounded-full size-9 flex items-center justify-center">
                                    <span class="text-sm font-bold">{{ auth()->user()->initials() }}</span>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                                <p class="text-base-content/60 text-xs truncate">{{ auth()->user()->email }}</p>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-base-content/40 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                        </div>
                        <div tabindex="0"
                            class="dropdown-content bg-base-100 rounded-box border border-base-300 mb-2 w-56 shadow-lg z-50">
                            <ul class="menu w-full p-2">
                                <li>
                                    <a href="{{ route('profile.edit') }}" wire:navigate>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                        <span>My Profile</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('profile.edit') }}" wire:navigate>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <span>Settings</span>
                                    </a>
                                </li>
                            </ul>
                            <hr class="border-base-300">
                            <ul class="menu w-full p-2">
                                <li>
                                    <button type="button" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="text-error hover:bg-error/10 flex w-full items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                        <span>Logout</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

            </aside>
        </div>
    </div>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>

    <x-toast />
</body>

</html>