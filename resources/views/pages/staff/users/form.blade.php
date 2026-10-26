<?php

use App\Actions\Users\SendUserInvitation;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?int $userId = null;

    public string $name = '';
    public string $email = '';
    public string $role = 'operator';
    public bool $active = true;

    /** @var list<int> */
    public array $clientIds = [];

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->authorize('update', $user);

            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = $user->role->value;
            $this->active = $user->active;
            $this->clientIds = $user->clients()->pluck('clients.id')->all();

            return;
        }

        $this->authorize('create', User::class);
    }

    #[Computed]
    public function clients()
    {
        return Client::orderBy('trade_name')->get(['id', 'trade_name', 'active']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'active' => ['boolean'],
            // O operador precisa de pelo menos um cliente (R-ACE-2).
            'clientIds' => [Rule::requiredIf(UserRole::tryFrom($this->role)?->isClientUser() ?? false), 'array'],
            'clientIds.*' => ['integer', 'exists:clients,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('nome'),
            'email' => __('e-mail'),
            'role' => __('papel'),
            'clientIds' => __('clientes'),
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();
        $isNew = $this->userId === null;

        $user = $isNew ? new User : User::findOrFail($this->userId);
        $this->authorize($isNew ? 'create' : 'update', $isNew ? User::class : $user);

        $user->fill([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'role' => $validated['role'],
            'active' => $validated['active'],
        ]);

        if ($isNew) {
            // Senha provisória que ninguém conhece: o usuário cria a senha pelo link do e-mail.
            $user->password = Str::password(32);
        }

        $user->save();
        $this->syncClients($user, UserRole::from($validated['role'])->isClientUser() ? $validated['clientIds'] : []);

        if ($isNew) {
            app(SendUserInvitation::class)->handle($user);
        }

        Flux::toast(variant: 'success', text: $isNew
            ? __('Usuário criado. O usuário recebe um e-mail para criar a senha.')
            : __('Usuário salvo.'));

        $this->redirectRoute('staff.users.index', navigate: true);
    }

    /**
     * @param  list<int>  $clientIds
     */
    private function syncClients(User $user, array $clientIds): void
    {
        $before = $user->clients()->pluck('clients.id')->sort()->values()->all();
        $user->clients()->sync($clientIds);
        $after = $user->clients()->pluck('clients.id')->sort()->values()->all();

        if ($before !== $after) {
            $user->writeAudit('updated', ['client_ids' => $before], ['client_ids' => $after]);
        }
    }

    public function render()
    {
        return $this->view()->title($this->userId ? __('Editar usuário') : __('Novo usuário'));
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('staff.users.index')" wire:navigate>{{ __('Usuários') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $userId ? __('Editar') : __('Novo') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <flux:heading size="xl" level="1" class="mt-2">{{ $userId ? __('Editar usuário') : __('Novo usuário') }}</flux:heading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:card class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="name" :label="__('Nome')" required />
            <flux:input wire:model="email" :label="__('E-mail')" type="email" required />
            <flux:select wire:model.live="role" :label="__('Papel')">
                @foreach (App\Enums\UserRole::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex items-end">
                <flux:switch wire:model="active" :label="__('Usuário ativo')" />
            </div>
        </flux:card>

        @if ($role === App\Enums\UserRole::Operator->value)
            <flux:card class="flex flex-col gap-4">
                <div>
                    <flux:heading>{{ __('Clientes do usuário') }}</flux:heading>
                    <flux:text>{{ __('O operador e o gestor veem só os dados dos clientes marcados.') }}</flux:text>
                </div>
                <flux:checkbox.group wire:model="clientIds" class="grid gap-2 sm:grid-cols-2">
                    @foreach ($this->clients as $client)
                        <flux:checkbox :value="$client->id" :label="$client->trade_name.($client->active ? '' : ' ('.__('desativado').')')" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="clientIds" />
            </flux:card>
        @else
            <flux:callout icon="shield-check" variant="secondary">
                {{ __('A Staff Weega vê todos os clientes.') }}
            </flux:callout>
        @endif

        @unless ($userId)
            <flux:callout icon="envelope" variant="secondary">
                {{ __('O usuário recebe um e-mail para criar a senha. O convite vale por 7 dias. No primeiro login, o painel pede o cadastro do 2FA.') }}
            </flux:callout>
        @endunless

        <div class="flex justify-end gap-2">
            <flux:button :href="route('staff.users.index')" wire:navigate>{{ __('Cancelar') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Salvar') }}</flux:button>
        </div>
    </form>
</div>
