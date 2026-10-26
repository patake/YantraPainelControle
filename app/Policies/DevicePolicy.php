<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

/**
 * Só a Staff Weega (administrador e suporte) ativa e bloqueia equipamentos (R-LIC-4).
 */
class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function decide(User $user, Device $device): bool
    {
        return $user->isStaff();
    }
}
