<?php

use App\Actions\Exams\RestoreExamTypeVersion;
use App\Models\ExamType;
use App\Models\ExamTypeVersion;
use App\Support\LineDiff;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $examTypeId;

    #[Locked]
    public int $version;

    /**
     * Rótulos dos atributos do exame na tela de diferenças.
     */
    private const LABELS = [
        'name' => 'Nome',
        'erp_type' => 'Tipo do ERP',
        'procedure_code' => 'Código do procedimento',
        'destination_folder' => 'Pasta de destino',
        'active' => 'Ativo',
        'print_options' => 'Opções de impressão',
    ];

    private const SQL_LABELS = [
        'sql_query' => 'SQL de consulta',
        'sql_auth' => 'SQL de autenticação',
        'sql_insert' => 'SQL de gravação',
        'sql_image' => 'SQL de imagem',
        'sql_update' => 'SQL de atualização',
    ];

    public function mount(ExamType $examType, int $version): void
    {
        abort_unless(auth()->user()->isStaff(), 403);

        $this->examTypeId = $examType->id;
        $this->version = $version;

        abort_unless($this->current !== null, 404);
    }

    #[Computed]
    public function exam(): ExamType
    {
        return ExamType::with('client')->findOrFail($this->examTypeId);
    }

    #[Computed]
    public function current(): ?ExamTypeVersion
    {
        return ExamTypeVersion::with('user:id,name')->where('exam_type_id', $this->examTypeId)->where('version', $this->version)->first();
    }

    /**
     * Versão anterior que existe no histórico. A primeira versão compara com um exame vazio.
     */
    #[Computed]
    public function previous(): ?ExamTypeVersion
    {
        return ExamTypeVersion::where('exam_type_id', $this->examTypeId)->where('version', '<', $this->version)->latest('version')->first();
    }

    /**
     * @return list<array{label: string, old: string, new: string}>
     */
    #[Computed]
    public function attributeChanges(): array
    {
        $old = $this->previous?->snapshot['attributes'] ?? [];
        $new = $this->current->snapshot['attributes'];
        $changes = [];

        foreach (self::LABELS as $key => $label) {
            if (($old[$key] ?? null) != ($new[$key] ?? null)) {
                $changes[] = ['label' => __($label), 'old' => $this->format($old[$key] ?? null), 'new' => $this->format($new[$key] ?? null)];
            }
        }

        return $changes;
    }

    /**
     * @return list<array{label: string, lines: list<array<string, mixed>>, removed: int, added: int}>
     */
    #[Computed]
    public function sqlChanges(): array
    {
        $old = $this->previous?->snapshot['attributes'] ?? [];
        $new = $this->current->snapshot['attributes'];
        $changes = [];

        foreach (self::SQL_LABELS as $key => $label) {
            if (($old[$key] ?? '') === ($new[$key] ?? '')) {
                continue;
            }

            $lines = LineDiff::compare((string) ($old[$key] ?? ''), (string) ($new[$key] ?? ''));
            $types = array_count_values(array_column($lines, 'type'));
            $changes[] = ['label' => __($label), 'lines' => $lines, 'removed' => $types['removed'] ?? 0, 'added' => $types['added'] ?? 0];
        }

        return $changes;
    }

    /**
     * Leituras novas, removidas e alteradas, pelo marcador.
     *
     * @return list<array{change: string, field: array<string, mixed>}>
     */
    #[Computed]
    public function fieldChanges(): array
    {
        $old = collect($this->previous?->snapshot['fields'] ?? [])->keyBy('placeholder');
        $new = collect($this->current->snapshot['fields'])->keyBy('placeholder');
        $changes = [];

        foreach ($new as $placeholder => $field) {
            if (! $old->has($placeholder)) {
                $changes[] = ['change' => 'added', 'field' => $field];
            } elseif ($old[$placeholder] != $field) {
                $changes[] = ['change' => 'changed', 'field' => $field];
            }
        }

        foreach ($old->diffKeys($new) as $field) {
            $changes[] = ['change' => 'removed', 'field' => $field];
        }

        return $changes;
    }

    /**
     * Restaura esta versão numa versão nova. Só o administrador altera exames (R-EXA-3, R-EXA-10).
     */
    public function restore(RestoreExamTypeVersion $restore): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($this->version === $this->exam->version, 409);

        $examType = $restore->handle($this->current);

        Flux::toast(variant: 'success', text: __('A versão :old foi restaurada na versão :new.', ['old' => $this->version, 'new' => $examType->version]));

        $this->redirectRoute('staff.exams.edit', $examType, navigate: true);
    }

    private function format(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? __('Sim') : __('Não'),
            is_array($value) => collect($value)->filter()->keys()->join(', ') ?: '—',
            default => (string) $value,
        };
    }

    public function render()
    {
        return $this->view()->title(__('Diferenças da versão :version', ['version' => $this->version]));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('staff.exams.index')" wire:navigate>{{ __('Exames') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('staff.exams.edit', $this->exam)" wire:navigate>{{ $this->exam->name }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>v{{ $this->previous?->version ?? 0 }} → v{{ $version }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <flux:heading size="xl" level="1" class="mt-2">{{ __('Diferenças da versão :version', ['version' => $version]) }}</flux:heading>
            <flux:text class="mt-1">
                {{ $this->exam->name }} · {{ $this->exam->client->trade_name }} ·
                {{ __('gravada por :user em :date', ['user' => $this->current->user?->name ?? __('Sistema'), 'date' => $this->current->created_at->format('d/m/Y H:i:s')]) }}
                @if ($this->current->restored_from)
                    · {{ __('restaurada da versão :version', ['version' => $this->current->restored_from]) }}
                @endif
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:button :href="route('staff.exams.edit', $this->exam)" wire:navigate>{{ __('Voltar ao exame') }}</flux:button>
            @if (auth()->user()->isAdmin() && $version !== $this->exam->version)
                <flux:button variant="primary" wire:click="restore" wire:confirm="{{ __('Restaurar a versão :old? O painel cria a versão :new com o conteúdo da versão :old.', ['old' => $version, 'new' => $this->exam->version + 1]) }}">
                    {{ __('Restaurar a versão :version', ['version' => $version]) }}
                </flux:button>
            @endif
        </div>
    </div>

    @error('version')
        <flux:callout variant="danger" icon="x-circle">{{ $message }}</flux:callout>
    @enderror

    @if ($version !== $this->exam->version)
        <flux:callout icon="information-circle" variant="secondary">
            {{ __('Restaurar a versão :old cria a versão :new com o conteúdo da versão :old. O histórico nunca perde versões.', ['old' => $version, 'new' => $this->exam->version + 1]) }}
        </flux:callout>
    @endif

    @if (! $this->attributeChanges && ! $this->sqlChanges && ! $this->fieldChanges)
        <flux:card><flux:text>{{ __('Esta versão tem o mesmo conteúdo da versão anterior.') }}</flux:text></flux:card>
    @endif

    @if ($this->attributeChanges)
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Dados do exame') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Campo') }}</flux:table.column>
                    <flux:table.column>{{ __('Versão :version', ['version' => $this->previous?->version ?? 0]) }}</flux:table.column>
                    <flux:table.column>{{ __('Versão :version', ['version' => $version]) }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->attributeChanges as $change)
                        <flux:table.row>
                            <flux:table.cell>{{ $change['label'] }}</flux:table.cell>
                            <flux:table.cell class="text-red-700 dark:text-red-400">{{ $change['old'] }}</flux:table.cell>
                            <flux:table.cell class="text-green-700 dark:text-green-400">{{ $change['new'] }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @foreach ($this->sqlChanges as $sql)
        <flux:card class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ $sql['label'] }}</flux:heading>
                <flux:text class="mt-1">{{ trans_choice(':count linha removida|:count linhas removidas', $sql['removed']) }}, {{ trans_choice(':count linha nova|:count linhas novas', $sql['added']) }}</flux:text>
            </div>
            <div class="overflow-x-auto rounded-lg border border-zinc-200 font-mono text-xs dark:border-zinc-700">
                <table class="w-full">
                    <tbody>
                        @foreach ($sql['lines'] as $line)
                            <tr @class([
                                'bg-red-50 text-red-900 dark:bg-red-950 dark:text-red-200' => $line['type'] === 'removed',
                                'bg-green-50 text-green-900 dark:bg-green-950 dark:text-green-200' => $line['type'] === 'added',
                            ])>
                                <td class="w-10 select-none px-2 text-end text-zinc-400">{{ $line['old'] }}</td>
                                <td class="w-10 select-none px-2 text-end text-zinc-400">{{ $line['new'] }}</td>
                                <td class="w-4 select-none px-1">{{ ['removed' => '−', 'added' => '+', 'same' => ''][$line['type']] }}</td>
                                <td class="whitespace-pre px-2">{{ $line['text'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endforeach

    @if ($this->fieldChanges)
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Leituras') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Mudança') }}</flux:table.column>
                    <flux:table.column>{{ __('Tipo') }}</flux:table.column>
                    <flux:table.column>{{ __('Marcador') }}</flux:table.column>
                    <flux:table.column>{{ __('Rótulo') }}</flux:table.column>
                    <flux:table.column>{{ __('Valor padrão') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->fieldChanges as $change)
                        <flux:table.row>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="['added' => 'green', 'removed' => 'red', 'changed' => 'amber'][$change['change']]">
                                    {{ ['added' => __('nova'), 'removed' => __('removida'), 'changed' => __('alterada')][$change['change']] }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ App\Enums\ExamFieldKind::tryFrom($change['field']['kind'])?->label() ?? $change['field']['kind'] }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $change['field']['placeholder'] }}</flux:table.cell>
                            <flux:table.cell>{{ $change['field']['label'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $change['field']['default_value'] ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
