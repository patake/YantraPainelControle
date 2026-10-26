<?php

use App\Models\Client;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Início')] class extends Component {
    /**
     * Clientes vinculados ao operador, com os equipamentos (R-ACE-2).
     */
    #[Computed]
    public function clients()
    {
        return Client::visibleTo(auth()->user())
            ->where('active', true)
            ->with(['devices' => fn ($query) => $query->usingLicense()->with('system')->orderBy('hostname')])
            ->orderBy('trade_name')
            ->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Início') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Equipamentos dos seus clientes. A execução dos exames entra na etapa E6.') }}</flux:text>
    </div>

    @foreach ($this->clients as $client)
        <flux:card class="flex flex-col gap-4" wire:key="client-{{ $client->id }}">
            <flux:heading size="lg">{{ $client->trade_name }}</flux:heading>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($client->devices as $device)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="device-{{ $device->id }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ $device->hostname }}</span>
                            <flux:badge size="sm">{{ $device->system->code }}</flux:badge>
                        </div>
                        @php($online = $device->last_seen_at?->gte(now()->subMinutes(5)))
                        <div class="mt-2 flex items-center gap-2 text-sm {{ $online ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400' }}">
                            <span class="size-2 rounded-full {{ $online ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                            {{ $online ? __('Online') : __('Offline') }}
                            @unless ($online)
                                · {{ $device->last_seen_at?->diffForHumans() ?? __('sem sinal') }}
                            @endunless
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('Nenhum equipamento com o agente instalado.') }}</flux:text>
                @endforelse
            </div>
        </flux:card>
    @endforeach
</div>
