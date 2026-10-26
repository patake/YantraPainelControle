<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @if (auth()->user()->isStaff())
                    <flux:sidebar.group :heading="__('Staff Weega')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('staff.dashboard')" :current="request()->routeIs('staff.dashboard')" wire:navigate>
                            {{ __('Início') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-office-2" :href="route('staff.clients.index')" :current="request()->routeIs('staff.clients.*')" wire:navigate>
                            {{ __('Clientes') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="key" :href="route('staff.systems.index')" :current="request()->routeIs('staff.systems.*')" wire:navigate>
                            {{ __('Sistemas e licenças') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="computer-desktop" :href="route('staff.devices.index')" :current="request()->routeIs('staff.devices.*')" :badge="App\Models\Device::where('status', App\Enums\DeviceStatus::Trial)->count() ?: null" wire:navigate>
                            {{ __('Equipamentos') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('staff.users.index')" :current="request()->routeIs('staff.users.*')" wire:navigate>
                            {{ __('Usuários') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('staff.releases.index')" :current="request()->routeIs('staff.releases.*')" wire:navigate>
                            {{ __('Versões do agente') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('staff.logs.audit')" :current="request()->routeIs('staff.logs.*')" wire:navigate>
                            {{ __('Log e auditoria') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group :heading="__('Exames')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('app.dashboard')" :current="request()->routeIs('app.dashboard')" wire:navigate>
                            {{ __('Início') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Menu do usuário no celular -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <x-user-menu-items />
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
