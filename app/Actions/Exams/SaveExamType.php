<?php

namespace App\Actions\Exams;

use App\Models\ExamType;
use App\Models\ExamTypeVersion;
use Illuminate\Support\Facades\DB;

/**
 * Grava o exame e as leituras numa versão nova. Cada gravação cria uma versão no histórico (R-EXA-10).
 */
class SaveExamType
{
    /**
     * Atributos do exame que entram na cópia da versão.
     */
    public const ATTRIBUTES = [
        'name', 'erp_type', 'procedure_code', 'destination_folder', 'active', 'print_options',
        'sql_query', 'sql_auth', 'sql_insert', 'sql_image', 'sql_update',
    ];

    /**
     * Campos de cada leitura que entram na cópia da versão.
     */
    public const FIELD_ATTRIBUTES = ['kind', 'placeholder', 'db_column', 'label', 'input', 'default_value', 'options', 'required'];

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array{field: string, pattern: string}>|null  $serialRules  null mantém as regras seriais atuais
     */
    public function handle(ExamType $examType, array $attributes, array $fields, ?array $serialRules = null, ?int $restoredFrom = null): ExamType
    {
        return DB::transaction(function () use ($examType, $attributes, $fields, $serialRules, $restoredFrom) {
            $examType->fill($attributes);

            if ($examType->exists) {
                $examType->version++;
            } else {
                $examType->version ??= 1;
            }

            $examType->save();
            $examType->fields()->delete();

            foreach (array_values($fields) as $position => $field) {
                $examType->fields()->create(array_merge(
                    array_intersect_key($field, array_flip(self::FIELD_ATTRIBUTES)),
                    ['position' => $position],
                ));
            }

            // Regras regex da porta serial (E8). O valor extraído vai para o campo de mesmo nome.
            if ($serialRules !== null) {
                $examType->serialRules()->delete();

                foreach (array_values($serialRules) as $position => $rule) {
                    $examType->serialRules()->create([
                        'field' => $rule['field'],
                        'pattern' => $rule['pattern'],
                        'position' => $position,
                    ]);
                }
            }

            $examType->load(['fields', 'serialRules']);

            ExamTypeVersion::create([
                'exam_type_id' => $examType->id,
                'version' => $examType->version,
                'snapshot' => self::snapshot($examType),
                'restored_from' => $restoredFrom,
                'user_id' => auth()->id(),
            ]);

            return $examType;
        });
    }

    /**
     * @return array{attributes: array<string, mixed>, fields: list<array<string, mixed>>, serial_rules: list<array{field: string, pattern: string}>}
     */
    public static function snapshot(ExamType $examType): array
    {
        $attributes = [];

        foreach (self::ATTRIBUTES as $attribute) {
            $value = $examType->getAttribute($attribute);
            $attributes[$attribute] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        $fields = $examType->fields->map(function ($field) {
            $values = [];

            foreach (self::FIELD_ATTRIBUTES as $attribute) {
                $value = $field->getAttribute($attribute);
                $values[$attribute] = $value instanceof \BackedEnum ? $value->value : $value;
            }

            return $values;
        })->values()->all();

        $serialRules = $examType->serialRules
            ->map(fn ($rule) => ['field' => $rule->field, 'pattern' => $rule->pattern])
            ->values()
            ->all();

        return ['attributes' => $attributes, 'fields' => $fields, 'serial_rules' => $serialRules];
    }
}
