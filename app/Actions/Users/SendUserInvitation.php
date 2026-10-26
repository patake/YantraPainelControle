<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\URL;

/**
 * Envia o convite com o link assinado para criar a senha (R-ACE-13).
 * Um convite novo invalida o link do convite anterior.
 */
class SendUserInvitation
{
    public const VALID_DAYS = 7;

    public function handle(User $user): void
    {
        $user->forceFill(['invited_at' => now()])->save();

        $url = URL::temporarySignedRoute('invitation.show', now()->addDays(self::VALID_DAYS), [
            'user' => $user->id,
            'sent' => $user->invited_at->getTimestamp(),
        ]);

        $user->notify(new UserInvitation($url, self::VALID_DAYS));
    }
}
