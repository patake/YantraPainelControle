<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\DeviceConfig;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regra regex (RE2) que extrai um campo do conteúdo da porta serial.
 */
#[Fillable(['exam_type_id', 'field', 'pattern', 'position'])]
class SerialRule extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
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
        static::saved(fn (SerialRule $model) => DeviceConfig::bumpExamType($model->exam_type_id));
        static::deleted(fn (SerialRule $model) => DeviceConfig::bumpExamType($model->exam_type_id));
    }
}
