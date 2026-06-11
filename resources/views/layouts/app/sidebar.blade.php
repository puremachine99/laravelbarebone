<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')" class="grid">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            @can('view-executive-dashboard')
            <flux:sidebar.group :heading="__('Executive')" class="grid mt-2">
                <flux:sidebar.item icon="presentation-chart-bar" :href="route('executive-dashboard')" :current="request()->routeIs('executive-dashboard')" wire:navigate>
                    Executive Dashboard
                </flux:sidebar.item>
            </flux:sidebar.group>
            @endcan

            @can('manage-talent-okr')
            <flux:sidebar.group :heading="__('Talent & OKRs')" class="grid mt-2">
                <flux:sidebar.item icon="users" :href="route('leaderboard')" :current="request()->routeIs('leaderboard')" wire:navigate>
                    Talent Leaderboard
                </flux:sidebar.item>
            </flux:sidebar.group>
            @endcan

            @can('manage-pipeline')
            <flux:sidebar.group :heading="__('Workflow')" class="grid mt-2">
                <flux:sidebar.item icon="adjustments-horizontal" :href="route('pipeline-config')" :current="request()->routeIs('pipeline-config')" wire:navigate>
                    Pipeline Config
                </flux:sidebar.item>
            </flux:sidebar.group>
            @endcan

            @if(auth()->user()->can('manage-finance-intake') || auth()->user()->can('manage-finance-reconciliation') || auth()->user()->can('manage-finance-disbursements') || auth()->user()->can('view-finance-clearance'))
            <flux:sidebar.group expandable heading="Finance" icon="banknotes" class="mt-2">
                @can('manage-finance-intake')
                <flux:sidebar.item :href="route('finance.intake')" :current="request()->routeIs('finance.intake')" wire:navigate>
                    Intake Queue
                </flux:sidebar.item>
                @endcan

                @can('manage-finance-reconciliation')
                <flux:sidebar.item :href="route('finance.reconciliation')" :current="request()->routeIs('finance.reconciliation')" wire:navigate>
                    Reconciliation
                </flux:sidebar.item>
                @endcan

                @can('manage-finance-disbursements')
                <flux:sidebar.item :href="route('finance.disbursements')" :current="request()->routeIs('finance.disbursements')" wire:navigate>
                    Disbursements
                </flux:sidebar.item>
                @endcan

                @can('view-finance-clearance')
                <flux:sidebar.item :href="route('finance.clearance-archive')" :current="request()->routeIs('finance.clearance-archive')" wire:navigate>
                    Clearance Archive
                </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>
            @endif

            @if(auth()->user()->can('manage-chat') || auth()->user()->can('manage-ai-center'))
            <flux:sidebar.group expandable heading="Nakama Hub" icon="chat-bubble-left-right" class="mt-2">
                @can('manage-chat')
                <flux:sidebar.item :href="route('chat')" :current="request()->routeIs('chat')" wire:navigate>
                    Nakama Chat
                </flux:sidebar.item>
                @endcan

                @can('manage-ai-center')
                <flux:sidebar.item :href="route('ai-center')" :current="request()->routeIs('ai-center')" wire:navigate>
                    AI Center
                </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>
            @endif

            @if(auth()->user()->can('manage-internal-org') || auth()->user()->can('manage-partnership') || auth()->user()->can('manage-products') || auth()->user()->can('manage-investors'))
            <flux:sidebar.group expandable heading="Data Master" icon="server">

                @can('manage-internal-org')
                <flux:sidebar.group expandable heading="Internal Organization" class="mt-2">
                    <flux:sidebar.item :href="route('departments.index')" :current="request()->routeIs('departments.*')"
                        wire:navigate>
                        Departments
                    </flux:sidebar.item>

                    <flux:sidebar.item :href="route('positions.index')" :current="request()->routeIs('positions.*')"
                        wire:navigate>
                        Positions
                    </flux:sidebar.item>

                    <flux:sidebar.item :href="route('employees.index')" :current="request()->routeIs('employees.*')"
                        wire:navigate>
                        Employees
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endcan

                @can('manage-partnership')
                <flux:sidebar.group expandable heading="Partnership" class="mt-2">
                    <flux:sidebar.item :href="route('region.index')" :current="request()->routeIs('region.*')"
                        wire:navigate>
                        Region
                    </flux:sidebar.item>

                    <flux:sidebar.item :href="route('opd.index')" :current="request()->routeIs('opd.*')"
                        wire:navigate>
                        OPD
                    </flux:sidebar.item>


                    <flux:sidebar.item :href="route('contact-positions.index')"
                        :current="request()->routeIs('contact-positions.*')" wire:navigate>
                        Contact Positions
                    </flux:sidebar.item>

                    <flux:sidebar.item :href="route('urusan.index')" :current="request()->routeIs('urusan.*')"
                        wire:navigate>
                        Urusan
                    </flux:sidebar.item>
                    <flux:sidebar.item :href="route('contact.index')" :current="request()->routeIs('contact.*')"
                        wire:navigate>
                        Contact
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endcan

                @can('manage-products')
                <flux:sidebar.item :href="route('products.index')" :current="request()->routeIs('products.*')"
                    wire:navigate class="mt-2">
                    Products
                </flux:sidebar.item>
                @endcan

                @can('manage-investors')
                <flux:sidebar.item :href="route('investors.index')" :current="request()->routeIs('investors.*')"
                    wire:navigate>
                    Investors
                </flux:sidebar.item>
                @endcan

            </flux:sidebar.group>
            @endif
        </flux:sidebar.nav>

        <flux:spacer />


        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />
                <flux:menu.radio.group>
                    <div class="flex items-center justify-between px-2 py-1.5">
                        <flux:text class="text-sm">Dark Mode</flux:text>
                        <flux:switch x-data x-model="$flux.dark" />
                    </div>
                </flux:menu.radio.group>
                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
