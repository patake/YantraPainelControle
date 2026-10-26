<?php

namespace App\Actions\Exams;

use App\Models\ExamType;
use App\Models\ExamTypeVersion;
use Illuminate\Validation\ValidationException;

/**
 * Restaura uma versão antiga numa versão nova. O histórico nunca perde versões (R-EXA-10).
 */
class RestoreExamTypeVersion
{
    public function __construct(private SaveExamType $save) {}

    public function handle(ExamTypeVersion $version): ExamType
    {
        $examType = $version->examType;
        $attributes = $version->snapshot['attributes'];

        $nameTaken = ExamType::query()
            ->where('client_id', $examType->client_id)
            ->where('name', $attributes['name'])
            ->whereKeyNot($examType->id)
            ->exists();

        if ($nameTaken) {
            throw ValidationException::withMessages([
                'version' => __('O cliente já tem outro exame com o nome :name.', ['name' => $attributes['name']]),
            ]);
        }

        // Uma versão anterior à E8 não tem regras seriais na cópia: a lista vazia apaga as regras atuais.
        return $this->save->handle(
            $examType,
            $attributes,
            $version->snapshot['fields'],
            serialRules: $version->snapshot['serial_rules'] ?? [],
            restoredFrom: $version->version,
        );
    }
}
