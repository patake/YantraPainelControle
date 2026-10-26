<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'active' => 'boolean',
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Clientes vinculados ao usuário. A Staff Weega vê todos os clientes (R-ACE-3).
     *
     * @return BelongsToMany<Client, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class)->withTimestamps();
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Gestor do hospital: usa a área Cliente e personaliza as teclas da captura de tela (R-ACE-14).
     */
    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    /**
     * Usuário ativo. O operador também precisa de um cliente ativo (R-ACE-6).
     */
    public function canSignIn(): bool
    {
        if (! $this->active) {
            return false;
        }

        return $this->isStaff() || $this->clients()->where('active', true)->exists();
    }

    /**
     * 2FA cadastrado e confirmado (R-ACE-5).
     */
    public function hasConfirmedTwoFactor(): bool
    {
        // isset() também cobre um usuário criado agora, sem as colunas do 2FA carregadas.
        return isset($this->two_factor_secret, $this->two_factor_confirmed_at);
    }

    /**
     * O usuário ainda não terminou o assistente de primeiro acesso, ou ficou sem 2FA
     * depois que o administrador reiniciou o 2FA (R-ACE-5, R-ACE-12, R-ACE-15).
     */
    public function needsOnboarding(): bool
    {
        return ! isset($this->onboarded_at) || ! $this->hasConfirmedTwoFactor();
    }

    /**
     * Iniciais do nome, para o avatar.
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    #[Scope]
    protected function staff(Builder $query): void
    {
        $query->whereIn('role', [UserRole::Admin, UserRole::Support]);
    }
}
