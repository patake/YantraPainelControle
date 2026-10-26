<?php

use App\Enums\ReleaseChannel;
use App\Models\AgentRelease;
use App\Models\Device;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Versões do agente')] class extends Component {
    use WithFileUploads;

    public string $version = '';

    public string $channel = 'pilot';

    public string $notes = '';

    public $installer;

    public function mount(): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
    }

    #[Computed]
    public function releases()
    {
        return AgentRelease::get()->sort(fn ($a, $b) => version_compare($b->version, $a->version))->values();
    }

    /**
     * Quantos equipamentos de cada canal já usam a versão mais nova do canal.
     *
     * @return array<string, array{latest: ?string, updated: int, total: int}>
     */
    #[Computed]
    public function channels(): array
    {
        $result = [];

        foreach (ReleaseChannel::cases() as $channel) {
            $latest = AgentRelease::latestFor($channel)?->version;
            $devices = Device::usingLicense()->where('release_channel', $channel);

            $result[$channel->value] = [
                'latest' => $latest,
                'updated' => $latest ? (clone $devices)->where('agent_version', $latest)->count() : 0,
                'total' => $devices->count(),
            ];
        }

        return $result;
    }

    /**
     * Envia o instalador ao S3 (agent-releases/) e publica a versão. Só o administrador publica.
     */
    public function save(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate([
            'version' => ['required', 'regex:/^\d+\.\d+\.\d+$/', Rule::unique('agent_releases', 'version')],
            'channel' => ['required', Rule::enum(ReleaseChannel::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'installer' => ['required', 'file', 'max:204800', 'extensions:exe'],
        ], [
            'version.regex' => __('Use o formato 2.1.0.'),
            'installer.extensions' => __('Envie o instalador .exe.'),
        ], [
            'version' => __('versão'),
            'installer' => __('instalador'),
        ]);

        $path = AgentRelease::storagePathFor($this->version);
        $stream = fopen($this->installer->getRealPath(), 'r');
        Storage::disk('s3')->writeStream($path, $stream);
        is_resource($stream) && fclose($stream);

        AgentRelease::create([
            'version' => $this->version,
            'channel' => $this->channel,
            'storage_path' => $path,
            'sha256' => hash_file('sha256', $this->installer->getRealPath()),
            'size_bytes' => $this->installer->getSize(),
            'notes' => $this->notes !== '' ? $this->notes : null,
            'published_at' => now(),
        ]);

        $this->reset('version', 'notes', 'installer');
        $this->channel = 'pilot';
        unset($this->releases, $this->channels);

        Flux::toast(variant: 'success', text: __('Versão publicada.'));
    }

    public function promote(int $releaseId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        AgentRelease::findOrFail($releaseId)->update(['channel' => ReleaseChannel::Stable]);
        unset($this->releases, $this->channels);

        Flux::toast(variant: 'success', text: __('Versão promovida para o canal estável.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Versões do agente') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Os agentes consultam a versão do seu canal e se atualizam sozinhos. Publique primeiro no canal piloto.') }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach (App\Enums\ReleaseChannel::cases() as $option)
            @php($info = $this->channels[$option->value])
            <flux:card class="flex flex-col gap-2" wire:key="channel-{{ $option->value }}">
                <flux:text>{{ $option === App\Enums\ReleaseChannel::Pilot ? __('Canal piloto') : __('Canal estável') }} · {{ __(':total equipamentos', ['total' => $info['total']]) }}</flux:text>
                <flux:heading size="xl" class="font-mono">{{ $info['latest'] ?? '—' }}</flux:heading>
                <flux:text class="tabular-nums">{{ __(':updated de :total atualizados', ['updated' => $info['updated'], 'total' => $info['total']]) }}</flux:text>
            </flux:card>
        @endforeach
    </div>

    @if (auth()->user()->isAdmin())
        <flux:card>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="version" :label="__('Versão')" placeholder="2.1.0" />
                <flux:select wire:model="channel" :label="__('Canal')">
                    <flux:select.option value="pilot">{{ __('Piloto') }}</flux:select.option>
                    <flux:select.option value="stable">{{ __('Estável') }}</flux:select.option>
                </flux:select>
                <div class="sm:col-span-2">
                    <flux:textarea wire:model="notes" :label="__('Notas da versão')" rows="3" />
                </div>
                <div class="sm:col-span-2">
                    <flux:input type="file" wire:model="installer" :label="__('Instalador (.exe, até 200 MB)')" accept=".exe" />
                    <div wire:loading wire:target="installer" class="mt-2 text-sm text-zinc-500">{{ __('Enviando o arquivo…') }}</div>
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Publicar versão') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Versão') }}</flux:table.column>
            <flux:table.column>{{ __('Canal') }}</flux:table.column>
            <flux:table.column>{{ __('Publicação') }}</flux:table.column>
            <flux:table.column>{{ __('Tamanho') }}</flux:table.column>
            <flux:table.column>SHA-256</flux:table.column>
            <flux:table.column>{{ __('Notas') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->releases as $release)
                <flux:table.row :key="$release->id">
                    <flux:table.cell class="font-mono font-medium">{{ $release->version }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$release->channel === App\Enums\ReleaseChannel::Stable ? 'green' : 'amber'">
                            {{ $release->channel === App\Enums\ReleaseChannel::Stable ? __('Estável') : __('Piloto') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $release->published_at?->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ Illuminate\Support\Number::fileSize($release->size_bytes) }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ substr($release->sha256, 0, 4) }}…{{ substr($release->sha256, -4) }}</flux:table.cell>
                    <flux:table.cell>{{ $release->notes ?? '—' }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @if (auth()->user()->isAdmin() && $release->channel === App\Enums\ReleaseChannel::Pilot)
                            <flux:button size="sm" variant="primary" wire:click="promote({{ $release->id }})" wire:confirm="{{ __('Liberar esta versão para todos os equipamentos?') }}">{{ __('Promover a estável') }}</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('Nenhuma versão publicada.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
