<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\ActivationCode;
use App\Support\Cnpj;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Empresa cliente da Weega, identificada pelo CNPJ.
 */
#[Fillable(['cnpj', 'legal_name', 'trade_name', 'contact_name', 'contact_email', 'contact_phone', 'active'])]
#[Hidden(['activation_code'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'activation_code' => 'encrypted',
        ];
    }

    /**
     * Todo cliente novo recebe um código de ativação (R-LIC-11).
     */
    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            $client->activation_code ??= ActivationCode::generate();
        });
    }

    /**
     * Troca o código de ativação. O código anterior deixa de valer na hora (R-LIC-11).
     */
    public function regenerateActivationCode(): void
    {
        $this->forceFill(['activation_code' => ActivationCode::generate()])->save();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<License, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return HasOne<ErpConnection, $this>
     */
    public function erpConnection(): HasOne
    {
        return $this->hasOne(ErpConnection::class);
    }

    /**
     * @return HasMany<ExamType, $this>
     */
    public function examTypes(): HasMany
    {
        return $this->hasMany(ExamType::class);
    }

    /**
     * @return HasMany<Exam, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /**
     * Clientes que o usuário pode ver: todos para a Staff Weega, só os vinculados
     * para o operador (R-ACE-2 e R-ACE-3).
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isStaff()) {
            $query->whereHas('users', fn (Builder $users) => $users->whereKey($user->getKey()));
        }
    }

    public function formattedCnpj(): string
    {
        return Cnpj::format($this->cnpj);
    }
}
