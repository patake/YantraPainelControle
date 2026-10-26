<?php

namespace App\Models;

use App\Enums\ExamFileKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Arquivo do exame. O painel guarda só os dados do arquivo e um preview temporário
 * no S3 (previews/). O painel apaga o preview no fim do exame (R-EXA-6 e R-EXA-7).
 */
#[Fillable([
    'exam_id', 'kind', 'original_name', 'preview_path', 'sha256', 'size_bytes',
    'selected', 'final_path', 'erp_report_id',
])]
class ExamFile extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'kind' => ExamFileKind::class,
            'size_bytes' => 'integer',
            'selected' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
