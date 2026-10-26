<?php

use App\Actions\Exams\ImportYantraCardioExam;
use App\Models\Client;
use App\Models\ExamType;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Exames')] class extends Component {
    use WithFileUploads;

    #[Url(as: 'cliente')]
    public string $clientId = '';

    public ?int $importClientId = null;

    public $examFile;

    public $parametersFile;

    public function mount(): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
    }

    #[Computed]
    public function clients()
    {
        return Client::orderBy('trade_name')->get(['id', 'trade_name']);
    }

    #[Computed]
    public function exams()
    {
        return ExamType::query()
            ->with('client:id,trade_name')
            ->withCount(['devices', 'fields'])
            ->when($this->clientId !== '', fn ($query) => $query->where('client_id', (int) $this->clientId))
            ->orderBy('client_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Importa EXAME.json e PARAMETROS.json de uma pasta Queries\<Exame> do Yantra Cardio 3.0.
     * Só o administrador altera exames e SQLs (R-EXA-3).
     */
    public function import(ImportYantraCardioExam $import): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate([
            'importClientId' => ['required', 'integer', 'exists:clients,id'],
            'examFile' => ['required', 'file', 'max:1024', 'extensions:json'],
            'parametersFile' => ['required', 'file', 'max:1024', 'extensions:json'],
        ], [], [
            'importClientId' => __('cliente'),
            'examFile' => 'EXAME.json',
            'parametersFile' => 'PARAMETROS.json',
        ]);

        try {
            $exam = $import->handle(
                Client::findOrFail($this->importClientId),
                (string) file_get_contents($this->examFile->getRealPath()),
                (string) file_get_contents($this->parametersFile->getRealPath()),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('examFile', $exception->getMessage());

            return;
        }

        $this->reset('examFile', 'parametersFile');
        Flux::modal('import-exam')->close();
        Flux::toast(variant: 'success', text: __('Exame :name importado.', ['name' => $exam->name]));

        $this->redirectRoute('staff.exams.edit', $exam, navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Exames') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Cada exame tem os 5 SQLs do ERP e as leituras. Só o administrador altera SQLs.') }}</flux:text>
        </div>

        @if (auth()->user()->isAdmin())
            <div class="flex gap-2">
                <flux:modal.trigger name="import-exam">
                    <flux:button icon="arrow-up-tray">{{ __('Importar do Yantra Cardio 3.0') }}</flux:button>
                </flux:modal.trigger>
                <flux:button variant="primary" icon="plus" :href="route('staff.exams.create')" wire:navigate>{{ __('Novo exame') }}</flux:button>
            </div>
        @endif
    </div>

    <div class="w-72">
        <flux:select wire:model.live="clientId" :label="__('Cliente')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            @foreach ($this->clients as $client)
                <flux:select.option :value="$client->id">{{ $client->trade_name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Exame') }}</flux:table.column>
            <flux:table.column>{{ __('Cliente') }}</flux:table.column>
            <flux:table.column>{{ __('Procedimento') }}</flux:table.column>
            <flux:table.column>{{ __('Leituras') }}</flux:table.column>
            <flux:table.column>{{ __('Equipamentos') }}</flux:table.column>
            <flux:table.column>{{ __('Versão') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->exams as $exam)
                <flux:table.row :key="$exam->id">
                    <flux:table.cell>
                        <flux:link :href="route('staff.exams.edit', $exam)" wire:navigate class="font-medium">{{ $exam->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $exam->client->trade_name }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $exam->procedure_code ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $exam->fields_count }}</flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $exam->devices_count }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">v{{ $exam->version }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$exam->active ? 'green' : 'zinc'">{{ $exam->active ? __('Ativo') : __('Inativo') }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('Nenhum exame cadastrado.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="import-exam" class="md:w-[32rem]">
        <form wire:submit="import" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ __('Importar do Yantra Cardio 3.0') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Envie os dois arquivos da pasta Queries\\<Exame> da instalação do cliente. Um exame com o mesmo nome é atualizado.') }}</flux:text>
            </div>
            <flux:select wire:model="importClientId" :label="__('Cliente')" :placeholder="__('Escolha o cliente')">
                @foreach ($this->clients as $client)
                    <flux:select.option :value="$client->id">{{ $client->trade_name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="file" wire:model="examFile" label="EXAME.json" accept=".json" />
            <flux:input type="file" wire:model="parametersFile" label="PARAMETROS.json" accept=".json" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button>{{ __('Cancelar') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Importar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
