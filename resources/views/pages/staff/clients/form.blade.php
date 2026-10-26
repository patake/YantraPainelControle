<?php

use App\Models\Client;
use App\Rules\ValidCnpj;
use App\Support\Cnpj;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?int $clientId = null;

    public string $cnpj = '';
    public string $legal_name = '';
    public string $trade_name = '';
    public string $contact_name = '';
    public string $contact_email = '';
    public string $contact_phone = '';
    public bool $active = true;

    public function mount(?Client $client = null): void
    {
        if ($client?->exists) {
            $this->authorize('update', $client);

            $this->clientId = $client->id;
            $this->cnpj = $client->formattedCnpj();
            $this->fill($client->only(['legal_name', 'trade_name', 'active']));
            $this->contact_name = (string) $client->contact_name;
            $this->contact_email = (string) $client->contact_email;
            $this->contact_phone = (string) $client->contact_phone;

            return;
        }

        $this->authorize('create', Client::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', new ValidCnpj],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'cnpj' => 'CNPJ',
            'legal_name' => __('razão social'),
            'trade_name' => __('nome fantasia'),
            'contact_name' => __('contato'),
            'contact_email' => __('e-mail do contato'),
            'contact_phone' => __('telefone do contato'),
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['cnpj'] = Cnpj::normalize($validated['cnpj']);

        // O CNPJ é único, também entre clientes excluídos (R-ACE-1).
        $this->validateCnpjIsUnique($validated['cnpj']);

        $client = $this->clientId ? Client::findOrFail($this->clientId) : new Client;
        $this->authorize($client->exists ? 'update' : 'create', $client->exists ? $client : Client::class);

        $client->fill($validated)->save();

        Flux::toast(variant: 'success', text: __('Cliente salvo.'));

        $this->redirectRoute('staff.clients.show', $client, navigate: true);
    }

    private function validateCnpjIsUnique(string $cnpj): void
    {
        validator(
            ['cnpj' => $cnpj],
            ['cnpj' => [Rule::unique('clients', 'cnpj')->ignore($this->clientId)]],
            ['cnpj.unique' => __('Já existe um cliente com este CNPJ.')],
        )->validate();
    }

    public function render()
    {
        return $this->view()->title($this->clientId ? __('Editar cliente') : __('Novo cliente'));
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('staff.clients.index')" wire:navigate>{{ __('Clientes') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $clientId ? __('Editar') : __('Novo') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <flux:heading size="xl" level="1" class="mt-2">{{ $clientId ? __('Editar cliente') : __('Novo cliente') }}</flux:heading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:card class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="cnpj" label="CNPJ" mask="99.999.999/9999-99" placeholder="00.000.000/0000-00" :disabled="(bool) $clientId" required />
            <div class="hidden sm:block"></div>
            <flux:input wire:model="legal_name" :label="__('Razão social')" required />
            <flux:input wire:model="trade_name" :label="__('Nome fantasia')" required />
            <flux:input wire:model="contact_name" :label="__('Contato')" />
            <flux:input wire:model="contact_email" :label="__('E-mail do contato')" type="email" />
            <flux:input wire:model="contact_phone" :label="__('Telefone do contato')" mask="(99) 99999-9999" />
            <div class="flex items-end">
                <flux:switch wire:model="active" :label="__('Cliente ativo')" />
            </div>
        </flux:card>

        <flux:callout icon="information-circle" variant="secondary">
            {{ __('Um cliente desativado bloqueia o login dos operadores dele.') }}
        </flux:callout>

        <div class="flex justify-end gap-2">
            <flux:button :href="$clientId ? route('staff.clients.show', $clientId) : route('staff.clients.index')" wire:navigate>{{ __('Cancelar') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Salvar') }}</flux:button>
        </div>
    </form>
</div>
