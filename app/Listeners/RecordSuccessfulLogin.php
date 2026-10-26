<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Grava a data do último login. Também marca a senha como confirmada, porque o
 * usuário acabou de digitar a senha: o cadastro do 2FA abre sem pedir a senha de novo.
 */
class RecordSuccessfulLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        if (request()->hasSession()) {
            request()->session()->passwordConfirmed();
        }
    }
}
