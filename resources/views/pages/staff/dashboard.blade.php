<?php

use App\Enums\DeviceStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Device;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Início')] class extends Component {
    /**
     * Totais da página inicial da Staff Weega.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function totals(): array
    {
        return [
            'clients' => Client::where('active', true)->count(),
            'devices' => Device::usingLicense()->count(),
            'online' => Device::usingLicense()->where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'trials' => Device::where('status', DeviceStatus::Trial)->count(),
        ];
    }

    /**
     * Instalações em trial que aguardam a decisão da Staff Weega.
     */
    #[Computed]
    public function pendingDevices()
    {
        return Device::with(['client', 'system'])
            ->where('status', DeviceStatus::Trial)
            ->orderBy('trial_ends_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function recentAudit()
    {
        return AuditLog::with('user')->latest('created_at')->limit(8)->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Início') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Situação dos clientes e dos equipamentos.') }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Clientes ativos') }}</flux:text>
            <flux:heading size="xl" class="tabular-nums">{{ $this->totals['clients'] }}</flux:heading>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Equipamentos com licença') }}</flux:text>
            <flux:heading size="xl" class="tabular-nums">{{ $this->totals['devices'] }}</flux:heading>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Online nos últimos 5 minutos') }}</flux:text>
            <flux:heading size="xl" class="tabular-nums">{{ $this->totals['online'] }}</flux:heading>
        </flux:card>
        <flux:card class="flex flex-col gap-1 {{ $this->totals['trials'] > 0 ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40' : '' }}">
            <flux:text>{{ __('Aguardando decisão') }}</flux:text>
            <flux:heading size="xl" class="tabular-nums">{{ $this->totals['trials'] }}</flux:heading>
        </flux:card>
    </div>

    <flux:card class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Instalações aguardando decisão') }}</flux:heading>
            <flux:link :href="route('staff.devices.index', ['estado' => 'trial'])" wire:navigate>{{ __('Ativar ou bloquear') }}</flux:link>
        </div>

        @if ($this->pendingDevices->isEmpty())
            <flux:text>{{ __('Nenhuma instalação nova aguarda decisão.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Equipamento') }}</flux:table.column>
                    <flux:table.column>{{ __('Cliente') }}</flux:table.column>
                    <flux:table.column>{{ __('Sistema') }}</flux:table.column>
                    <flux:table.column>{{ __('Trial até') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->pendingDevices as $device)
                        <flux:table.row :key="$device->id">
                            <flux:table.cell class="font-medium">{{ $device->hostname }}</flux:table.cell>
                            <flux:table.cell>{{ $device->client->trade_name }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm">{{ $device->system->code }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="tabular-nums">{{ $device->trial_ends_at?->format('d/m/Y') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <flux:card class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Últimas alterações') }}</flux:heading>
            <flux:link :href="route('staff.logs.audit')" wire:navigate>{{ __('Ver a auditoria') }}</flux:link>
        </div>

        @forelse ($this->recentAudit as $log)
            <div class="flex flex-wrap justify-between gap-2 text-sm" wire:key="audit-{{ $log->id }}">
                <span>
                    <span class="font-medium">{{ $log->user?->name ?? __('Sistema') }}</span>
                    · {{ __($log->action) }} · {{ $log->auditable_type }} #{{ $log->auditable_id }}
                </span>
                <span class="text-zinc-500 tabular-nums">{{ $log->created_at->format('d/m H:i') }}</span>
            </div>
        @empty
            <flux:text>{{ __('Nenhuma alteração registrada.') }}</flux:text>
        @endforelse
    </flux:card>
</div>
