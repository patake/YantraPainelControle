<?php

use App\Enums\CommandType;
use App\Enums\DbType;
use App\Enums\UserRole;
use App\Models\AgentCommand;
use App\Models\Client;
use App\Models\ErpConnection;
use App\Models\License;
use App\Models\System;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
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
        $this->updatedTab($this->tab);
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
     * Operadores e gestores que ainda não têm vínculo com este cliente.
     */
    #[Computed]
    public function availableOperators()
    {
        return User::whereIn('role', [UserRole::Operator, UserRole::Manager])
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

        $operator = User::whereIn('role', [UserRole::Operator, UserRole::Manager])->findOrFail($this->operatorToLink);
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

    #[Locked]
    public ?int $editingLicenseId = null;

    public ?int $licenseSystemId = null;

    public ?int $licenseQuantity = null;

    public string $licenseValidUntil = '';

    #[Computed]
    public function systems()
    {
        return System::orderBy('code')->get(['id', 'code', 'name']);
    }

    public function editLicense(int $licenseId): void
    {
        $license = $this->client->licenses()->findOrFail($licenseId);

        $this->editingLicenseId = $license->id;
        $this->licenseSystemId = $license->system_id;
        $this->licenseQuantity = $license->quantity;
        $this->licenseValidUntil = $license->valid_until?->format('Y-m-d') ?? '';
    }

    public function cancelLicense(): void
    {
        $this->reset('editingLicenseId', 'licenseSystemId', 'licenseQuantity', 'licenseValidUntil');
        $this->resetValidation();
    }

    /**
     * Cria ou altera um pacote de licenças. Só o administrador altera (R-ACE-4).
     */
    public function saveLicense(): void
    {
        $this->authorize('update', $this->client);

        $license = $this->editingLicenseId
            ? $this->client->licenses()->findOrFail($this->editingLicenseId)
            : new License(['client_id' => $this->clientId]);

        $this->validate([
            'licenseSystemId' => [
                'required', 'integer', 'exists:systems,id',
                Rule::unique('licenses', 'system_id')->where('client_id', $this->clientId)->ignore($license->id),
            ],
            'licenseQuantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'licenseValidUntil' => ['nullable', 'date'],
        ], [
            'licenseSystemId.unique' => __('O cliente já tem um pacote deste sistema. Altere o pacote existente.'),
        ], [
            'licenseSystemId' => __('sistema'),
            'licenseQuantity' => __('quantidade'),
            'licenseValidUntil' => __('validade'),
        ]);

        $license->system_id = $this->licenseSystemId;
        $used = $license->exists ? $license->usedCount() : 0;

        // O pacote não fica menor que o uso atual: bloqueie equipamentos antes.
        if ($this->licenseQuantity < $used) {
            $this->addError('licenseQuantity', __('O cliente usa :used licenças deste sistema. Bloqueie equipamentos antes de reduzir o pacote.', ['used' => $used]));

            return;
        }

        $license->fill([
            'system_id' => $this->licenseSystemId,
            'quantity' => $this->licenseQuantity,
            'valid_until' => $this->licenseValidUntil !== '' ? $this->licenseValidUntil : null,
        ])->save();

        $this->cancelLicense();
        unset($this->licenses, $this->client);

        Flux::toast(variant: 'success', text: __('Pacote de licenças salvo.'));
    }

    public string $erpHost = '';

    public ?int $erpPort = 1521;

    public string $erpServiceName = '';

    public string $erpUsername = '';

    public string $erpPassword = '';

    public ?string $erpTestDeviceId = null;

    public function updatedTab(string $tab): void
    {
        if ($tab === 'erp' && $this->client->erpConnection) {
            $connection = $this->client->erpConnection;
            $this->erpHost = $connection->host;
            $this->erpPort = $connection->port;
            $this->erpServiceName = $connection->service_name;
            $this->erpUsername = $connection->username;
        }
    }

    /**
     * Uma conexão do ERP por cliente. Só o administrador altera conexões (R-EXA-3).
     */
    public function saveErpConnection(): void
    {
        $this->authorize('update', $this->client);

        $connection = $this->client->erpConnection ?? new ErpConnection(['client_id' => $this->clientId, 'db_type' => DbType::Oracle]);

        $this->validate([
            'erpHost' => ['required', 'string', 'max:255'],
            'erpPort' => ['required', 'integer', 'between:1,65535'],
            'erpServiceName' => ['required', 'string', 'max:255'],
            'erpUsername' => ['required', 'string', 'max:255'],
            'erpPassword' => [$connection->exists ? 'nullable' : 'required', 'string', 'max:255'],
        ], [], [
            'erpHost' => __('servidor'),
            'erpPort' => __('porta'),
            'erpServiceName' => __('service name'),
            'erpUsername' => __('usuário do banco'),
            'erpPassword' => __('senha do banco'),
        ]);

        $connection->fill([
            'host' => $this->erpHost,
            'port' => $this->erpPort,
            'service_name' => $this->erpServiceName,
            'username' => $this->erpUsername,
        ]);

        // A senha só muda quando o administrador digita uma senha nova.
        if ($this->erpPassword !== '') {
            $connection->password = $this->erpPassword;
        }

        $connection->save();

        $this->reset('erpPassword');
        unset($this->client);

        Flux::toast(variant: 'success', text: __('Conexão do ERP salva.'));
    }

    /**
     * O teste roda num equipamento do hospital: a nuvem não alcança o banco do ERP.
     */
    public function testErpConnection(): void
    {
        $this->authorize('update', $this->client);

        $this->validate(
            ['erpTestDeviceId' => ['required', 'string', Rule::in($this->devices->pluck('id')->all())]],
            [],
            ['erpTestDeviceId' => __('equipamento')],
        );

        AgentCommand::create([
            'device_id' => $this->erpTestDeviceId,
            'type' => CommandType::TestErp,
            'expires_at' => now()->addSeconds(CommandType::TestErp->ttlSeconds()),
        ]);

        Flux::toast(text: __('Teste enviado. O resultado aparece quando o agente responder.'));
    }

    #[Computed]
    public function lastErpTest(): ?AgentCommand
    {
        return AgentCommand::with('device:id,hostname')
            ->where('type', CommandType::TestErp)
            ->whereIn('device_id', $this->devices->pluck('id'))
            ->latest()
            ->first();
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
        <flux:navbar.item wire:click="$set('tab', 'erp')" :current="$tab === 'erp'">{{ __('Conexão do ERP') }}</flux:navbar.item>
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
                        <flux:select wire:model="operatorToLink" :label="__('Vincular um operador ou gestor')" :placeholder="__('Escolha o usuário')">
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
                                    <flux:button size="sm" variant="ghost" wire:click="unlinkOperator({{ $user->id }})" wire:confirm="{{ __('Remover o vínculo deste usuário?') }}">{{ __('Remover vínculo') }}</flux:button>
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
            <flux:text>{{ __('Em uso = equipamentos em trial ou ativos. O trial já usa uma licença.') }}</flux:text>

            @can('update', $this->client)
                <form wire:submit="saveLicense" class="grid items-end gap-3 sm:grid-cols-4">
                    <flux:select wire:model="licenseSystemId" :label="__('Sistema')" :placeholder="__('Escolha o sistema')" :disabled="(bool) $editingLicenseId">
                        @foreach ($this->systems as $system)
                            <flux:select.option :value="$system->id">{{ $system->code }} · {{ $system->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="licenseQuantity" type="number" min="1" :label="__('Quantidade')" />
                    <flux:input wire:model="licenseValidUntil" type="date" :label="__('Validade (opcional)')" />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary">{{ $editingLicenseId ? __('Salvar pacote') : __('Adicionar pacote') }}</flux:button>
                        @if ($editingLicenseId)
                            <flux:button wire:click="cancelLicense">{{ __('Cancelar') }}</flux:button>
                        @endif
                    </div>
                </form>
            @endcan

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Sistema') }}</flux:table.column>
                    <flux:table.column>{{ __('Contratadas') }}</flux:table.column>
                    <flux:table.column>{{ __('Em uso') }}</flux:table.column>
                    <flux:table.column>{{ __('Validade') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->licenses as $license)
                        <flux:table.row :key="$license->id">
                            <flux:table.cell><flux:badge size="sm">{{ $license->system->code }}</flux:badge> {{ $license->system->name }}</flux:table.cell>
                            <flux:table.cell class="tabular-nums">{{ $license->quantity }}</flux:table.cell>
                            <flux:table.cell class="tabular-nums">{{ $license->usedCount() }}</flux:table.cell>
                            <flux:table.cell class="tabular-nums">{{ $license->valid_until?->format('d/m/Y') ?? __('Sem prazo') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('update', $this->client)
                                    <flux:button size="sm" variant="ghost" wire:click="editLicense({{ $license->id }})">{{ __('Alterar') }}</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">{{ __('Nenhuma licença cadastrada.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($tab === 'erp')
        <div class="grid gap-6 xl:grid-cols-[3fr_2fr]">
            <flux:card>
                <form wire:submit="saveErpConnection" class="flex flex-col gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Dados da conexão') }}</flux:heading>
                        <flux:text>{{ __('O painel não acessa o banco. Só o agente usa a conexão, dentro da rede do hospital.') }}</flux:text>
                    </div>
                    <fieldset @disabled(! auth()->user()->can('update', $this->client)) class="grid gap-4 sm:grid-cols-2">
                        <flux:input :value="'Oracle'" :label="__('Tipo de banco')" disabled />
                        <flux:input wire:model="erpHost" :label="__('Servidor')" class="font-mono" />
                        <flux:input wire:model="erpPort" type="number" :label="__('Porta')" class="font-mono" />
                        <flux:input wire:model="erpServiceName" :label="__('Service name')" class="font-mono" />
                        <flux:input wire:model="erpUsername" :label="__('Usuário do banco')" class="font-mono" />
                        <flux:input wire:model="erpPassword" type="password" autocomplete="new-password" :label="__('Senha do banco')"
                            :placeholder="$this->client->erpConnection ? __('Gravada e criptografada. Digite para trocar.') : ''" />
                    </fieldset>
                    @can('update', $this->client)
                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary">{{ __('Salvar conexão') }}</flux:button>
                        </div>
                    @endcan
                </form>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Testar conexão') }}</flux:heading>
                    <flux:text>{{ __('O teste roda num equipamento do hospital, porque a nuvem não alcança o banco.') }}</flux:text>
                </div>
                @can('update', $this->client)
                    <form wire:submit="testErpConnection" class="flex flex-wrap items-end gap-2">
                        <div class="min-w-48 flex-1">
                            <flux:select wire:model="erpTestDeviceId" :label="__('Equipamento')" :placeholder="__('Escolha o equipamento')">
                                @foreach ($this->devices as $device)
                                    <flux:select.option :value="$device->id">{{ $device->hostname }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:button type="submit" :disabled="! $this->client->erpConnection">{{ __('Testar agora') }}</flux:button>
                    </form>
                @endcan
                @if ($this->lastErpTest)
                    <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                        <div class="font-medium">{{ __('Último teste') }} · {{ $this->lastErpTest->device->hostname }} · {{ $this->lastErpTest->created_at->format('d/m H:i') }}</div>
                        <div class="mt-1 text-zinc-600 dark:text-zinc-400">
                            {{ match ($this->lastErpTest->status->value) {
                                'pending' => __('Aguardando o agente.'),
                                'delivered' => __('O agente recebeu o comando.'),
                                'done' => __('Conexão funcionando.').' '.($this->lastErpTest->result['data']['server_version'] ?? ''),
                                'failed' => __('Falhou:').' '.($this->lastErpTest->result['error'] ?? ''),
                                'expired' => __('O agente não respondeu a tempo.'),
                            } }}
                        </div>
                    </div>
                @endif
            </flux:card>
        </div>
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
