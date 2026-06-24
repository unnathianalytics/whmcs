<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @if (auth()->user()->isSaasAdmin())
                    <flux:sidebar.group :heading="__('Platform')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('saas.dashboard')" :current="request()->routeIs('saas.dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-office-2" :href="route('saas.companies')" :current="request()->routeIs('saas.companies')" wire:navigate>
                            {{ __('Companies') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="rectangle-stack" :href="route('saas.plans')" :current="request()->routeIs('saas.plans')" wire:navigate>
                            {{ __('Plans') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group :heading="__('Overview')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Management')" class="grid">
                        @can('clients.view')
                            <flux:sidebar.item icon="users" :href="route('admin.clients')" :current="request()->routeIs('admin.clients*')" wire:navigate>
                                {{ __('Clients') }}
                            </flux:sidebar.item>
                        @else
                            <flux:sidebar.item icon="users" :disabled="true">{{ __('Clients') }}</flux:sidebar.item>
                        @endcan
                        @can('services.view')
                            <flux:sidebar.item icon="squares-2x2" :href="route('admin.products')" :current="request()->routeIs('admin.products*')" wire:navigate>
                                {{ __('Products') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="cube" :href="route('admin.services')" :current="request()->routeIs('admin.services*')" wire:navigate>
                                {{ __('Services') }}
                            </flux:sidebar.item>
                        @else
                            <flux:sidebar.item icon="squares-2x2" :disabled="true">{{ __('Products') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="cube" :disabled="true">{{ __('Services') }}</flux:sidebar.item>
                        @endcan
                        @can('invoices.view')
                            <flux:sidebar.item icon="document-text" :href="route('admin.invoices')" :current="request()->routeIs('admin.invoices*')" wire:navigate>
                                {{ __('Invoices') }}
                            </flux:sidebar.item>
                        @else
                            <flux:sidebar.item icon="document-text" :disabled="true">{{ __('Invoices') }}</flux:sidebar.item>
                        @endcan
                        @can('tickets.view')
                            <flux:sidebar.item icon="lifebuoy" :href="route('admin.tickets')" :current="request()->routeIs('admin.tickets*')" wire:navigate>
                                {{ __('Tickets') }}
                            </flux:sidebar.item>
                        @else
                            <flux:sidebar.item icon="lifebuoy" :disabled="true">{{ __('Tickets') }}</flux:sidebar.item>
                        @endcan
                        @can('domains.view')
                            <flux:sidebar.item icon="globe-alt" :href="route('admin.domains')" :current="request()->routeIs('admin.domains*')" wire:navigate>
                                {{ __('Domains') }}
                            </flux:sidebar.item>
                        @else
                            <flux:sidebar.item icon="globe-alt" :disabled="true">{{ __('Domains') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('System')" class="grid">
                        @can('invoices.view')
                            <flux:sidebar.item icon="receipt-percent" :href="route('admin.tax-rates')" :current="request()->routeIs('admin.tax-rates*')" wire:navigate>
                                {{ __('Tax Rates') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('tickets.view')
                            <flux:sidebar.item icon="inbox-stack" :href="route('admin.ticket-departments')" :current="request()->routeIs('admin.ticket-departments*')" wire:navigate>
                                {{ __('Departments') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('reminders.view')
                            <flux:sidebar.item icon="bell-alert" :href="route('admin.reminders')" :current="request()->routeIs('admin.reminders*')" wire:navigate>
                                {{ __('Reminders') }}
                            </flux:sidebar.item>
                        @else
                            <flux:sidebar.item icon="bell-alert" :disabled="true">{{ __('Reminders') }}</flux:sidebar.item>
                        @endcan
                        @can('roles.view')
                            <flux:sidebar.item icon="shield-check" :href="route('admin.roles')" :current="request()->routeIs('admin.roles*')" wire:navigate>
                                {{ __('Roles') }}
                            </flux:sidebar.item>
                        @else
                            <flux:sidebar.item icon="shield-check" :disabled="true">{{ __('Roles') }}</flux:sidebar.item>
                        @endcan
                        @can('settings.view')
                            <flux:sidebar.item icon="clock" :href="route('admin.activity-log')" :current="request()->routeIs('admin.activity-log*')" wire:navigate>
                                {{ __('Activity Log') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings*')" wire:navigate>
                                {{ __('Settings') }}
                            </flux:sidebar.item>
                        @else
                            <flux:sidebar.item icon="clock" :disabled="true">{{ __('Activity Log') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="cog-6-tooth" :disabled="true">{{ __('Settings') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        @if (session()->has(\App\Services\Saas\Impersonation::SESSION_KEY))
            <div class="bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950">
                {{ __('You are impersonating :name.', ['name' => auth()->user()->name]) }}
                <form method="POST" action="{{ route('impersonate.stop') }}" class="inline">
                    @csrf
                    <button type="submit" class="ml-2 underline">{{ __('Stop impersonating') }}</button>
                </form>
            </div>
        @endif

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
