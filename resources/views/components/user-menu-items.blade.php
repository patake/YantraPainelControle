{{-- Menu do usuário: dados, tema, perfil e saída. Igual no computador e no celular. --}}
<flux:menu>
    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
        <flux:avatar
            :name="auth()->user()->name"
            :initials="auth()->user()->initials()"
        />
        <div class="grid flex-1 text-start text-sm leading-tight">
            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
            <flux:text class="truncate">{{ auth()->user()->role->label() }} · {{ auth()->user()->email }}</flux:text>
        </div>
    </div>

    <flux:menu.separator />

    <flux:menu.radio.group x-data variant="segmented" x-model="$flux.appearance" :label="__('Tema')">
        <flux:menu.radio value="light" icon="sun">{{ __('Claro') }}</flux:menu.radio>
        <flux:menu.radio value="dark" icon="moon">{{ __('Escuro') }}</flux:menu.radio>
        <flux:menu.radio value="system" icon="computer-desktop">{{ __('Sistema') }}</flux:menu.radio>
    </flux:menu.radio.group>

    <flux:menu.separator />

    <flux:menu.item :href="route('profile.edit')" icon="user-circle" wire:navigate>
        {{ __('Perfil e 2FA') }}
    </flux:menu.item>

    <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <flux:menu.item
            as="button"
            type="submit"
            icon="arrow-right-start-on-rectangle"
            class="w-full cursor-pointer"
            data-test="logout-button"
        >
            {{ __('Sair') }}
        </flux:menu.item>
    </form>
</flux:menu>
