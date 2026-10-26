<?php

use App\Enums\ExamStatus;
use App\Models\Exam;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Histórico')] class extends Component {
    use WithPagination;

    #[Url(as: 'data')]
    public string $date = '';

    #[Url(as: 'estado')]
    public string $status = '';

    #[Url(as: 'busca')]
    public string $search = '';

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    /**
     * Exames dos clientes do operador (R-ACE-2). A busca usa o paciente gravado no exame.
     */
    #[Computed]
    public function exams()
    {
        return Exam::visibleTo(auth()->user())
            ->with(['examType:id,name', 'device:id,hostname', 'operator:id,name'])
            ->withCount(['files as reports_count' => fn ($query) => $query->whereNotNull('erp_report_id')])
            ->when($this->date !== '', fn ($query) => $query->whereDate('started_at', $this->date))
            ->when(ExamStatus::tryFrom($this->status), fn ($query, ExamStatus $status) => $query->where('status', $status))
            ->when($this->search !== '', fn ($query) => $query->whereRaw('cast(patient as text) ilike ?', ['%'.$this->search.'%']))
            ->latest('started_at')
            ->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Histórico') }}</flux:heading>
        <flux:text class="mt-1">{{ __('O painel guarda os dados do exame, não os arquivos.') }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Buscar')" :placeholder="__('Paciente ou atendimento')" />
        <flux:input wire:model.live="date" type="date" :label="__('Data')" />
        <flux:select wire:model.live="status" :label="__('Estado')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            <flux:select.option value="completed">{{ __('Concluído') }}</flux:select.option>
            <flux:select.option value="reviewing">{{ __('Em conferência') }}</flux:select.option>
            <flux:select.option value="cancelled">{{ __('Cancelado') }}</flux:select.option>
            <flux:select.option value="failed">{{ __('Falhou') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->exams">
        <flux:table.columns>
            <flux:table.column>{{ __('Início') }}</flux:table.column>
            <flux:table.column>{{ __('Exame') }}</flux:table.column>
            <flux:table.column>{{ __('Paciente') }}</flux:table.column>
            <flux:table.column>{{ __('Equipamento') }}</flux:table.column>
            <flux:table.column>{{ __('Operador') }}</flux:table.column>
            <flux:table.column>{{ __('Laudos') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->exams as $exam)
                <flux:table.row :key="$exam->id">
                    <flux:table.cell class="tabular-nums">
                        <flux:link :href="route('app.exams.show', $exam)" wire:navigate>{{ $exam->started_at->format('d/m/Y H:i') }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $exam->examType->name }}</flux:table.cell>
                    <flux:table.cell>{{ $exam->patient['PACIENTE'] ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $exam->device->hostname }}</flux:table.cell>
                    <flux:table.cell>{{ $exam->operator->name }}</flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $exam->reports_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($exam->status) { ExamStatus::Completed => 'green', ExamStatus::Failed, ExamStatus::Cancelled => 'zinc', default => 'blue' }">{{ __($exam->status->value) }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('Nenhum exame encontrado.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
