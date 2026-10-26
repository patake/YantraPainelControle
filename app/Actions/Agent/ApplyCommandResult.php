<?php

namespace App\Actions\Agent;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\ExamStatus;
use App\Models\AgentCommand;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;

/**
 * Grava o resultado de um comando do agente e avança o ciclo de exame (E6).
 */
class ApplyCommandResult
{
    /**
     * @param  array{success: bool, error?: ?string, data?: ?array<string, mixed>}  $result
     */
    public function handle(AgentCommand $command, array $result): void
    {
        DB::transaction(function () use ($command, $result) {
            $command->update([
                'status' => $result['success'] ? CommandStatus::Done : CommandStatus::Failed,
                'result' => $result,
                'completed_at' => now(),
                // A senha do Tasy sai do banco assim que o agente responde (R-EXA-5).
                'payload' => $command->type === CommandType::RecordReport ? null : $command->payload,
            ]);

            $exam = $command->exam;

            if ($exam === null || ! $exam->status->isOpen()) {
                return;
            }

            match ($command->type) {
                CommandType::StartExam => $this->started($exam, $result),
                CommandType::RecordReport => $this->recorded($exam, $result),
                default => null,
            };
        });

        $command->exam?->refresh();

        if ($command->exam?->status === ExamStatus::Completed) {
            $command->exam->purgePreviews();
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function started(Exam $exam, array $result): void
    {
        $exam->update($result['success']
            ? ['status' => ExamStatus::Capturing, 'temp_folder' => $result['data']['temp_folder'] ?? null]
            : ['status' => ExamStatus::Failed, 'completed_at' => now()]);
    }

    /**
     * Grava o caminho final e o id do laudo de cada PDF. Com um laudo com erro, o exame
     * volta para a conferência: o operador tenta de novo ou conclui sem o laudo.
     *
     * @param  array<string, mixed>  $result
     */
    private function recorded(Exam $exam, array $result): void
    {
        foreach ($result['data']['reports'] ?? [] as $report) {
            if (empty($report['error']) && isset($report['file_id'])) {
                $exam->files()->whereKey($report['file_id'])->update([
                    'final_path' => $report['final_path'] ?? null,
                    'erp_report_id' => isset($report['erp_report_id']) ? (string) $report['erp_report_id'] : null,
                ]);
            }
        }

        $pending = $exam->files()->where('selected', true)->whereNull('erp_report_id')->count();

        $exam->update($result['success'] && $pending === 0
            ? ['status' => ExamStatus::Completed, 'completed_at' => now()]
            : ['status' => ExamStatus::Reviewing]);
    }
}
