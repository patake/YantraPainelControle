<?php

namespace App\Models;

use App\Enums\ExamFieldKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leitura do exame. O valor preenche o marcador {placeholder} dos SQLs.
 */
#[Fillable(['exam_type_id', 'kind', 'placeholder', 'label', 'input', 'default_value', 'options', 'required', 'position'])]
class ExamTypeField extends Model
{
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
}
