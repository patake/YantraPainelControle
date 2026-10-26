<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\ReleaseChannel;
use App\Enums\SystemFeature;
use App\Exceptions\LicenseUnavailableException;
use App\Models\Concerns\Auditable;
use Database\Factories\DeviceFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Equipamento com o agente instalado. O agente se autentica com um token Sanctum
 * que pertence ao equipamento (R-SEG-1).
 */
#[Fillable([
    'client_id', 'system_id', 'signature', 'status', 'trial_ends_at', 'decided_by', 'decided_at',
    'hostname', 'machine_type', 'domain', 'macs', 'windows_version', 'agent_version', 'release_channel',
    'config_version', 'last_seen_at', 'registered_at',
])]
class Device extends Model implements AuthenticatableContract, AuthorizableContract
{
    /** @use HasFactory<DeviceFactory> */
    use Auditable, Authenticatable, Authorizable, HasApiTokens, HasFactory, HasUuids;

    /**
     * Habilidade do token de um equipamento.
     */
    public const TOKEN_ABILITY = 'agent';

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'release_channel' => ReleaseChannel::class,
            'trial_ends_at' => 'datetime',
            'decided_at' => 'datetime',
            'macs' => 'array',
            'config_version' => 'integer',
            'last_seen_at' => 'datetime',
            'registered_at' => 'datetime',
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
     * @return BelongsTo<System, $this>
     */
    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    /**
     * Usuário da Staff Weega que ativou ou bloqueou o equipamento.
     *
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Equipamento do sistema de captura de tela (YO SRC). Só ele usa a combinação de teclas (R-EXA-11).
     */
    public function capturesScreen(): bool
    {
        return $this->system?->feature === SystemFeature::Screen;
    }

    /**
     * @return HasOne<DeviceSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(DeviceSetting::class);
    }

    /**
     * Tipos de exame habilitados no equipamento.
     *
     * @return BelongsToMany<ExamType, $this>
     */
    public function examTypes(): BelongsToMany
    {
        return $this->belongsToMany(ExamType::class)->withTimestamps();
    }

    /**
     * @return HasMany<Exam, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /**
     * @return HasMany<AgentCommand, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(AgentCommand::class);
    }

    /**
     * Equipamentos que ocupam licença: trial ou ativo (R-LIC-1).
     */
    #[Scope]
    protected function usingLicense(Builder $query): void
    {
        $query->whereIn('status', DeviceStatus::usingLicense());
    }

    /**
     * Trials vencidos que ainda não passaram para expirado (R-LIC-3).
     */
    #[Scope]
    protected function trialOverdue(Builder $query): void
    {
        $query->where('status', DeviceStatus::Trial)->where('trial_ends_at', '<=', now());
    }

    /**
     * Ativa a licença do equipamento. Só a Staff Weega decide (R-LIC-4).
     * Um equipamento fora do trial precisa de uma licença livre para voltar (R-LIC-2).
     */
    public function activate(User $by): void
    {
        if (! in_array($this->status, DeviceStatus::usingLicense(), true)) {
            $license = License::whereBelongsTo($this->client)->whereBelongsTo($this->system)->first();

            if ($license === null || ! $license->hasAvailableSeat()) {
                throw new LicenseUnavailableException(__('Todas as licenças do sistema :system estão em uso.', ['system' => $this->system->name]));
            }
        }

        $this->update([
            'status' => DeviceStatus::Active,
            'trial_ends_at' => null,
            'decided_by' => $by->id,
            'decided_at' => now(),
        ]);
    }

    /**
     * Bloqueia o equipamento. O token continua válido: a API responde só a licença (R-SEG-1).
     */
    public function block(User $by): void
    {
        $this->update([
            'status' => DeviceStatus::Blocked,
            'decided_by' => $by->id,
            'decided_at' => now(),
        ]);
    }

    /**
     * O equipamento não tem senha: o login web nunca usa este modelo.
     */
    public function getAuthPassword(): string
    {
        return '';
    }
}
