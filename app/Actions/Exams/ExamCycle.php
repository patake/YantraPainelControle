<?php

namespace App\Actions\Exams;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\DeviceStatus;
use App\Enums\ExamFieldKind;
use App\Enums\ExamStatus;
use App\Exceptions\ExamCycleException;
use App\Models\AgentCommand;
use App\Models\Device;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Ações do operador no ciclo de exame (E6). Cada ação cria o comando para o agente.
 */
class ExamCycle
{
    /**
     * Inicia o exame num equipamento (R-EXA-1 e R-EXA-2).
     */
    public function start(User $operator, Device $device, ExamType $examType): Exam
    {
        if (! in_array($device->status, DeviceStatus::usingLicense(), true)) {
            throw new ExamCycleException(__('A licença deste equipamento não está ativa.'));
        }

        if (! $device->examTypes()->whereKey($examType->id)->where('active', true)->exists()) {
            throw new ExamCycleException(__('Este exame não está habilitado no equipamento.'));
        }

        try {
            return DB::transaction(function () use ($operator, $device, $examType) {
                $exam = Exam::create([
                    'client_id' => $device->client_id,
                    'device_id' => $device->id,
                    'exam_type_id' => $examType->id,
                    'operator_id' => $operator->id,
                    'status' => ExamStatus::Starting,
                    'started_at' => now(),
                ]);

                $this->command($exam, CommandType::StartExam, [
                    'exam_type_id' => $examType->id,
                    'exam_name' => $examType->name,
                ]);

                return $exam;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ExamCycleException(__('O equipamento já tem um exame aberto. Conclua ou cancele o exame antes.'));
        }
    }

    /**
     * Pede ao agente a consulta de pacientes no ERP, com os Parâmetros e Filtros do operador.
     *
     * @param  array<string, string>  $values
     */
    public function queryPatients(Exam $exam, array $values): AgentCommand
    {
        $this->ensureOpen($exam);

        $allowed = $exam->examType->fields
            ->whereIn('kind', [ExamFieldKind::Param, ExamFieldKind::Filter])
            ->pluck('placeholder')
            ->all();

        return $this->command($exam, CommandType::QueryPatients, [
            'values' => array_intersect_key($values, array_flip($allowed)),
        ]);
    }

    /**
     * Pede ao agente a gravação dos laudos. A senha do Tasy fica só no payload do
     * comando, criptografado, e o painel apaga o payload depois (R-EXA-5).
     *
     * @param  array<string, mixed>  $patient
     * @param  list<string>  $fileIds
     * @param  array<string, string>  $values
     */
    public function record(Exam $exam, array $patient, array $fileIds, array $values, string $tasyUsername, string $tasyPassword): AgentCommand
    {
        $this->ensureOpen($exam);

        $files = $exam->files()->whereIn('id', $fileIds)->get();

        if ($files->isEmpty()) {
            throw new ExamCycleException(__('Escolha pelo menos um arquivo para o laudo.'));
        }

        return DB::transaction(function () use ($exam, $patient, $files, $values, $tasyUsername, $tasyPassword) {
            $exam->files()->update(['selected' => false]);
            $exam->files()->whereIn('id', $files->pluck('id'))->update(['selected' => true]);

            $exam->update([
                'status' => ExamStatus::Recording,
                'patient' => $patient,
                'complementary_info' => $values,
            ]);

            return $this->command($exam, CommandType::RecordReport, [
                'tasy_username' => $tasyUsername,
                'tasy_password' => $tasyPassword,
                'patient' => $patient,
                'files' => $files->map(fn ($file) => [
                    'id' => $file->id,
                    'original_name' => $file->original_name,
                    'sha256' => $file->sha256,
                ])->values()->all(),
                'values' => $values,
            ]);
        });
    }

    /**
     * Conclui o exame só com os laudos gravados. Os arquivos com erro ficam de fora (R-EXA-9).
     */
    public function completeWithRecordedReports(Exam $exam): void
    {
        $this->ensureOpen($exam);

        if (! $exam->files()->whereNotNull('erp_report_id')->exists()) {
            throw new ExamCycleException(__('Nenhum laudo foi gravado. Grave pelo menos um laudo ou cancele o exame.'));
        }

        $exam->files()->whereNull('erp_report_id')->update(['selected' => false]);
        $exam->update(['status' => ExamStatus::Completed, 'completed_at' => now()]);
        $exam->purgePreviews();
    }

    /**
     * Cancela o exame, apaga os previews e avisa o agente.
     */
    public function cancel(Exam $exam): void
    {
        $this->ensureOpen($exam);

        DB::transaction(function () use ($exam) {
            $exam->commands()->whereIn('status', [CommandStatus::Pending, CommandStatus::Delivered])
                ->update(['status' => CommandStatus::Expired->value, 'payload' => null]);

            $exam->update(['status' => ExamStatus::Cancelled, 'completed_at' => now()]);
            $this->command($exam, CommandType::CancelExam, []);
        });

        $exam->purgePreviews();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function command(Exam $exam, CommandType $type, array $payload): AgentCommand
    {
        return AgentCommand::create([
            'device_id' => $exam->device_id,
            'exam_id' => $exam->id,
            'type' => $type,
            'payload' => $payload,
            'expires_at' => now()->addSeconds($type->ttlSeconds()),
        ]);
    }

    private function ensureOpen(Exam $exam): void
    {
        if (! $exam->status->isOpen()) {
            throw new ExamCycleException(__('Este exame já terminou.'));
        }
    }
}
