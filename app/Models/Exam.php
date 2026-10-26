<?php

namespace App\Models;

use App\Enums\ExamStatus;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Exame realizado: um ciclo de exame num equipamento.
 */
#[Fillable([
    'client_id', 'device_id', 'exam_type_id', 'operator_id', 'status', 'temp_folder',
    'patient', 'complementary_info', 'started_at', 'completed_at',
])]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => ExamStatus::class,
            'patient' => 'array',
            'complementary_info' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
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
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * @return HasMany<ExamFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ExamFile::class);
    }

    /**
     * @return HasMany<AgentCommand, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(AgentCommand::class);
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', ExamStatus::open());
    }
}
