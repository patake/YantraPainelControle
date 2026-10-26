<?php

use App\Models\Device;
use App\Support\DeviceConfig;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $deviceId;

    public string $captureBasePath = '';
    public string $networkUsername = '';
    public string $networkPassword = '';
    public string $hotkey = '';
    public string $serialPort = '';

    /** @var list<int> */
    public array $examTypeIds = [];

    public function mount(Device $device): void
    {
        $this->authorize('viewAny', Device::class);

        $this->deviceId = $device->id;
        // refresh() carrega o valor padrão da pasta, que o banco preenche na criação.
        $settings = $device->settings()->firstOrCreate()->refresh();

        $this->captureBasePath = $settings->capture_base_path;
        $this->networkUsername = (string) $settings->network_username;
        $this->hotkey = (string) $settings->hotkey;
        $this->serialPort = (string) $settings->serial_port;
        $this->examTypeIds = $device->examTypes()->pluck('exam_types.id')->all();
    }

    #[Computed]
    public function device(): Device
    {
        return Device::with(['client', 'system', 'settings', 'decidedBy:id,name'])->findOrFail($this->deviceId);
    }

    #[Computed]
    public function availableExams()
    {
        return $this->device->client->examTypes()->orderBy('name')->get(['id', 'name', 'active']);
    }

    /**
     * Parametrização do equipamento. Só o administrador altera (R-EXA-3).
     */
    public function save(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate([
            'captureBasePath' => ['required', 'string', 'max:255'],
            'networkUsername' => ['nullable', 'string', 'max:255'],
            'networkPassword' => ['nullable', 'string', 'max:255'],
            'hotkey' => ['nullable', 'string', 'max:40'],
            'serialPort' => ['nullable', 'string', 'max:20', 'regex:/^COM\d{1,3}$/i'],
            'examTypeIds' => ['array'],
            'examTypeIds.*' => ['integer', 'in:'.$this->availableExams->pluck('id')->join(',')],
        ], [
            'serialPort.regex' => __('Use o formato COM3.'),
        ], [
            'captureBasePath' => __('pasta base das capturas'),
            'serialPort' => __('porta serial'),
        ]);

        $settings = $this->device->settings;
        $settings->fill([
            'capture_base_path' => $this->captureBasePath,
            'network_username' => $this->networkUsername !== '' ? $this->networkUsername : null,
            'hotkey' => $this->hotkey !== '' ? $this->hotkey : null,
            'serial_port' => $this->serialPort !== '' ? strtoupper($this->serialPort) : null,
        ]);

        // A senha só muda quando o administrador digita uma senha nova.
        if ($this->networkPassword !== '') {
            $settings->network_password = $this->networkPassword;
        }

        if ($this->networkUsername === '') {
            $settings->network_password = null;
        }

        $settings->save();

        $before = $this->device->examTypes()->pluck('exam_types.id')->sort()->values()->all();
        $this->device->examTypes()->sync($this->examTypeIds);
        $after = collect($this->examTypeIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($before !== $after) {
            $this->device->writeAudit('updated', ['exam_type_ids' => $before], ['exam_type_ids' => $after]);
            DeviceConfig::bumpDevice($this->deviceId);
        }

        $this->reset('networkPassword');
        unset($this->device);

        Flux::toast(variant: 'success', text: __('Parametrização salva. O agente baixa a versão nova no próximo ciclo.'));
    }

    public function render()
    {
        return $this->view()->title($this->device->hostname);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('staff.devices.index')" wire:navigate>{{ __('Equipamentos') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $this->device->hostname }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <flux:heading size="xl" level="1">{{ $this->device->hostname }}</flux:heading>
            <flux:badge size="sm">{{ $this->device->system->code }}</flux:badge>
            <flux:badge size="sm">{{ $this->device->status->label() }}</flux:badge>
        </div>
        <flux:text class="mt-1">{{ $this->device->client->trade_name }} · {{ __('parâmetros na versão :version', ['version' => $this->device->config_version]) }}</flux:text>
    </div>

    <flux:card>
        <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-zinc-500">{{ __('Domínio') }}</dt><dd class="mt-1">{{ $this->device->domain ?? __('sem domínio') }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Windows') }}</dt><dd class="mt-1">{{ $this->device->windows_version ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Versão do agente') }}</dt><dd class="mt-1 font-mono">{{ $this->device->agent_version ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Último sinal') }}</dt><dd class="mt-1">{{ $this->device->last_seen_at?->diffForHumans() ?? __('Nunca') }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Registro') }}</dt><dd class="mt-1 tabular-nums">{{ $this->device->registered_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Decisão') }}</dt><dd class="mt-1">{{ $this->device->decidedBy?->name ?? '—' }} {{ $this->device->decided_at?->format('d/m/Y') }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-zinc-500">{{ __('Assinatura') }}</dt><dd class="mt-1 break-all font-mono text-xs">{{ $this->device->signature }}</dd></div>
        </dl>
    </flux:card>

    <form wire:submit="save" class="flex flex-col gap-6">
        <fieldset @disabled(! auth()->user()->isAdmin()) class="flex flex-col gap-6">
            <flux:card class="grid gap-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:input wire:model="captureBasePath" :label="__('Pasta base das capturas')" class="font-mono" />
                </div>
                <flux:input wire:model="networkUsername" :label="__('Usuário de rede (fora de domínio)')" :description="__('Em domínio, o agente copia com a conta do computador.')" />
                <flux:input wire:model="networkPassword" type="password" :label="__('Senha de rede')" :placeholder="$this->device->settings?->network_password ? __('Gravada. Digite para trocar.') : ''" autocomplete="new-password" />
                <flux:input wire:model="hotkey" :label="__('Teclas da captura de tela')" placeholder="Ctrl+Y" />
                <flux:input wire:model="serialPort" :label="__('Porta serial')" placeholder="COM3" />
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Exames habilitados') }}</flux:heading>
                    <flux:text>{{ __('O operador inicia neste equipamento só os exames marcados.') }}</flux:text>
                </div>
                <flux:checkbox.group wire:model="examTypeIds" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($this->availableExams as $exam)
                        <flux:checkbox :value="$exam->id" :label="$exam->name.($exam->active ? '' : ' ('.__('inativo').')')" />
                    @empty
                        <flux:text>{{ __('O cliente não tem exames cadastrados.') }}</flux:text>
                    @endforelse
                </flux:checkbox.group>
            </flux:card>
        </fieldset>

        @if (auth()->user()->isAdmin())
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Salvar parametrização') }}</flux:button>
            </div>
        @endif
    </form>
</div>
