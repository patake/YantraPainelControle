<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * O 2FA é obrigatório para todos (R-ACE-5). Sem 2FA confirmado, o usuário só acessa
 * a tela de segurança, onde cadastra o aplicativo autenticador.
 */
class EnsureTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasConfirmedTwoFactor()) {
            return redirect()->route('security.edit')
                ->with('two-factor-required', true);
        }

        return $next($request);
    }
}
