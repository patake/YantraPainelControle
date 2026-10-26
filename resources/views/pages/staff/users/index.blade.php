<?php

use App\Actions\Users\SendUserInvitation;
use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Usuários')] class extends Component {
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'papel')]
    public string $role = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->with('clients:id,trade_name')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', '%'.$this->search.'%', caseSensitive: false)
                ->orWhereLike('email', '%'.$this->search.'%', caseSensitive: false)))
            ->when(UserRole::tryFrom($this->role), fn ($query, UserRole $role) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * Apaga o 2FA de um usuário sem celular e sem código de recuperação.
     * O usuário cadastra o 2FA de novo no próximo login.
     */
    public function resetTwoFactor(int $userId, DisableTwoFactorAuthentication $disable): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('resetTwoFactor', $user);

        $disable($user);
        $user->writeAudit('two_factor_reset', [], []);

        Flux::toast(variant: 'success', text: __('2FA reiniciado. O usuário cadastra o 2FA no próximo login.'));
    }

    /**
     * Envia um convite novo, válido por 7 dias. O link anterior perde o valor (R-ACE-13).
     */
    public function resendInvitation(int $userId, SendUserInvitation $send): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);
        abort_if($user->invited_at === null, 409);

        $send->handle($user);

        Flux::toast(variant: 'success', text: __('Convite enviado de novo. O link vale por 7 dias.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Usuários') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Staff Weega e operadores dos clientes. Todos usam 2FA.') }}</flux:text>
        </div>

        @can('create', App\Models\User::class)
            <flux:button variant="primary" icon="plus" :href="route('staff.users.create')" wire:navigate>{{ __('Novo usuário') }}</flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-4">
        <div class="min-w-64 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Buscar')" :placeholder="__('Nome ou e-mail')" />
        </div>
        <div class="w-48">
            <flux:select wire:model.live="role" :label="__('Papel')">
                <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                @foreach (App\Enums\UserRole::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>{{ __('Usuário') }}</flux:table.column>
            <flux:table.column>{{ __('Papel') }}</flux:table.column>
            <flux:table.column>{{ __('Clientes') }}</flux:table.column>
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
                        <flux:badge size="sm" :color="$user->isStaff() ? 'red' : 'zinc'">{{ $user->role->label() }}</flux:badge>
                        @unless ($user->active)
                            <flux:badge size="sm" color="zinc">{{ __('Desativado') }}</flux:badge>
                        @endunless
                        @if ($user->invited_at)
                            <flux:badge size="sm" color="amber">{{ __('Convite pendente') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->isStaff() ? __('Todos') : ($user->clients->pluck('trade_name')->join(', ') ?: '—') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->hasConfirmedTwoFactor())
                            <flux:badge color="green" size="sm">{{ __('Ativo') }}</flux:badge>
                        @else
                            <flux:badge color="amber" size="sm">{{ __('Pendente') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $user->last_login_at?->format('d/m/Y H:i') ?? __('Nunca') }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-1">
                            @can('resetTwoFactor', $user)
                                @if ($user->two_factor_secret)
                                    <flux:button size="sm" variant="ghost" wire:click="resetTwoFactor({{ $user->id }})" wire:confirm="{{ __('Reiniciar o 2FA deste usuário?') }}">{{ __('Reiniciar 2FA') }}</flux:button>
                                @endif
                            @endcan
                            @can('update', $user)
                                @if ($user->invited_at)
                                    <flux:button size="sm" variant="ghost" wire:click="resendInvitation({{ $user->id }})">{{ __('Reenviar convite') }}</flux:button>
                                @endif
                                <flux:button size="sm" :href="route('staff.users.edit', $user)" wire:navigate>{{ __('Editar') }}</flux:button>
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('Nenhum usuário encontrado.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
