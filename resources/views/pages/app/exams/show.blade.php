<?php

use App\Actions\Exams\ExamCycle;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\ExamFieldKind;
use App\Enums\ExamStatus;
use App\Exceptions\ExamCycleException;
use App\Models\AgentCommand;
use App\Models\Exam;
use App\Models\ExamFile;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $examId;

    public ?string $previewFileId = null;

    /** @var list<string> */
    public array $selectedFileIds = [];

    /** @var array<string, string> */
    public array $queryValues = [];

    /** @var array<string, string> */
    public array $infoValues = [];

    public ?int $patientIndex = null;

    public string $tasyUsername = '';

    public string $tasyPassword = '';

    public function mount(Exam $exam): void
    {
        abort_unless(Exam::visibleTo(auth()->user())->whereKey($exam->id)->exists(), 404);

        $this->examId = $exam->id;

        foreach ($exam->examType->fields as $field) {
            if (in_array($field->kind, [ExamFieldKind::Param, ExamFieldKind::Filter], true)) {
                $this->queryValues[$field->placeholder] = (string) $field->default_value;
            }

            if ($field->kind === ExamFieldKind::Info) {
                $this->infoValues[$field->placeholder] = (string) ($exam->complementary_info[$field->placeholder] ?? $field->default_value);
            }
        }
    }

    #[Computed]
    public function exam(): Exam
    {
        return Exam::with(['examType.fields', 'device:id,hostname,last_seen_at', 'files' => fn ($query) => $query->oldest()])
            ->findOrFail($this->examId);
    }

    #[Computed]
    public function patientQuery(): ?AgentCommand
    {
        return $this->exam->commands()->where('type', CommandType::QueryPatients)->latest()->first();
    }

    #[Computed]
    public function recordCommand(): ?AgentCommand
    {
        return $this->exam->commands()->where('type', CommandType::RecordReport)->latest()->first();
    }

    /**
     * Linhas devolvidas pela consulta de pacientes no ERP.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function patients(): array
    {
        $command = $this->patientQuery;

        return $command?->status === CommandStatus::Done ? array_values($command->result['data']['rows'] ?? []) : [];
    }

    public function previewUrl(ExamFile $file): ?string
    {
        return $file->preview_path ? Storage::disk('s3')->temporaryUrl($file->preview_path, now()->addMinutes(10)) : null;
    }

    public function refresh(): void
    {
        unset($this->exam, $this->patientQuery, $this->recordCommand, $this->patients);
    }

    public function queryPatients(ExamCycle $cycle): void
    {
        try {
            $cycle->queryPatients($this->exam, $this->queryValues);
        } catch (ExamCycleException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->patientIndex = null;
        $this->refresh();
    }

    /**
     * Pede a gravação dos laudos. A senha do Tasy não fica no painel (R-EXA-5).
     */
    public function record(ExamCycle $cycle): void
    {
        $required = $this->exam->examType->fields
            ->where('kind', ExamFieldKind::Info)
            ->where('required', true)
            ->mapWithKeys(fn ($field) => ["infoValues.{$field->placeholder}" => ['required', 'string', 'max:2000']])
            ->all();

        $this->validate([
            'patientIndex' => ['required', 'integer', 'min:0', 'max:'.max(0, count($this->patients) - 1)],
            'selectedFileIds' => ['required', 'array', 'min:1'],
            'tasyUsername' => ['required', 'string', 'max:255'],
            'tasyPassword' => ['required', 'string', 'max:255'],
            ...$required,
        ], [
            'patientIndex.required' => __('Escolha o paciente.'),
            'selectedFileIds.required' => __('Escolha pelo menos um arquivo para o laudo.'),
        ], [
            'tasyUsername' => __('usuário do Tasy'),
            'tasyPassword' => __('senha do Tasy'),
        ]);

        try {
            $cycle->record(
                $this->exam,
                $this->patients[$this->patientIndex],
                $this->selectedFileIds,
                array_filter($this->infoValues, fn ($value) => $value !== ''),
                $this->tasyUsername,
                $this->tasyPassword,
            );
        } catch (ExamCycleException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        } finally {
            $this->reset('tasyPassword');
        }

        $this->refresh();
        Flux::toast(text: __('Gravação enviada ao agente.'));
    }

    public function completeWithRecordedReports(ExamCycle $cycle): void
    {
        try {
            $cycle->completeWithRecordedReports($this->exam);
        } catch (ExamCycleException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->refresh();
    }

    public function cancel(ExamCycle $cycle): void
    {
        try {
            $cycle->cancel($this->exam);
        } catch (ExamCycleException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->refresh();
        Flux::toast(text: __('Exame cancelado.'));
    }

    public function render()
    {
        return $this->view()->title($this->exam->examType->name);
    }
}; ?>

@php
    $exam = $this->exam;
    $open = $exam->status->isOpen();
    $files = $exam->files;
    $preview = $files->firstWhere('id', $previewFileId) ?? $files->firstWhere('preview_path', '!=', null);
    $statusLabel = match ($exam->status) {
        ExamStatus::Starting => __('Aguardando o agente'),
        ExamStatus::Capturing => __('Capturando'),
        ExamStatus::Reviewing => __('Em conferência'),
        ExamStatus::Recording => __('Gravando no Tasy'),
        ExamStatus::Completed => __('Concluído'),
        ExamStatus::Failed => __('Falhou'),
        ExamStatus::Cancelled => __('Cancelado'),
    };
@endphp

<div class="flex flex-col gap-6" @if ($open) wire:poll.5s="refresh" @endif>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <flux:heading size="xl" level="1">{{ $exam->examType->name }}</flux:heading>
                <flux:badge size="sm" :color="match ($exam->status) { ExamStatus::Completed => 'green', ExamStatus::Failed, ExamStatus::Cancelled => 'zinc', ExamStatus::Recording => 'amber', default => 'blue' }">{{ $statusLabel }}</flux:badge>
            </div>
            <flux:text class="mt-1">{{ __('Equipamento :device · iniciado às :time', ['device' => $exam->device->hostname, 'time' => $exam->started_at->format('H:i')]) }}</flux:text>
        </div>
        @if ($open)
            <flux:button variant="ghost" wire:click="cancel" wire:confirm="{{ __('Cancelar este exame? Os arquivos do painel serão apagados.') }}">{{ __('Cancelar exame') }}</flux:button>
        @endif
    </div>

    @if ($exam->status === ExamStatus::Starting)
        <flux:callout icon="clock">{{ __('Aguardando o agente do equipamento abrir o exame. O agente consulta o painel a cada poucos segundos.') }}</flux:callout>
    @endif

    @if ($open)
        <flux:callout icon="information-circle" variant="secondary">{{ __('Preview temporário. O painel apaga os arquivos quando o exame termina. Os originais ficam no equipamento até a cópia para o file server.') }}</flux:callout>
    @endif

    <div class="grid gap-6 xl:grid-cols-[3fr_2fr]">
        {{-- Arquivos e preview --}}
        <flux:card class="flex min-w-0 flex-col gap-4">
            <flux:heading size="lg">{{ __('Arquivos do exame') }}</flux:heading>

            @if ($files->isEmpty())
                <flux:text>{{ __('Nenhum arquivo ainda. Imprima na Yantra Printer do equipamento.') }}</flux:text>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($files as $file)
                        <div wire:key="file-{{ $file->id }}" @class([
                            'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm',
                            'border-zinc-800 dark:border-white' => $preview?->id === $file->id,
                            'border-zinc-200 dark:border-zinc-700' => $preview?->id !== $file->id,
                        ])>
                            @if ($open)
                                <input type="checkbox" wire:model="selectedFileIds" value="{{ $file->id }}" aria-label="{{ __('Usar no laudo') }}" class="accent-zinc-800">
                            @endif
                            <button type="button" wire:click="$set('previewFileId', '{{ $file->id }}')" class="text-start">
                                <span class="block font-medium">{{ $file->original_name }}</span>
                                <span class="block text-xs text-zinc-500">
                                    {{ strtoupper($file->kind->value) }} · {{ Illuminate\Support\Number::fileSize($file->size_bytes) }}
                                    @if ($file->erp_report_id) · {{ __('laudo :id', ['id' => $file->erp_report_id]) }} @endif
                                </span>
                            </button>
                        </div>
                    @endforeach
                </div>
                <flux:error name="selectedFileIds" />

                @if ($preview && $url = $this->previewUrl($preview))
                    <div class="overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-900">
                        @if ($preview->kind === App\Enums\ExamFileKind::Png)
                            <img src="{{ $url }}" alt="{{ $preview->original_name }}" class="mx-auto max-h-[70vh]">
                        @else
                            <iframe src="{{ $url }}" title="{{ $preview->original_name }}" class="h-[70vh] w-full"></iframe>
                        @endif
                    </div>
                @elseif (! $open)
                    <flux:text>{{ __('O painel já apagou os previews deste exame.') }}</flux:text>
                @endif
            @endif
        </flux:card>

        <div class="flex min-w-0 flex-col gap-6">
            {{-- Paciente --}}
            @if ($open || $exam->patient)
                <flux:card class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ __('Paciente') }}</flux:heading>

                    @if ($open)
                        <form wire:submit="queryPatients" class="flex flex-col gap-3">
                            @foreach ($exam->examType->fields->whereIn('kind', [ExamFieldKind::Param, ExamFieldKind::Filter]) as $field)
                                <flux:input wire:model="queryValues.{{ $field->placeholder }}" :label="$field->label ?? $field->placeholder" :type="$field->input === 'date' ? 'date' : 'text'" wire:key="q-{{ $field->placeholder }}" />
                            @endforeach
                            <flux:button type="submit" variant="primary">{{ __('Consultar no Tasy') }}</flux:button>
                        </form>

                        @if ($this->patientQuery)
                            @php($query = $this->patientQuery)
                            @if (in_array($query->status, [CommandStatus::Pending, CommandStatus::Delivered], true))
                                <flux:text>{{ __('Consultando o Tasy pelo agente…') }}</flux:text>
                            @elseif ($query->status === CommandStatus::Failed)
                                <flux:callout color="red" icon="exclamation-triangle">{{ $query->result['error'] ?? __('A consulta falhou.') }}</flux:callout>
                            @elseif ($query->status === CommandStatus::Expired)
                                <flux:callout color="amber" icon="exclamation-triangle">{{ __('O agente não respondeu. Confira se o equipamento está ligado e tente de novo.') }}</flux:callout>
                            @elseif (empty($this->patients))
                                <flux:text>{{ __('Nenhum pedido pendente encontrado.') }}</flux:text>
                            @else
                                @php($columns = $query->result['data']['columns'] ?? array_keys($this->patients[0]))
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-start text-xs text-zinc-500">
                                                <th class="py-1 pe-2"><span class="sr-only">{{ __('Escolher') }}</span></th>
                                                @foreach ($columns as $column)
                                                    <th class="py-1 pe-3 text-start font-medium">{{ $column }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($this->patients as $index => $row)
                                                <tr class="border-t border-zinc-100 dark:border-zinc-700 {{ $patientIndex === $index ? 'bg-zinc-100 dark:bg-zinc-700' : '' }}" wire:key="patient-{{ $index }}">
                                                    <td class="py-1 pe-2"><input type="radio" wire:model.live="patientIndex" value="{{ $index }}" aria-label="{{ __('Escolher este paciente') }}" class="accent-zinc-800"></td>
                                                    @foreach ($columns as $column)
                                                        <td class="py-1 pe-3">{{ $row[$column] ?? '' }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <flux:error name="patientIndex" />
                            @endif
                        @endif
                    @else
                        <dl class="grid gap-1 text-sm">
                            @foreach ($exam->patient as $key => $value)
                                <div class="flex gap-2"><dt class="text-zinc-500">{{ $key }}</dt><dd>{{ $value }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                </flux:card>
            @endif

            {{-- Informações complementares e gravação --}}
            @if ($open && $exam->status !== ExamStatus::Recording)
                <form wire:submit="record" class="flex flex-col gap-6">
                    @php($infoFields = $exam->examType->fields->where('kind', ExamFieldKind::Info))
                    @if ($infoFields->isNotEmpty())
                        <flux:card class="flex flex-col gap-3">
                            <flux:heading size="lg">{{ __('Informações complementares') }}</flux:heading>
                            @foreach ($infoFields as $field)
                                @if ($field->input === 'select')
                                    <flux:select wire:model="infoValues.{{ $field->placeholder }}" :label="$field->label" :placeholder="__('Escolha')" wire:key="i-{{ $field->placeholder }}">
                                        @foreach ($field->options ?? [] as $option)
                                            <flux:select.option :value="$option['id'] ?? $option">{{ $option['valor'] ?? $option }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:textarea wire:model="infoValues.{{ $field->placeholder }}" :label="$field->label" rows="2" wire:key="i-{{ $field->placeholder }}" />
                                @endif
                            @endforeach
                        </flux:card>
                    @endif

                    <flux:card class="flex flex-col gap-3">
                        <flux:heading size="lg">{{ __('Gravar laudo no Tasy') }}</flux:heading>
                        <flux:text>{{ __('O agente valida o usuário e grava um laudo por PDF escolhido.') }}</flux:text>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <flux:input wire:model="tasyUsername" :label="__('Usuário do Tasy')" autocomplete="username" />
                            <flux:input wire:model="tasyPassword" type="password" :label="__('Senha do Tasy')" autocomplete="current-password" />
                        </div>
                        <flux:text class="text-xs">{{ __('O painel não guarda a senha. A senha vai só no comando ao agente e expira em 2 minutos.') }}</flux:text>
                        <flux:button type="submit" variant="primary">{{ __('Gravar laudo') }}</flux:button>
                    </flux:card>
                </form>
            @endif

            {{-- Resultado da gravação --}}
            @if ($this->recordCommand)
                @php($record = $this->recordCommand)
                <flux:card class="flex flex-col gap-3">
                    <flux:heading size="lg">{{ __('Resultado da gravação') }}</flux:heading>
                    @if (in_array($record->status, [CommandStatus::Pending, CommandStatus::Delivered], true))
                        <flux:text>{{ __('Gravando no Tasy pelo agente…') }}</flux:text>
                    @elseif ($record->status === CommandStatus::Expired)
                        <flux:callout color="amber" icon="exclamation-triangle">{{ __('O agente não respondeu a tempo. Confira o equipamento e grave de novo.') }}</flux:callout>
                    @else
                        @if ($record->result['error'] ?? null)
                            <flux:callout color="red" icon="exclamation-triangle">{{ $record->result['error'] }}</flux:callout>
                        @endif
                        @foreach ($record->result['data']['reports'] ?? [] as $report)
                            @php($reportFile = $files->firstWhere('id', $report['file_id'] ?? null))
                            <div class="rounded-lg border p-3 text-sm {{ empty($report['error']) ? 'border-green-200 dark:border-green-900' : 'border-red-300 dark:border-red-900' }}" wire:key="report-{{ $loop->index }}">
                                <div class="font-medium">{{ $reportFile?->original_name ?? $report['file_id'] ?? '' }}</div>
                                @if (empty($report['error']))
                                    <div class="text-green-700 dark:text-green-400">{{ __('Laudo :id gravado.', ['id' => $report['erp_report_id'] ?? '']) }}</div>
                                    <div class="break-all font-mono text-xs text-zinc-500">{{ $report['final_path'] ?? '' }}</div>
                                @else
                                    <div class="text-red-700 dark:text-red-400">{{ $report['error'] }}</div>
                                @endif
                            </div>
                        @endforeach

                        @if ($open && $files->whereNotNull('erp_report_id')->isNotEmpty())
                            <flux:button wire:click="completeWithRecordedReports" wire:confirm="{{ __('Concluir o exame sem os laudos com erro?') }}">{{ __('Concluir sem o laudo com erro') }}</flux:button>
                        @endif
                    @endif
                </flux:card>
            @endif
        </div>
    </div>
</div>
