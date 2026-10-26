<?php

use App\Actions\Exams\SaveExamType;
use App\Enums\ErpType;
use App\Enums\ExamFieldKind;
use App\Models\Client;
use App\Models\ExamType;
use App\Rules\ValidSerialPattern;
use App\Support\SerialPattern;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?int $examId = null;

    public ?int $clientId = null;
    public string $name = '';
    public string $erpType = 'DELPHI';
    public string $procedureCode = '';
    public string $destinationFolder = '';
    public bool $active = true;

    /** @var array<string, bool> */
    public array $printOptions = ['print' => false, 'print_one_page' => false, 'complete_after_file' => false];

    /** @var array<string, string> */
    public array $sql = ['query' => '', 'auth' => '', 'insert' => '', 'image' => '', 'update' => ''];

    public string $sqlTab = 'query';

    /** @var list<array<string, mixed>> */
    public array $fields = [];

    /**
     * Regras regex da porta serial (E8, R-EXA-12).
     *
     * @var list<array{field: string, pattern: string}>
     */
    public array $serialRules = [];

    /**
     * Conteúdo colado para testar as regras. Não é gravado.
     */
    public string $serialSample = '';

    public function mount(?ExamType $examType = null): void
    {
        abort_unless(auth()->user()->isStaff(), 403);

        if ($examType?->exists) {
            $this->examId = $examType->id;
            $this->clientId = $examType->client_id;
            $this->name = $examType->name;
            $this->erpType = $examType->erp_type->value;
            $this->procedureCode = (string) $examType->procedure_code;
            $this->destinationFolder = $examType->destination_folder;
            $this->active = $examType->active;
            $this->printOptions = array_merge($this->printOptions, $examType->print_options ?? []);
            $this->sql = [
                'query' => $examType->sql_query, 'auth' => $examType->sql_auth, 'insert' => $examType->sql_insert,
                'image' => $examType->sql_image, 'update' => $examType->sql_update,
            ];
            $this->fields = $examType->fields->map(fn ($field) => [
                'kind' => $field->kind->value,
                'placeholder' => $field->placeholder,
                'db_column' => (string) $field->db_column,
                'label' => (string) $field->label,
                'input' => $field->input,
                'default_value' => (string) $field->default_value,
                'options' => $field->options,
                'required' => $field->required,
            ])->all();
            $this->serialRules = $examType->serialRules
                ->map(fn ($rule) => ['field' => $rule->field, 'pattern' => $rule->pattern])
                ->all();

            return;
        }

        abort_unless(auth()->user()->isAdmin(), 403);
    }

    #[Computed]
    public function exam(): ?ExamType
    {
        return $this->examId ? ExamType::with('client')->find($this->examId) : null;
    }

    /**
     * Histórico das versões do exame, da mais nova para a mais antiga (R-EXA-10).
     */
    #[Computed]
    public function versions()
    {
        return $this->examId
            ? $this->exam->versions()->with('user:id,name')->latest('version')->get(['id', 'exam_type_id', 'version', 'restored_from', 'user_id', 'created_at'])
            : collect();
    }

    #[Computed]
    public function clients()
    {
        return Client::orderBy('trade_name')->get(['id', 'trade_name']);
    }

    /**
     * Marcadores {X} dos 5 SQLs e se cada um tem leitura cadastrada.
     *
     * @return array<string, bool>
     */
    #[Computed]
    public function placeholders(): array
    {
        $exam = new ExamType([
            'sql_query' => $this->sql['query'], 'sql_auth' => $this->sql['auth'], 'sql_insert' => $this->sql['insert'],
            'sql_image' => $this->sql['image'], 'sql_update' => $this->sql['update'],
        ]);
        $configured = array_column($this->fields, 'placeholder');

        return collect($exam->placeholders())->mapWithKeys(fn ($key) => [$key => in_array($key, $configured, true)])->all();
    }

    public function addField(): void
    {
        $this->fields[] = [
            'kind' => ExamFieldKind::Internal->value, 'placeholder' => '', 'db_column' => '', 'label' => '',
            'input' => 'text', 'default_value' => '', 'options' => null, 'required' => false,
        ];
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    public function addSerialRule(): void
    {
        $this->serialRules[] = ['field' => '', 'pattern' => ''];
    }

    public function removeSerialRule(int $index): void
    {
        unset($this->serialRules[$index]);
        $this->serialRules = array_values($this->serialRules);
    }

    /**
     * Resultado de cada regra no conteúdo de teste. O painel testa com o PCRE no subconjunto do RE2 (R-EXA-12).
     *
     * @return list<array{field: string, value: ?string, error: ?string}>
     */
    #[Computed]
    public function serialResults(): array
    {
        if (trim($this->serialSample) === '') {
            return [];
        }

        return array_map(fn (array $rule) => [
            'field' => strtoupper(trim($rule['field'])),
            'value' => SerialPattern::extract($rule['pattern'], $this->serialSample),
            'error' => $rule['pattern'] === '' ? __('Regra vazia.') : SerialPattern::error($rule['pattern']),
        ], $this->serialRules);
    }

    /**
     * Salva o exame e as leituras numa versão nova. Só o administrador altera (R-EXA-3).
     */
    public function save(SaveExamType $save): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $validated = $this->validate([
            'clientId' => ['required', 'integer', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('exam_types', 'name')->where('client_id', $this->clientId)->ignore($this->examId)],
            'erpType' => ['required', Rule::enum(ErpType::class)],
            'procedureCode' => ['nullable', 'string', 'max:50'],
            'destinationFolder' => ['required', 'string', 'max:255'],
            'active' => ['boolean'],
            'sql.*' => ['required', 'string'],
            'fields.*.kind' => ['required', Rule::enum(ExamFieldKind::class)],
            'fields.*.placeholder' => ['required', 'string', 'max:60', 'distinct'],
            'fields.*.db_column' => ['nullable', 'string', 'max:60'],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.input' => ['required', Rule::in(['text', 'number', 'date', 'select', 'textarea'])],
            'fields.*.default_value' => ['nullable', 'string', 'max:255'],
            'fields.*.required' => ['boolean'],
            'serialRules' => ['array', 'max:50'],
            'serialRules.*.field' => ['required', 'string', 'regex:/^[A-Za-z0-9_]{1,60}$/', 'distinct:ignore_case'],
            'serialRules.*.pattern' => ['required', 'string', 'max:1000', new ValidSerialPattern],
        ], [
            'name.unique' => __('O cliente já tem um exame com este nome.'),
            'fields.*.placeholder.distinct' => __('Cada marcador aparece uma vez.'),
            'serialRules.*.field.regex' => __('O campo usa letras, números e "_", com até 60 caracteres.'),
            'serialRules.*.field.distinct' => __('Cada campo da porta serial aparece uma vez.'),
        ], [
            'serialRules.*.field' => __('campo'),
            'serialRules.*.pattern' => __('regra'),
            'clientId' => __('cliente'),
            'name' => __('nome'),
            'destinationFolder' => __('pasta de destino'),
            'sql.query' => __('SQL de consulta'),
            'sql.auth' => __('SQL de autenticação'),
            'sql.insert' => __('SQL de gravação'),
            'sql.image' => __('SQL de imagem'),
            'sql.update' => __('SQL de atualização'),
            'fields.*.placeholder' => __('marcador'),
        ]);

        $exam = $save->handle(
            $this->examId ? ExamType::findOrFail($this->examId) : new ExamType,
            [
                'client_id' => $validated['clientId'],
                'name' => $validated['name'],
                'erp_type' => $validated['erpType'],
                'procedure_code' => $validated['procedureCode'] ?: null,
                'destination_folder' => $validated['destinationFolder'],
                'active' => $validated['active'],
                'print_options' => array_map('boolval', $this->printOptions),
                'sql_query' => $this->sql['query'],
                'sql_auth' => $this->sql['auth'],
                'sql_insert' => $this->sql['insert'],
                'sql_image' => $this->sql['image'],
                'sql_update' => $this->sql['update'],
            ],
            array_map(fn (array $field) => [
                'kind' => $field['kind'],
                'placeholder' => strtoupper(trim($field['placeholder'])),
                'db_column' => $field['db_column'] ?: null,
                'label' => $field['label'] ?: null,
                'input' => $field['input'],
                'default_value' => $field['default_value'] !== '' ? $field['default_value'] : null,
                'options' => $field['options'] ?? null,
                'required' => (bool) $field['required'],
            ], array_values($this->fields)),
            array_map(fn (array $rule) => [
                'field' => strtoupper(trim($rule['field'])),
                'pattern' => $rule['pattern'],
            ], array_values($this->serialRules)),
        );

        Flux::toast(variant: 'success', text: __('Exame salvo na versão :version.', ['version' => $exam->version]));

        $this->redirectRoute('staff.exams.edit', $exam, navigate: true);
    }

    public function render()
    {
        return $this->view()->title($this->examId ? $this->name : __('Novo exame'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('staff.exams.index')" wire:navigate>{{ __('Exames') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $examId ? $name : __('Novo') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <flux:heading size="xl" level="1" class="mt-2">{{ $examId ? $name : __('Novo exame') }}</flux:heading>
            @if ($this->exam)
                <flux:text class="mt-1">{{ __('Versão :version · :client', ['version' => $this->exam->version, 'client' => $this->exam->client->trade_name]) }}</flux:text>
            @endif
        </div>
    </div>

    @unless (auth()->user()->isAdmin())
        <flux:callout icon="lock-closed" variant="secondary">{{ __('Só o administrador altera exames e SQLs. Você vê o cadastro só para consulta.') }}</flux:callout>
    @endunless

    <form wire:submit="save" class="flex flex-col gap-6">
        <fieldset @disabled(! auth()->user()->isAdmin()) class="flex flex-col gap-6">
            <flux:card class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                <flux:select wire:model="clientId" :label="__('Cliente')" :placeholder="__('Escolha o cliente')" :disabled="(bool) $examId">
                    @foreach ($this->clients as $client)
                        <flux:select.option :value="$client->id">{{ $client->trade_name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" :label="__('Nome')" />
                <flux:select wire:model="erpType" :label="__('Tipo do ERP')">
                    <flux:select.option value="TASY_HTML5">TASY HTML5</flux:select.option>
                    <flux:select.option value="DELPHI">TASY Delphi</flux:select.option>
                    <flux:select.option value="JAVA">TASY Java</flux:select.option>
                </flux:select>
                <flux:input wire:model="procedureCode" :label="__('Código do procedimento')" class="font-mono" />
                <div class="sm:col-span-2">
                    <flux:input wire:model="destinationFolder" :label="__('Pasta de destino')" placeholder="\\servidor\yantra\ECG" class="font-mono" />
                </div>
                <div class="flex flex-col gap-3 sm:col-span-2 xl:col-span-3">
                    <flux:text>{{ __('Arquivo final: <pasta>\<PACIENTE>-<NASCIMENTO>\aaaa\mm\dd\<Exame>-<ATENDIMENTO>-<n>.pdf') }}</flux:text>
                    <div class="flex flex-wrap gap-6">
                        <flux:switch wire:model="active" :label="__('Exame ativo')" />
                        <flux:checkbox wire:model="printOptions.print" :label="__('Imprimir em papel')" />
                        <flux:checkbox wire:model="printOptions.print_one_page" :label="__('Imprimir só uma página')" />
                        <flux:checkbox wire:model="printOptions.complete_after_file" :label="__('Concluir após gerar o arquivo')" />
                    </div>
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="lg">{{ __('SQLs no ERP') }}</flux:heading>
                    <flux:text>{{ __('O agente troca cada marcador {X} por uma variável de ligação (bind).') }}</flux:text>
                </div>
                <flux:navbar class="border-b border-zinc-200 dark:border-zinc-700">
                    @foreach (['query' => __('1 · Consulta'), 'auth' => __('2 · Autenticação'), 'insert' => __('3 · Gravação'), 'image' => __('4 · Imagem'), 'update' => __('5 · Atualização')] as $key => $label)
                        <flux:navbar.item wire:click="$set('sqlTab', '{{ $key }}')" :current="$sqlTab === $key">{{ $label }}</flux:navbar.item>
                    @endforeach
                </flux:navbar>
                <div class="grid gap-4 xl:grid-cols-[1fr_16rem]">
                    <flux:textarea wire:model.blur="sql.{{ $sqlTab }}" rows="14" class="font-mono text-xs" />
                    <div class="flex flex-col gap-2 text-sm">
                        <flux:heading>{{ __('Marcadores encontrados') }}</flux:heading>
                        @forelse ($this->placeholders as $placeholder => $configured)
                            <div class="flex items-center justify-between gap-2" wire:key="ph-{{ $placeholder }}">
                                <code class="font-mono text-xs">{{ '{'.$placeholder.'}' }}</code>
                                <span class="text-xs {{ $configured ? 'text-green-700 dark:text-green-400' : 'text-zinc-500' }}">{{ $configured ? __('leitura') : __('do paciente ou do ERP') }}</span>
                            </div>
                        @empty
                            <flux:text>{{ __('Nenhum marcador.') }}</flux:text>
                        @endforelse
                    </div>
                </div>
                @error('sql.*') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <flux:heading size="lg">{{ __('Leituras') }}</flux:heading>
                        <flux:text>{{ __('Parâmetro interno: valor fixo do exame. Parâmetro e Filtro: o operador informa antes da consulta. Informação complementar: o operador informa na confirmação.') }}</flux:text>
                    </div>
                    <flux:button size="sm" icon="plus" wire:click="addField">{{ __('Adicionar leitura') }}</flux:button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[56rem] text-sm">
                        <thead>
                            <tr class="text-start text-xs text-zinc-500">
                                <th class="py-2 pe-2 text-start font-medium">{{ __('Tipo') }}</th>
                                <th class="py-2 pe-2 text-start font-medium">{{ __('Marcador') }}</th>
                                <th class="py-2 pe-2 text-start font-medium">{{ __('Rótulo') }}</th>
                                <th class="py-2 pe-2 text-start font-medium">{{ __('Coluna (CAMPOBD)') }}</th>
                                <th class="py-2 pe-2 text-start font-medium">{{ __('Entrada') }}</th>
                                <th class="py-2 pe-2 text-start font-medium">{{ __('Valor padrão') }}</th>
                                <th class="py-2 pe-2 text-start font-medium">{{ __('Obrigatório') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fields as $index => $field)
                                <tr class="border-t border-zinc-100 align-top dark:border-zinc-700" wire:key="field-{{ $index }}">
                                    <td class="py-2 pe-2">
                                        <flux:select wire:model="fields.{{ $index }}.kind" size="sm">
                                            @foreach (App\Enums\ExamFieldKind::cases() as $kind)
                                                <flux:select.option :value="$kind->value">{{ $kind->label() }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </td>
                                    <td class="py-2 pe-2"><flux:input wire:model="fields.{{ $index }}.placeholder" size="sm" class="font-mono" /></td>
                                    <td class="py-2 pe-2"><flux:input wire:model="fields.{{ $index }}.label" size="sm" /></td>
                                    <td class="py-2 pe-2"><flux:input wire:model="fields.{{ $index }}.db_column" size="sm" class="font-mono" /></td>
                                    <td class="py-2 pe-2">
                                        <flux:select wire:model="fields.{{ $index }}.input" size="sm">
                                            @foreach (['text' => __('texto'), 'number' => __('número'), 'date' => __('data'), 'select' => __('lista'), 'textarea' => __('texto longo')] as $value => $label)
                                                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </td>
                                    <td class="py-2 pe-2"><flux:input wire:model="fields.{{ $index }}.default_value" size="sm" /></td>
                                    <td class="py-2 pe-2"><flux:checkbox wire:model="fields.{{ $index }}.required" /></td>
                                    <td class="py-2"><flux:button size="sm" variant="ghost" icon="trash" wire:click="removeField({{ $index }})" :aria-label="__('Remover leitura')" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('fields.*') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <flux:heading size="lg">{{ __('Regras da porta serial') }}</flux:heading>
                        <flux:text>{{ __('Sistema YO HUB. O agente aplica cada regra ao conteúdo da porta serial. O valor é o primeiro grupo entre parênteses ou, sem grupo, o trecho inteiro. Use a sintaxe do RE2: sem referência a grupo, lookahead ou lookbehind.') }}</flux:text>
                    </div>
                    <flux:button size="sm" icon="plus" wire:click="addSerialRule">{{ __('Adicionar regra') }}</flux:button>
                </div>

                @forelse ($serialRules as $index => $rule)
                    <div class="grid gap-2 sm:grid-cols-[12rem_1fr_auto]" wire:key="serial-{{ $index }}">
                        <flux:input wire:model="serialRules.{{ $index }}.field" size="sm" class="font-mono" :placeholder="__('Campo, ex.: FC')" :aria-label="__('Campo')" />
                        <flux:input wire:model.blur="serialRules.{{ $index }}.pattern" size="sm" class="font-mono" placeholder="FC:\s*(\d+)" :aria-label="__('Regra')" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeSerialRule({{ $index }})" :aria-label="__('Remover regra')" />
                        <flux:error name="serialRules.{{ $index }}.field" />
                        <flux:error name="serialRules.{{ $index }}.pattern" />
                    </div>
                @empty
                    <flux:text>{{ __('Nenhuma regra. Os exames sem porta serial não precisam de regras.') }}</flux:text>
                @endforelse
            </flux:card>
        </fieldset>

        @if (auth()->user()->isAdmin())
            <div class="flex justify-end gap-2">
                <flux:button :href="route('staff.exams.index')" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $examId ? __('Salvar versão :version', ['version' => ($this->exam?->version ?? 0) + 1]) : __('Salvar') }}</flux:button>
            </div>
        @endif
    </form>

    @if ($serialRules)
        <flux:card class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ __('Testar as regras da porta serial') }}</flux:heading>
                <flux:text>{{ __('Cole um conteúdo recebido do equipamento. O painel mostra o valor de cada regra. O teste não grava nada.') }}</flux:text>
            </div>
            <flux:textarea wire:model.live.debounce.500ms="serialSample" rows="6" class="font-mono text-xs" :placeholder="__('Conteúdo recebido da porta serial')" />

            @if ($this->serialResults)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Campo') }}</flux:table.column>
                        <flux:table.column>{{ __('Valor extraído') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->serialResults as $result)
                            <flux:table.row>
                                <flux:table.cell class="font-mono text-xs">{{ $result['field'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($result['error'])
                                        <span class="text-red-700 dark:text-red-400">{{ $result['error'] }}</span>
                                    @elseif ($result['value'] === null)
                                        <span class="text-amber-700 dark:text-amber-400">{{ __('Não encontrado') }}</span>
                                    @else
                                        <span class="font-mono">{{ $result['value'] }}</span>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    @if ($examId)
        <flux:card class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ __('Versões') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Cada gravação cria uma versão. O histórico nunca perde versões.') }}</flux:text>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Versão') }}</flux:table.column>
                    <flux:table.column>{{ __('Data') }}</flux:table.column>
                    <flux:table.column>{{ __('Usuário') }}</flux:table.column>
                    <flux:table.column>{{ __('Origem') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->versions as $version)
                        <flux:table.row :key="$version->id">
                            <flux:table.cell class="font-mono text-xs">
                                v{{ $version->version }}
                                @if ($version->version === $this->exam->version)
                                    <flux:badge size="sm" color="green" class="ms-1">{{ __('Atual') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="tabular-nums">{{ $version->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                            <flux:table.cell>{{ $version->user?->name ?? __('Sistema') }}</flux:table.cell>
                            <flux:table.cell>{{ $version->restored_from ? __('Restaurada da versão :version', ['version' => $version->restored_from]) : __('Gravação') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" :href="route('staff.exams.versions.show', [$this->exam, $version->version])" wire:navigate>{{ __('Ver diferenças') }}</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">{{ __('Nenhuma versão no histórico.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
