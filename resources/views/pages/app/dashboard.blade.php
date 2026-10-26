<?php

use App\Models\Client;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Equipamentos')] class extends Component {
    /**
     * Clientes vinculados ao operador, com os equipamentos e o exame aberto de cada um (R-ACE-2).
     */
    #[Computed]
    public function clients()
    {
        return Client::visibleTo(auth()->user())
            ->where('active', true)
            ->with(['devices' => fn ($query) => $query->usingLicense()
                ->with(['system', 'exams' => fn ($query) => $query->open()->with('examType:id,name')])
                ->orderBy('hostname')])
            ->orderBy('trade_name')
            ->get();
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.30s>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Equipamentos') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Equipamentos dos seus clientes, com o exame em andamento.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('app.exams.create')" wire:navigate>{{ __('Novo exame') }}</flux:button>
    </div>

    @foreach ($this->clients as $client)
        <flux:card class="flex flex-col gap-4" wire:key="client-{{ $client->id }}">
            <flux:heading size="lg">{{ $client->trade_name }}</flux:heading>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($client->devices as $device)
                    @php($online = $device->last_seen_at?->gte(now()->subMinutes(5)))
                    @php($exam = $device->exams->first())
                    <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="device-{{ $device->id }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ $device->hostname }}</span>
                            <flux:badge size="sm">{{ $device->system->code }}</flux:badge>
                        </div>
                        <div class="flex items-center gap-2 text-sm {{ $online ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400' }}">
                            <span class="size-2 rounded-full {{ $online ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                            {{ $online ? __('Online') : __('Offline') }}
                            @unless ($online)
                                · {{ $device->last_seen_at?->diffForHumans() ?? __('sem sinal') }}
                            @endunless
                        </div>
                        @if ($exam)
                            <flux:link :href="route('app.exams.show', $exam)" wire:navigate class="text-sm">{{ __('Em exame: :exam', ['exam' => $exam->examType->name]) }}</flux:link>
                        @endif
                    </div>
                @empty
                    <flux:text>{{ __('Nenhum equipamento com o agente instalado.') }}</flux:text>
                @endforelse
            </div>
        </flux:card>
    @endforeach
</div>
