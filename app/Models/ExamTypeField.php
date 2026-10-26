<?php

namespace App\Models;

use App\Enums\ExamFieldKind;
use App\Models\Concerns\Auditable;
use App\Support\DeviceConfig;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leitura do exame. O valor preenche o marcador {placeholder} dos SQLs.
 */
#[Fillable(['exam_type_id', 'kind', 'placeholder', 'db_column', 'label', 'input', 'default_value', 'options', 'required', 'position'])]
class ExamTypeField extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'kind' => ExamFieldKind::class,
            'options' => 'array',
            'required' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExamType, $this>
     */
    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    /**
     * Cada alteração aumenta a versão dos parâmetros dos equipamentos afetados (E5).
     */
    protected static function booted(): void
    {
        static::saved(fn (ExamTypeField $model) => DeviceConfig::bumpExamType($model->exam_type_id));
        static::deleted(fn (ExamTypeField $model) => DeviceConfig::bumpExamType($model->exam_type_id));
    }
}
