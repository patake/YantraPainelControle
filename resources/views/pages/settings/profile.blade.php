<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Perfil')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
    }

    /**
     * O usuário muda só o nome. Só o administrador muda o e-mail.
     */
    public function updateProfileInformation(): void
    {
        $validated = $this->validate(['name' => $this->nameRules()]);

        Auth::user()->update($validated);

        Flux::toast(variant: 'success', text: __('Perfil salvo.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Perfil') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Perfil')" :subheading="__('Seu nome e seu e-mail de acesso')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Nome')" type="text" required autofocus autocomplete="name" />

            <flux:input :value="auth()->user()->email" :label="__('E-mail')" type="email" disabled :description="__('Só o administrador muda o e-mail.')" />

            <flux:button variant="primary" type="submit" data-test="update-profile-button">
                {{ __('Salvar') }}
            </flux:button>
        </form>
    </x-pages::settings.layout>
</section>
