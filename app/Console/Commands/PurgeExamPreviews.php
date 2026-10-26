<?php

namespace App\Console\Commands;

use App\Enums\ExamStatus;
use App\Models\Exam;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Segunda garantia de que o painel não guarda arquivos de exame (R-EXA-7): apaga os
 * previews dos exames terminados e os previews com mais de 24 horas.
 */
#[Signature('exams:purge-previews')]
#[Description('Apaga os previews dos exames terminados e os previews com mais de 24 horas')]
class PurgeExamPreviews extends Command
{
    public function handle(): int
    {
        $count = 0;

        Exam::query()
            ->whereHas('files', fn ($query) => $query->whereNotNull('preview_path'))
            ->where(fn ($query) => $query
                ->whereNotIn('status', ExamStatus::open())
                ->orWhere('started_at', '<=', now()->subDay()))
            ->each(function (Exam $exam) use (&$count) {
                $exam->purgePreviews();
                $count++;
            });

        $this->info("Exames com previews apagados: {$count}.");

        return self::SUCCESS;
    }
}
