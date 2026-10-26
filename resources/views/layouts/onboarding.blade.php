<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-xl flex-col gap-6">
                <div class="flex items-center justify-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-[#b8323a] text-white">
                        <x-app-logo-icon class="size-6" />
                    </span>
                    <span class="text-lg font-semibold">{{ config('app.name', 'Yantra Painel') }}</span>
                </div>

                {{ $slot }}
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
