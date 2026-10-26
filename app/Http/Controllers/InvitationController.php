<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * O usuário novo cria a senha pelo link do convite (R-ACE-13).
 */
class InvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, User $user): View
    {
        return view('pages::auth.accept-invitation', [
            'user' => $user,
            'valid' => $this->isPending($request, $user),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse|View
    {
        if (! $this->isPending($request, $user)) {
            return $this->show($request, $user);
        }

        $validated = $request->validate(['password' => $this->passwordRules()]);

        // A senha do convite vale como o passo 1 do primeiro acesso (R-ACE-15).
        $user->forceFill(['password' => $validated['password'], 'password_changed_at' => now(), 'invited_at' => null])->save();

        return redirect()->route('login')->with('status', __('Senha criada. Entre com o e-mail e a senha e cadastre o 2FA.'));
    }

    /**
     * O link vale até expirar e só para o último convite enviado.
     */
    private function isPending(Request $request, User $user): bool
    {
        return $request->hasValidSignature()
            && $user->invited_at !== null
            && $user->invited_at->getTimestamp() === (int) $request->query('sent');
    }
}
