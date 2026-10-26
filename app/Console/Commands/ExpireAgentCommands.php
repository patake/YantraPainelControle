<?php

namespace App\Console\Commands;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\ExamStatus;
use App\Models\AgentCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Expira os comandos sem resposta no prazo. Apaga o payload, que pode ter a senha
 * do Tasy (R-EXA-5), e devolve o exame ao estado certo.
 */
#[Signature('commands:expire')]
#[Description('Expira os comandos do agente sem resposta no prazo')]
class ExpireAgentCommands extends Command
{
    public function handle(): int
    {
        $count = 0;

        AgentCommand::query()
            ->with('exam')
            ->whereIn('status', [CommandStatus::Pending, CommandStatus::Delivered])
            ->where('expires_at', '<=', now())
            ->each(function (AgentCommand $command) use (&$count) {
                $command->update(['status' => CommandStatus::Expired, 'payload' => null]);
                $count++;

                $exam = $command->exam;

                if ($exam === null || ! $exam->status->isOpen()) {
                    return;
                }

                // Sem início confirmado, o exame falha. Sem gravação confirmada, o exame volta para a conferência.
                match ($command->type) {
                    CommandType::StartExam => $exam->update(['status' => ExamStatus::Failed, 'completed_at' => now()]),
                    CommandType::RecordReport => $exam->update(['status' => ExamStatus::Reviewing]),
                    default => null,
                };
            });

        $this->info("Comandos expirados: {$count}.");

        return self::SUCCESS;
    }
}
