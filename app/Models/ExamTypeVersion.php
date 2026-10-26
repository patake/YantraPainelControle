<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cópia do exame numa versão: atributos, 5 SQLs e leituras (R-EXA-10).
 *
 * @property array{attributes: array<string, mixed>, fields: list<array<string, mixed>>} $snapshot
 */
#[Fillable(['exam_type_id', 'version', 'snapshot', 'restored_from', 'user_id'])]
class ExamTypeVersion extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
            'restored_from' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
