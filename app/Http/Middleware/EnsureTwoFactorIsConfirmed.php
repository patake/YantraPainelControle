<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * O 2FA é obrigatório para todos (R-ACE-5). Sem o primeiro acesso completo (R-ACE-15)
 * ou sem 2FA confirmado, o usuário só acessa o assistente de primeiro acesso.
 */
class EnsureTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->needsOnboarding()) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
