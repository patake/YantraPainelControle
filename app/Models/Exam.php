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
use Illuminate\Support\Facades\Storage;

/**
 * Exame realizado: um ciclo de exame num equipamento.
 */
#[Fillable([
    'client_id', 'device_id', 'exam_type_id', 'operator_id', 'status', 'temp_folder',
    'patient', 'complementary_info', 'serial_data', 'serial_received_at', 'started_at', 'completed_at',
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
            'serial_data' => 'array',
            'serial_received_at' => 'datetime',
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

    /**
     * Apaga os previews do S3. O painel guarda só os dados dos arquivos (R-EXA-6 e R-EXA-7).
     */
    public function purgePreviews(): void
    {
        $files = $this->files()->whereNotNull('preview_path')->get(['id', 'preview_path']);

        if ($files->isEmpty()) {
            return;
        }

        Storage::disk('s3')->delete($files->pluck('preview_path')->all());
        $this->files()->whereKey($files->pluck('id'))->update(['preview_path' => null]);
    }

    /**
     * Pasta dos previews deste exame no S3.
     */
    public function previewFolder(): string
    {
        return "previews/{$this->client_id}/{$this->id}";
    }

    /**
     * Exames que o usuário pode ver: todos para a Staff Weega, só os dos clientes
     * vinculados para o operador (R-ACE-2).
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isStaff()) {
            $query->whereIn('client_id', $user->clients()->where('active', true)->select('clients.id'));
        }
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', ExamStatus::open());
    }
}
