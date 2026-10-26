<?php

use App\Actions\Exams\ExamCycle;
use App\Exceptions\ExamCycleException;
use App\Models\Client;
use App\Models\Device;
use App\Models\ExamType;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Novo exame')] class extends Component {
    public ?string $deviceId = null;

    public ?int $examTypeId = null;

    /**
     * Equipamentos dos clientes do operador, com o exame aberto de cada um (R-ACE-2).
     */
    #[Computed]
    public function devices()
    {
        return Device::query()
            ->whereIn('client_id', Client::visibleTo(auth()->user())->where('active', true)->select('id'))
            ->usingLicense()
            ->with(['client:id,trade_name', 'system:id,code', 'exams' => fn ($query) => $query->open()->with('examType:id,name')])
            ->orderBy('hostname')
            ->get();
    }

    #[Computed]
    public function examTypes()
    {
        if ($this->deviceId === null) {
            return collect();
        }

        return ExamType::query()
            ->where('active', true)
            ->whereHas('devices', fn ($query) => $query->whereKey($this->deviceId))
            ->orderBy('name')
            ->get(['id', 'name', 'destination_folder']);
    }

    public function updatedDeviceId(): void
    {
        $this->examTypeId = null;
    }

    public function start(ExamCycle $cycle): void
    {
        $this->validate([
            'deviceId' => ['required', 'string'],
            'examTypeId' => ['required', 'integer'],
        ], [], ['deviceId' => __('equipamento'), 'examTypeId' => __('tipo de exame')]);

        $device = $this->devices->firstWhere('id', $this->deviceId);
        $examType = $this->examTypes->firstWhere('id', $this->examTypeId);
        abort_if($device === null || $examType === null, 404);

        try {
            $exam = $cycle->start(auth()->user(), $device, ExamType::findOrFail($examType->id));
        } catch (ExamCycleException $exception) {
            $this->addError('deviceId', $exception->getMessage());

            return;
        }

        $this->redirectRoute('app.exams.show', $exam, navigate: true);
    }
}; ?>

<div class="flex max-w-5xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Novo exame') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Escolha o equipamento e o tipo de exame. Cada equipamento tem um exame aberto por vez.') }}</flux:text>
    </div>

    <form wire:submit="start" class="flex flex-col gap-6">
        <section class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('1. Equipamento') }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($this->devices as $device)
                    @php($busy = $device->exams->isNotEmpty())
                    @php($online = $device->last_seen_at?->gte(now()->subMinutes(5)))
                    <label wire:key="device-{{ $device->id }}" @class([
                        'flex flex-col gap-2 rounded-lg border p-4',
                        'cursor-pointer border-zinc-200 dark:border-zinc-700' => ! $busy,
                        'cursor-not-allowed border-zinc-200 bg-zinc-50 opacity-75 dark:border-zinc-700 dark:bg-zinc-900' => $busy,
                        'ring-2 ring-zinc-800 dark:ring-white' => $deviceId === $device->id,
                    ])>
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ $device->hostname }}</span>
                            <input type="radio" wire:model.live="deviceId" value="{{ $device->id }}" @disabled($busy) class="accent-zinc-800">
                        </span>
                        <span class="text-sm {{ $online ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400' }}">
                            {{ $online ? __('Online') : __('Offline') }}
                            @if ($busy) · {{ __('em exame: :exam', ['exam' => $device->exams->first()->examType->name]) }} @endif
                        </span>
                        <span class="text-xs text-zinc-500">{{ $device->system->code }} · {{ $device->client->trade_name }}</span>
                        @if ($busy)
                            <flux:link :href="route('app.exams.show', $device->exams->first())" wire:navigate class="text-sm">{{ __('Abrir o exame') }}</flux:link>
                        @endif
                    </label>
                @empty
                    <flux:text>{{ __('Nenhum equipamento com licença ativa.') }}</flux:text>
                @endforelse
            </div>
            <flux:error name="deviceId" />
        </section>

        @if ($deviceId)
            <section class="flex flex-col gap-3">
                <flux:heading size="lg">{{ __('2. Tipo de exame') }}</flux:heading>
                <flux:radio.group wire:model="examTypeId" class="flex flex-col gap-2">
                    @forelse ($this->examTypes as $examType)
                        <flux:radio :value="$examType->id" :label="$examType->name" :description="__('Pasta :folder', ['folder' => $examType->destination_folder])" />
                    @empty
                        <flux:text>{{ __('Nenhum exame habilitado neste equipamento. Fale com a Weega.') }}</flux:text>
                    @endforelse
                </flux:radio.group>
                <flux:error name="examTypeId" />
            </section>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>{{ __('Depois de iniciar, imprima na Yantra Printer do equipamento. Os arquivos aparecem no painel em poucos segundos.') }}</flux:text>
            <flux:button type="submit" variant="primary">{{ __('Iniciar exame') }}</flux:button>
        </div>
    </form>
</div>
