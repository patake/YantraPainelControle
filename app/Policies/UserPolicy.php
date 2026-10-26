<?php

namespace App\Policies;

use App\Models\User;

/**
 * A Staff Weega consulta os usuários. Só o administrador cria e altera usuários,
 * inclusive os usuários da Staff Weega (R-ACE-4).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Reinicia o 2FA de um usuário sem celular e sem código de recuperação.
     */
    public function resetTwoFactor(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }
}
