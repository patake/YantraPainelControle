<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * A Staff Weega vê todos os clientes. Só o administrador altera clientes.
 * O operador vê só os clientes vinculados a ele (R-ACE-2 e R-ACE-3).
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Client $client): bool
    {
        return $user->isStaff() || $user->clients()->whereKey($client->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }
}
