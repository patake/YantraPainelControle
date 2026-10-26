<?php

use App\Enums\DeviceStatus;
use App\Exceptions\LicenseUnavailableException;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Device;
use App\Models\System;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Equipamentos')] class extends Component {
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'cliente')]
    public string $clientId = '';

    #[Url(as: 'sistema')]
    public string $systemId = '';

    #[Url(as: 'estado')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Device::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'clientId', 'systemId', 'status'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function devices()
    {
        return Device::query()
            ->with(['client:id,trade_name', 'system:id,code'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('hostname', '%'.$this->search.'%', caseSensitive: false)
                ->orWhereLike('domain', '%'.$this->search.'%', caseSensitive: false)
                ->orWhere('signature', 'like', strtolower($this->search).'%')))
            ->when($this->clientId !== '', fn ($query) => $query->where('client_id', (int) $this->clientId))
            ->when($this->systemId !== '', fn ($query) => $query->where('system_id', (int) $this->systemId))
            ->when(DeviceStatus::tryFrom($this->status), fn ($query, DeviceStatus $status) => $query->where('status', $status))
            ->orderByRaw("case when status = 'trial' then 0 else 1 end")
            ->orderBy('hostname')
            ->paginate(20);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function statusCounts(): array
    {
        return Device::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();
    }

    #[Computed]
    public function clients()
    {
        return Client::orderBy('trade_name')->get(['id', 'trade_name']);
    }

    #[Computed]
    public function systems()
    {
        return System::orderBy('code')->get(['id', 'code']);
    }

    /**
     * Equipamentos com suspeita de clone: a mesma assinatura tentou entrar em outro cliente (R-LIC-6).
     */
    #[Computed]
    public function cloneSuspects()
    {
        return AuditLog::where('action', 'clone_suspected')->latest('created_at')->limit(5)->get();
    }

    public function activate(string $deviceId): void
    {
        $device = Device::with(['client', 'system'])->findOrFail($deviceId);
        $this->authorize('decide', $device);

        try {
            $device->activate(auth()->user());
        } catch (LicenseUnavailableException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __(':device ativado.', ['device' => $device->hostname]));
    }

    /**
     * Canal de atualização: piloto recebe as versões novas antes do canal estável (E4).
     */
    public function setChannel(string $deviceId, string $channel): void
    {
        $device = Device::findOrFail($deviceId);
        $this->authorize('decide', $device);

        $device->update(['release_channel' => App\Enums\ReleaseChannel::from($channel)]);

        Flux::toast(text: __('Canal de :device alterado.', ['device' => $device->hostname]));
    }

    public function block(string $deviceId): void
    {
        $device = Device::findOrFail($deviceId);
        $this->authorize('decide', $device);

        $device->block(auth()->user());

        Flux::toast(text: __(':device bloqueado.', ['device' => $device->hostname]));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Equipamentos') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Cada instalação nova entra em trial por 7 dias e aguarda a decisão da Staff Weega.') }}</flux:text>
    </div>

    @if ($this->cloneSuspects->isNotEmpty())
        <flux:callout icon="exclamation-triangle" color="red">
            <flux:callout.heading>{{ __('Suspeita de clone') }}</flux:callout.heading>
            <flux:callout.text>
                @foreach ($this->cloneSuspects as $log)
                    <div wire:key="clone-{{ $log->id }}">
                        {{ $log->created_at->format('d/m H:i') }} · {{ $log->new_values['hostname'] ?? '' }}
                        · {{ __('CNPJ tentado') }} {{ $log->new_values['attempted_cnpj'] ?? '' }}
                        · {{ __('equipamento') }} #{{ $log->auditable_id }}
                    </div>
                @endforeach
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Buscar')" :placeholder="__('Nome, domínio ou assinatura')" />
        <flux:select wire:model.live="clientId" :label="__('Cliente')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            @foreach ($this->clients as $client)
                <flux:select.option :value="$client->id">{{ $client->trade_name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="systemId" :label="__('Sistema')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            @foreach ($this->systems as $system)
                <flux:select.option :value="$system->id">{{ $system->code }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" :label="__('Estado')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            @foreach (App\Enums\DeviceStatus::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }} ({{ $this->statusCounts[$option->value] ?? 0 }})</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->devices">
        <flux:table.columns>
            <flux:table.column>{{ __('Equipamento') }}</flux:table.column>
            <flux:table.column>{{ __('Cliente') }}</flux:table.column>
            <flux:table.column>{{ __('Sistema') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
            <flux:table.column>{{ __('Último sinal') }}</flux:table.column>
            <flux:table.column>{{ __('Versão') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->devices as $device)
                <flux:table.row :key="$device->id">
                    <flux:table.cell>
                        <flux:link :href="route('staff.devices.show', $device)" wire:navigate class="font-medium">{{ $device->hostname }}</flux:link>
                        <div class="font-mono text-xs text-zinc-500">{{ $device->domain ?? __('sem domínio') }} · {{ substr($device->signature, 0, 4) }}…{{ substr($device->signature, -4) }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $device->client->trade_name }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm">{{ $device->system->code }}</flux:badge></flux:table.cell>
                    <flux:table.cell>
                        @php($color = match ($device->status) { DeviceStatus::Active => 'green', DeviceStatus::Trial => 'amber', DeviceStatus::Blocked => 'red', DeviceStatus::Expired => 'zinc' })
                        <flux:badge size="sm" :color="$color">
                            {{ $device->status->label() }}{{ $device->status === DeviceStatus::Trial && $device->trial_ends_at ? ' '.__('até').' '.$device->trial_ends_at->format('d/m') : '' }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $device->last_seen_at?->diffForHumans() ?? __('Nunca') }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">
                        {{ $device->agent_version ?? '—' }}
                        @can('decide', $device)
                            <button type="button" class="ms-1 rounded px-1.5 py-0.5 font-sans text-xs {{ $device->release_channel === App\Enums\ReleaseChannel::Pilot ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}"
                                wire:click="setChannel('{{ $device->id }}', '{{ $device->release_channel === App\Enums\ReleaseChannel::Pilot ? 'stable' : 'pilot' }}')"
                                title="{{ __('Trocar o canal de atualização') }}">
                                {{ $device->release_channel === App\Enums\ReleaseChannel::Pilot ? __('piloto') : __('estável') }}
                            </button>
                        @endcan
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @can('decide', $device)
                            <div class="flex justify-end gap-1">
                                @if ($device->status !== DeviceStatus::Blocked)
                                    <flux:button size="sm" variant="ghost" wire:click="block('{{ $device->id }}')" wire:confirm="{{ __('Bloquear este equipamento?') }}">{{ __('Bloquear') }}</flux:button>
                                @endif
                                @if ($device->status !== DeviceStatus::Active)
                                    <flux:button size="sm" variant="primary" wire:click="activate('{{ $device->id }}')">{{ __('Ativar') }}</flux:button>
                                @endif
                            </div>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('Nenhum equipamento encontrado.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
