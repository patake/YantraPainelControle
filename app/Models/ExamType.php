<?php

namespace App\Models;

use App\Enums\ErpType;
use App\Models\Concerns\Auditable;
use App\Support\DeviceConfig;
use Database\Factories\ExamTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de exame no modelo do Yantra Cardio 3.0: 5 SQLs e as leituras.
 */
#[Fillable([
    'client_id', 'name', 'erp_type', 'procedure_code', 'destination_folder',
    'sql_query', 'sql_auth', 'sql_insert', 'sql_image', 'sql_update',
    'print_options', 'active', 'version',
])]
class ExamType extends Model
{
    /** @use HasFactory<ExamTypeFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'erp_type' => ErpType::class,
            'print_options' => 'array',
            'active' => 'boolean',
            'version' => 'integer',
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
     * Leituras do exame, na ordem de exibição.
     *
     * @return HasMany<ExamTypeField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(ExamTypeField::class)->orderBy('position');
    }

    /**
     * @return HasMany<SerialRule, $this>
     */
    public function serialRules(): HasMany
    {
        return $this->hasMany(SerialRule::class)->orderBy('position');
    }

    /**
     * Histórico das versões (R-EXA-10).
     *
     * @return HasMany<ExamTypeVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ExamTypeVersion::class);
    }

    /**
     * @return BelongsToMany<Device, $this>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class)->withTimestamps();
    }

    /**
     * Marcadores {X} usados nos 5 SQLs, sem repetição.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        $sql = implode("\n", [$this->sql_query, $this->sql_auth, $this->sql_insert, $this->sql_image, $this->sql_update]);

        preg_match_all('/\{([A-Za-z0-9_]+)\}/', $sql, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Cada alteração aumenta a versão dos parâmetros dos equipamentos afetados (E5).
     */
    protected static function booted(): void
    {
        static::saved(fn (ExamType $model) => DeviceConfig::bumpExamType($model->id));
        static::deleted(fn (ExamType $model) => DeviceConfig::bumpExamType($model->id));
    }
}
