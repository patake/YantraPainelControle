<?php

namespace App\Models;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comando do painel para o agente. O payload fica criptografado, porque o comando
 * record_report leva a senha do Tasy (R-EXA-5).
 */
#[Fillable(['device_id', 'exam_id', 'type', 'payload', 'status', 'result', 'expires_at', 'delivered_at', 'completed_at'])]
#[Hidden(['payload'])]
class AgentCommand extends Model
{
    use HasUuids;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'type' => CommandType::class,
            'payload' => 'encrypted:array',
            'status' => CommandStatus::class,
            'result' => 'array',
            'expires_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Comandos pendentes e ainda válidos.
     */
    #[Scope]
    protected function deliverable(Builder $query): void
    {
        $query->where('status', CommandStatus::Pending)->where('expires_at', '>', now());
    }
}
