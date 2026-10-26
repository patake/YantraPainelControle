<?php

use App\Enums\DeviceStatus;
use App\Models\License;
use App\Models\System;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sistemas e licenças')] class extends Component {
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $color = '#b8323a';

    public function mount(): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
    }

    #[Computed]
    public function systems()
    {
        return System::query()
            ->withSum('licenses as seats', 'quantity')
            ->withCount(['devices as used' => fn ($query) => $query->whereIn('status', DeviceStatus::usingLicense())])
            ->orderBy('code')
            ->get();
    }

    #[Computed]
    public function licenses()
    {
        return License::query()
            ->with(['client:id,trade_name', 'system:id,code'])
            ->join('clients', 'clients.id', '=', 'licenses.client_id')
            ->orderBy('clients.trade_name')
            ->select('licenses.*')
            ->get();
    }

    public function edit(int $systemId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $system = System::findOrFail($systemId);
        $this->editingId = $system->id;
        $this->name = $system->name;
        $this->color = $system->branding['color'] ?? '#b8323a';
    }

    /**
     * O nome e a cor mudam a identidade do agente depois da ativação.
     */
    public function save(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [], ['name' => __('nome'), 'color' => __('cor')]);

        $system = System::findOrFail($this->editingId);
        $system->update([
            'name' => $validated['name'],
            'branding' => array_merge($system->branding ?? [], ['color' => strtolower($validated['color'])]),
        ]);

        $this->reset('editingId', 'name', 'color');
        unset($this->systems);

        Flux::toast(variant: 'success', text: __('Sistema salvo.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Sistemas e licenças') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Cada sistema libera uma função do agente. O nome e a cor mudam a identidade do agente depois da ativação.') }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->systems as $system)
            <flux:card class="flex flex-col gap-3" wire:key="system-{{ $system->id }}">
                <div class="flex items-center justify-between">
                    <flux:badge size="sm">{{ $system->code }}</flux:badge>
                    <span class="size-5 rounded" style="background: {{ $system->branding['color'] ?? '#71717a' }}" aria-hidden="true"></span>
                </div>
                <flux:heading>{{ $system->name }}</flux:heading>
                <flux:text>{{ match ($system->feature->value) { 'printer' => __('Impressora virtual'), 'screen' => __('Captura de tela'), 'serial' => __('Porta serial') } }}</flux:text>
                <flux:text class="tabular-nums">{{ __(':used de :seats licenças em uso', ['used' => $system->used, 'seats' => (int) $system->seats]) }}</flux:text>
                @if (auth()->user()->isAdmin())
                    <flux:button size="sm" wire:click="edit({{ $system->id }})">{{ __('Editar identidade') }}</flux:button>
                @endif
            </flux:card>
        @endforeach
    </div>

    @if ($editingId)
        <flux:card>
            <form wire:submit="save" class="grid items-end gap-4 sm:grid-cols-3">
                <flux:input wire:model="name" :label="__('Nome')" />
                <flux:input wire:model="color" type="color" :label="__('Cor')" />
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">{{ __('Salvar') }}</flux:button>
                    <flux:button wire:click="$set('editingId', null)">{{ __('Cancelar') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Pacotes de licenças por cliente') }}</flux:heading>
        <flux:text>{{ __('Crie e altere os pacotes na tela do cliente, na aba Licenças.') }}</flux:text>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Cliente') }}</flux:table.column>
                <flux:table.column>{{ __('Sistema') }}</flux:table.column>
                <flux:table.column>{{ __('Contratadas') }}</flux:table.column>
                <flux:table.column>{{ __('Validade') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->licenses as $license)
                    <flux:table.row :key="$license->id">
                        <flux:table.cell>
                            <flux:link :href="route('staff.clients.show', ['client' => $license->client_id, 'aba' => 'licencas'])" wire:navigate>{{ $license->client->trade_name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell><flux:badge size="sm">{{ $license->system->code }}</flux:badge></flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $license->quantity }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $license->valid_until?->format('d/m/Y') ?? __('Sem prazo') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">{{ __('Nenhuma licença cadastrada.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
