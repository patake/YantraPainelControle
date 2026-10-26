<x-layouts::auth :title="__('Criar a senha')">
    <div class="flex flex-col gap-6">
        @if ($valid)
            <x-auth-header :title="__('Criar a senha')" :description="__('Olá, :name. Crie a senha do seu usuário :email.', ['name' => $user->name, 'email' => $user->email])" />

            <form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-col gap-6">
                @csrf

                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm password')"
                    viewable
                />

                <flux:text>{{ __('A senha tem no mínimo 12 caracteres. Depois de criar a senha, você entra e cadastra o 2FA.') }}</flux:text>

                <flux:button type="submit" variant="primary" class="w-full" data-test="accept-invitation-button">
                    {{ __('Criar a senha') }}
                </flux:button>
            </form>
        @else
            <x-auth-header :title="__('Convite inválido')" :description="__('O convite expirou, já foi usado ou foi trocado por um convite novo.')" />

            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>{{ __('Peça um novo convite ao administrador da Weega. Se você já criou a senha, entre pelo login.') }}</flux:callout.text>
            </flux:callout>

            <flux:button :href="route('login')" variant="primary" class="w-full">{{ __('Ir para o login') }}</flux:button>
        @endif
    </div>
</x-layouts::auth>
