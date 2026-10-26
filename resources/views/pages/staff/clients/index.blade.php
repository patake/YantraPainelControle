<?php

use App\Models\Client;
use App\Support\Cnpj;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Clientes')] class extends Component {
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'estado')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Client::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function clients()
    {
        $digits = Cnpj::normalize($this->search);

        return Client::query()
            ->withCount(['devices', 'users'])
            ->when($this->search !== '', function ($query) use ($digits) {
                $query->where(function ($query) use ($digits) {
                    $query->whereLike('trade_name', '%'.$this->search.'%', caseSensitive: false)
                        ->orWhereLike('legal_name', '%'.$this->search.'%', caseSensitive: false);

                    if ($digits !== '') {
                        $query->orWhere('cnpj', 'like', $digits.'%');
                    }
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('active', $this->status === 'ativo'))
            ->orderBy('trade_name')
            ->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Clientes') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Cadastre o cliente e as licenças antes da instalação do agente.') }}</flux:text>
        </div>

        @can('create', App\Models\Client::class)
            <flux:button variant="primary" icon="plus" :href="route('staff.clients.create')" wire:navigate>
                {{ __('Novo cliente') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-4">
        <div class="min-w-64 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Buscar')" :placeholder="__('Nome ou CNPJ')" />
        </div>
        <div class="w-48">
            <flux:select wire:model.live="status" :label="__('Estado')">
                <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                <flux:select.option value="ativo">{{ __('Ativo') }}</flux:select.option>
                <flux:select.option value="inativo">{{ __('Desativado') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <flux:table :paginate="$this->clients">
        <flux:table.columns>
            <flux:table.column>{{ __('Cliente') }}</flux:table.column>
            <flux:table.column>{{ __('CNPJ') }}</flux:table.column>
            <flux:table.column>{{ __('Usuários') }}</flux:table.column>
            <flux:table.column>{{ __('Equipamentos') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->clients as $client)
                <flux:table.row :key="$client->id">
                    <flux:table.cell>
                        <flux:link :href="route('staff.clients.show', $client)" wire:navigate class="font-medium">
                            {{ $client->trade_name }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $client->formattedCnpj() }}</flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $client->users_count }}</flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $client->devices_count }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($client->active)
                            <flux:badge color="green" size="sm">{{ __('Ativo') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Desativado') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('Nenhum cliente encontrado.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
