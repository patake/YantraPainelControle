<?php

use App\Enums\SystemFeature;
use App\Models\Device;
use App\Rules\ValidHotkey;
use App\Support\Hotkey;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Configurações')] class extends Component {
    /**
     * Combinação de teclas de cada equipamento, pelo id do equipamento.
     *
     * @var array<string, string>
     */
    public array $hotkeys = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->isManager(), 403);

        foreach ($this->devices as $device) {
            $this->hotkeys[$device->id] = $device->settings?->hotkey ?? Hotkey::DEFAULT;
        }
    }

    /**
     * Equipamentos de captura de tela (YO SRC) dos clientes ativos do gestor (R-ACE-2, R-EXA-11).
     */
    #[Computed]
    public function devices()
    {
        return Device::query()
            ->usingLicense()
            ->whereHas('client', fn ($query) => $query->visibleTo(auth()->user())->where('active', true))
            ->whereHas('system', fn ($query) => $query->where('feature', SystemFeature::Screen))
            ->with(['client:id,trade_name', 'settings'])
            ->orderBy('hostname')
            ->get();
    }

    /**
     * Grava a combinação de um equipamento. O agente baixa os parâmetros novos no próximo ciclo.
     */
    public function saveHotkey(string $deviceId): void
    {
        abort_unless(auth()->user()->isManager(), 403);

        // O equipamento precisa estar na lista do gestor: outro cliente ou outro sistema dá 404.
        $device = $this->devices->firstWhere('id', $deviceId);
        abort_if($device === null, 404);

        $this->validate(
            ["hotkeys.{$deviceId}" => ['required', 'string', 'max:40', new ValidHotkey]],
            [],
            ["hotkeys.{$deviceId}" => __('teclas da captura de tela')],
        );

        $settings = $device->settings()->firstOrCreate()->refresh();
        $settings->update(['hotkey' => Hotkey::normalize($this->hotkeys[$deviceId])]);
        $this->hotkeys[$deviceId] = $settings->hotkey;

        Flux::toast(variant: 'success', text: __('Teclas de :device salvas: :hotkey. O agente usa a combinação nova no próximo ciclo.', [
            'device' => $device->hostname,
            'hotkey' => $settings->hotkey,
        ]));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Configurações') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Teclas da captura de tela dos equipamentos Yantra Screen Capture (YO SRC).') }}</flux:text>
    </div>

    <flux:callout icon="information-circle" variant="secondary">
        {{ __('O usuário aperta a combinação de teclas no computador do exame, e o agente grava a imagem da janela ativa no exame aberto. O padrão é Ctrl+Y. Use Ctrl, Alt ou Win e uma tecla: letra, número, F1 a F12 ou PrintScreen.') }}
    </flux:callout>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Equipamento') }}</flux:table.column>
                <flux:table.column>{{ __('Cliente') }}</flux:table.column>
                <flux:table.column>{{ __('Teclas da captura de tela') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->devices as $device)
                    <flux:table.row :key="$device->id">
                        <flux:table.cell class="font-medium">{{ $device->hostname }}</flux:table.cell>
                        <flux:table.cell>{{ $device->client->trade_name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:input wire:model="hotkeys.{{ $device->id }}" placeholder="Ctrl+Y" class="max-w-48 font-mono" :aria-label="__('Teclas da captura de tela de :device', ['device' => $device->hostname])" />
                            <flux:error name="hotkeys.{{ $device->id }}" />
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="primary" wire:click="saveHotkey('{{ $device->id }}')">{{ __('Salvar') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">{{ __('Nenhum equipamento Yantra Screen Capture (YO SRC) nos seus clientes.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
