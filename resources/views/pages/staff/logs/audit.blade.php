<?php

use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Auditoria')] class extends Component {
    use WithPagination;

    #[Url(as: 'de')]
    public string $from = '';

    #[Url(as: 'ate')]
    public string $until = '';

    #[Url(as: 'usuario')]
    public string $userId = '';

    #[Url(as: 'acao')]
    public string $action = '';

    #[Url(as: 'objeto')]
    public string $type = '';

    public ?int $expanded = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'until', 'userId', 'action', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    #[Computed]
    public function logs()
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->when($this->from !== '', fn ($query) => $query->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->until !== '', fn ($query) => $query->where('created_at', '<=', $this->until.' 23:59:59'))
            ->when($this->userId !== '', fn ($query) => $query->where('user_id', (int) $this->userId))
            ->when($this->action !== '', fn ($query) => $query->where('action', $this->action))
            ->when($this->type !== '', fn ($query) => $query->where('auditable_type', $this->type))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function types()
    {
        return AuditLog::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Log e auditoria') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Quem mudou o quê e quando. A auditoria não grava senhas nem segredos.') }}</flux:text>
    </div>

    <flux:navbar class="border-b border-zinc-200 dark:border-zinc-700">
        <flux:navbar.item :href="route('staff.logs.audit')" current wire:navigate>{{ __('Ações dos usuários') }}</flux:navbar.item>
        <flux:navbar.item :href="route('staff.logs.requests')" wire:navigate>{{ __('Requisições da API') }}</flux:navbar.item>
    </flux:navbar>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <flux:input wire:model.live="from" type="date" :label="__('De')" />
        <flux:input wire:model.live="until" type="date" :label="__('Até')" />
        <flux:select wire:model.live="userId" :label="__('Usuário')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            @foreach ($this->users as $user)
                <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="action" :label="__('Ação')">
            <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
            <flux:select.option value="created">{{ __('created') }}</flux:select.option>
            <flux:select.option value="updated">{{ __('updated') }}</flux:select.option>
            <flux:select.option value="deleted">{{ __('deleted') }}</flux:select.option>
            <flux:select.option value="two_factor_reset">{{ __('two_factor_reset') }}</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="type" :label="__('Objeto')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            @foreach ($this->types as $option)
                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>{{ __('Data e hora') }}</flux:table.column>
            <flux:table.column>{{ __('Usuário') }}</flux:table.column>
            <flux:table.column>{{ __('Ação') }}</flux:table.column>
            <flux:table.column>{{ __('Objeto') }}</flux:table.column>
            <flux:table.column>{{ __('IP') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->logs as $log)
                <flux:table.row :key="'log-'.$log->id">
                    <flux:table.cell class="font-mono text-xs tabular-nums">{{ $log->created_at->format('d/m/Y H:i:s') }}</flux:table.cell>
                    <flux:table.cell>{{ $log->user?->name ?? __('Sistema') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($log->action) { 'created' => 'green', 'deleted' => 'red', default => 'blue' }">{{ __($log->action) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $log->auditable_type }} <span class="font-mono text-xs text-zinc-500">#{{ $log->auditable_id }}</span></flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $log->ip ?? '—' }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($log->old_values || $log->new_values)
                            <flux:button size="sm" variant="ghost" wire:click="toggle({{ $log->id }})">
                                {{ $expanded === $log->id ? __('Fechar') : __('Ver valores') }}
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>

                @if ($expanded === $log->id)
                    <flux:table.row :key="'diff-'.$log->id">
                        <flux:table.cell colspan="6">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-start text-xs text-zinc-500">
                                            <th class="py-1 pe-4 text-start font-medium">{{ __('Campo') }}</th>
                                            <th class="py-1 pe-4 text-start font-medium">{{ __('Valor antigo') }}</th>
                                            <th class="py-1 text-start font-medium">{{ __('Valor novo') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                                            <tr class="border-t border-zinc-100 dark:border-zinc-700">
                                                <td class="py-1 pe-4 font-mono text-xs">{{ $field }}</td>
                                                <td class="py-1 pe-4 text-red-700 dark:text-red-400">{{ json_encode($log->old_values[$field] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td>
                                                <td class="py-1 text-green-700 dark:text-green-400">{{ json_encode($log->new_values[$field] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endif
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('Nenhum registro encontrado.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
