<?php

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $clientId;

    #[Url(as: 'aba')]
    public string $tab = 'dados';

    public ?int $operatorToLink = null;

    public function mount(Client $client): void
    {
        $this->authorize('view', $client);
        $this->clientId = $client->id;
    }

    #[Computed]
    public function client(): Client
    {
        return Client::with(['erpConnection'])
            ->withCount(['users', 'devices', 'licenses'])
            ->findOrFail($this->clientId);
    }

    #[Computed]
    public function users()
    {
        return $this->client->users()->orderBy('name')->get();
    }

    /**
     * Operadores que ainda não têm vínculo com este cliente.
     */
    #[Computed]
    public function availableOperators()
    {
        return User::where('role', UserRole::Operator)
            ->whereDoesntHave('clients', fn ($query) => $query->whereKey($this->clientId))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function licenses()
    {
        return $this->client->licenses()->with('system')->get();
    }

    #[Computed]
    public function devices()
    {
        return $this->client->devices()->with('system')->orderBy('hostname')->get();
    }

    public function linkOperator(): void
    {
        $this->authorize('update', $this->client);

        $this->validate(
            ['operatorToLink' => ['required', 'integer', 'exists:users,id']],
            [],
            ['operatorToLink' => __('operador')],
        );

        $operator = User::where('role', UserRole::Operator)->findOrFail($this->operatorToLink);
        $this->syncOperator($operator, attach: true);

        $this->reset('operatorToLink');
        Flux::toast(variant: 'success', text: __('Operador vinculado.'));
    }

    public function unlinkOperator(int $userId): void
    {
        $this->authorize('update', $this->client);

        $this->syncOperator(User::findOrFail($userId), attach: false);

        Flux::toast(text: __('Vínculo removido.'));
    }

    /**
     * Vincula ou desvincula e grava a auditoria no usuário.
     */
    private function syncOperator(User $operator, bool $attach): void
    {
        $before = $operator->clients()->pluck('clients.id')->all();

        $attach
            ? $operator->clients()->syncWithoutDetaching([$this->clientId])
            : $operator->clients()->detach($this->clientId);

        $after = $operator->clients()->pluck('clients.id')->all();
        $operator->writeAudit('updated', ['client_ids' => $before], ['client_ids' => $after]);

        unset($this->users, $this->availableOperators);
    }

    public function render()
    {
        return $this->view()->title($this->client->trade_name);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('staff.clients.index')" wire:navigate>{{ __('Clientes') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $this->client->trade_name }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <flux:heading size="xl" level="1">{{ $this->client->trade_name }}</flux:heading>
                @if ($this->client->active)
                    <flux:badge color="green" size="sm">{{ __('Ativo') }}</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">{{ __('Desativado') }}</flux:badge>
                @endif
            </div>
            <flux:text class="mt-1 font-mono text-xs">CNPJ {{ $this->client->formattedCnpj() }}</flux:text>
        </div>

        @can('update', $this->client)
            <flux:button icon="pencil-square" :href="route('staff.clients.edit', $this->client)" wire:navigate>{{ __('Editar dados') }}</flux:button>
        @endcan
    </div>

    <flux:navbar class="border-b border-zinc-200 dark:border-zinc-700">
        <flux:navbar.item wire:click="$set('tab', 'dados')" :current="$tab === 'dados'">{{ __('Dados') }}</flux:navbar.item>
        <flux:navbar.item wire:click="$set('tab', 'usuarios')" :current="$tab === 'usuarios'" :badge="$this->client->users_count">{{ __('Usuários') }}</flux:navbar.item>
        <flux:navbar.item wire:click="$set('tab', 'licencas')" :current="$tab === 'licencas'" :badge="$this->client->licenses_count">{{ __('Licenças') }}</flux:navbar.item>
        <flux:navbar.item wire:click="$set('tab', 'equipamentos')" :current="$tab === 'equipamentos'" :badge="$this->client->devices_count">{{ __('Equipamentos') }}</flux:navbar.item>
    </flux:navbar>

    @if ($tab === 'dados')
        <flux:card>
            <dl class="grid gap-6 sm:grid-cols-2">
                <div><dt class="text-sm text-zinc-500">{{ __('Razão social') }}</dt><dd class="mt-1">{{ $this->client->legal_name }}</dd></div>
                <div><dt class="text-sm text-zinc-500">{{ __('Nome fantasia') }}</dt><dd class="mt-1">{{ $this->client->trade_name }}</dd></div>
                <div><dt class="text-sm text-zinc-500">{{ __('Contato') }}</dt><dd class="mt-1">{{ $this->client->contact_name ?: '—' }}</dd></div>
                <div><dt class="text-sm text-zinc-500">{{ __('E-mail do contato') }}</dt><dd class="mt-1 break-all">{{ $this->client->contact_email ?: '—' }}</dd></div>
                <div><dt class="text-sm text-zinc-500">{{ __('Telefone do contato') }}</dt><dd class="mt-1">{{ $this->client->contact_phone ?: '—' }}</dd></div>
                <div><dt class="text-sm text-zinc-500">{{ __('Conexão do ERP') }}</dt><dd class="mt-1">{{ $this->client->erpConnection ? __('Cadastrada') : __('Não cadastrada') }}</dd></div>
            </dl>
        </flux:card>
    @endif

    @if ($tab === 'usuarios')
        <flux:card class="flex flex-col gap-4">
            @can('update', $this->client)
                <form wire:submit="linkOperator" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-72 flex-1">
                        <flux:select wire:model="operatorToLink" :label="__('Vincular um operador')" :placeholder="__('Escolha o operador')">
                            @foreach ($this->availableOperators as $operator)
                                <flux:select.option :value="$operator->id">{{ $operator->name }} · {{ $operator->email }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button type="submit" variant="primary">{{ __('Vincular') }}</flux:button>
                </form>
            @endcan

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Usuário') }}</flux:table.column>
                    <flux:table.column>{{ __('2FA') }}</flux:table.column>
                    <flux:table.column>{{ __('Último acesso') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell>
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $user->email }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($user->hasConfirmedTwoFactor())
                                    <flux:badge color="green" size="sm">{{ __('Ativo') }}</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">{{ __('Pendente') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="tabular-nums">{{ $user->last_login_at?->format('d/m/Y H:i') ?? __('Nunca') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('update', $this->client)
                                    <flux:button size="sm" variant="ghost" wire:click="unlinkOperator({{ $user->id }})" wire:confirm="{{ __('Remover o vínculo deste operador?') }}">{{ __('Remover vínculo') }}</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">{{ __('Nenhum usuário vinculado.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($tab === 'licencas')
        <flux:card class="flex flex-col gap-4">
            <flux:text>{{ __('O cadastro de licenças entra na etapa E3. Esta lista mostra os pacotes já cadastrados.') }}</flux:text>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Sistema') }}</flux:table.column>
                    <flux:table.column>{{ __('Contratadas') }}</flux:table.column>
                    <flux:table.column>{{ __('Em uso') }}</flux:table.column>
                    <flux:table.column>{{ __('Validade') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->licenses as $license)
                        <flux:table.row :key="$license->id">
                            <flux:table.cell><flux:badge size="sm">{{ $license->system->code }}</flux:badge> {{ $license->system->name }}</flux:table.cell>
                            <flux:table.cell class="tabular-nums">{{ $license->quantity }}</flux:table.cell>
                            <flux:table.cell class="tabular-nums">{{ $license->usedCount() }}</flux:table.cell>
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
    @endif

    @if ($tab === 'equipamentos')
        <flux:card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Equipamento') }}</flux:table.column>
                    <flux:table.column>{{ __('Sistema') }}</flux:table.column>
                    <flux:table.column>{{ __('Estado') }}</flux:table.column>
                    <flux:table.column>{{ __('Último sinal') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->devices as $device)
                        <flux:table.row :key="$device->id">
                            <flux:table.cell class="font-medium">{{ $device->hostname }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm">{{ $device->system->code }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $device->status->label() }}</flux:table.cell>
                            <flux:table.cell class="tabular-nums">{{ $device->last_seen_at?->diffForHumans() ?? __('Nunca') }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">{{ __('Nenhum equipamento registrado.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
