<?php

use App\Models\ApiRequestLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Requisições da API')] class extends Component {
    use WithPagination;

    #[Url(as: 'de')]
    public string $from = '';

    #[Url(as: 'ate')]
    public string $until = '';

    #[Url(as: 'rota')]
    public string $route = '';

    #[Url(as: 'resultado')]
    public string $result = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'until', 'route', 'result'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function logs()
    {
        return ApiRequestLog::query()
            ->with(['device:id,hostname', 'user:id,name'])
            ->when($this->from !== '', fn ($query) => $query->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->until !== '', fn ($query) => $query->where('created_at', '<=', $this->until.' 23:59:59'))
            ->when($this->route !== '', fn ($query) => $query->whereLike('route', '%'.$this->route.'%', caseSensitive: false))
            ->when($this->result === 'sucesso', fn ($query) => $query->where('status', '<', 400))
            ->when($this->result === 'erro', fn ($query) => $query->where('status', '>=', 400))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Log e auditoria') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Cada requisição da API do agente, sem o corpo e sem os cabeçalhos.') }}</flux:text>
    </div>

    <flux:navbar class="border-b border-zinc-200 dark:border-zinc-700">
        <flux:navbar.item :href="route('staff.logs.audit')" wire:navigate>{{ __('Ações dos usuários') }}</flux:navbar.item>
        <flux:navbar.item :href="route('staff.logs.requests')" current wire:navigate>{{ __('Requisições da API') }}</flux:navbar.item>
    </flux:navbar>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:input wire:model.live="from" type="date" :label="__('De')" />
        <flux:input wire:model.live="until" type="date" :label="__('Até')" />
        <flux:input wire:model.live.debounce.300ms="route" :label="__('Rota')" placeholder="/api/v1/agent/ping" />
        <flux:select wire:model.live="result" :label="__('Resultado')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            <flux:select.option value="sucesso">{{ __('Sucesso') }}</flux:select.option>
            <flux:select.option value="erro">{{ __('Erro') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>{{ __('Data e hora') }}</flux:table.column>
            <flux:table.column>{{ __('Origem') }}</flux:table.column>
            <flux:table.column>{{ __('Rota') }}</flux:table.column>
            <flux:table.column>{{ __('Resultado') }}</flux:table.column>
            <flux:table.column>{{ __('Tempo') }}</flux:table.column>
            <flux:table.column>{{ __('IP') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->logs as $log)
                <flux:table.row :key="$log->id">
                    <flux:table.cell class="font-mono text-xs tabular-nums">{{ $log->created_at->format('d/m/Y H:i:s') }}</flux:table.cell>
                    <flux:table.cell>{{ $log->device?->hostname ?? $log->user?->name ?? __('Sem token') }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $log->method }} {{ $log->route }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$log->status < 400 ? 'green' : ($log->status < 500 ? 'amber' : 'red')">{{ $log->status }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $log->duration_ms }} ms</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $log->ip ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('Nenhuma requisição encontrada.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
