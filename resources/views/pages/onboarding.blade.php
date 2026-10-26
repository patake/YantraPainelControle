<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Assistente de primeiro acesso (R-ACE-15): 1) troca da senha, 2) cadastro do 2FA,
 * 3) confirmação do tema, 4) acesso ao painel.
 */
new class extends Component {
    use PasswordValidationRules;

    public const STEPS = [1 => 'Senha', 2 => 'Verificação em duas etapas', 3 => 'Tema', 4 => 'Tudo pronto'];

    #[Locked]
    public int $step = 1;

    public string $password = '';

    public string $password_confirmation = '';

    public string $code = '';

    #[Locked]
    public bool $showRecoveryCodes = false;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->needsOnboarding()) {
            $this->redirectRoute('home');

            return;
        }

        $this->step = match (true) {
            ! isset($user->password_changed_at) => 1,
            ! $user->hasConfirmedTwoFactor() => 2,
            default => 3,
        };

        if ($this->step === 2) {
            $this->prepareTwoFactor();
        }
    }

    /**
     * Passo 1: a senha nova substitui a senha provisória (R-ACE-8).
     */
    public function savePassword(): void
    {
        abort_unless($this->step === 1, 409);

        $this->validate(['password' => $this->passwordRules()], [], ['password' => __('senha nova')]);

        $user = auth()->user();

        if (Hash::check($this->password, $user->password)) {
            $this->addError('password', __('A senha nova precisa ser diferente da senha atual.'));

            return;
        }

        $user->forceFill(['password' => $this->password, 'password_changed_at' => now()])->save();

        $this->reset('password', 'password_confirmation');
        $this->step = 2;
        $this->prepareTwoFactor();
    }

    /**
     * Passo 2: gera a chave do aplicativo autenticador. Uma chave não confirmada continua a mesma,
     * então recarregar a página não invalida o QR code já lido.
     */
    private function prepareTwoFactor(): void
    {
        $user = auth()->user();
        app(EnableTwoFactorAuthentication::class)($user);
        $user->refresh();

        $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
        $this->manualSetupKey = decrypt($user->two_factor_secret);
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirm): void
    {
        abort_unless($this->step === 2 && ! $this->showRecoveryCodes, 409);

        $this->validate(['code' => ['required', 'string', 'size:6']], [], ['code' => __('código')]);

        $confirm(auth()->user(), $this->code);

        $this->reset('code', 'qrCodeSvg', 'manualSetupKey');
        $this->showRecoveryCodes = true;
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function recoveryCodes(): array
    {
        return $this->showRecoveryCodes ? auth()->user()->recoveryCodes() : [];
    }

    public function continueAfterRecoveryCodes(): void
    {
        abort_unless($this->showRecoveryCodes, 409);

        $this->showRecoveryCodes = false;
        $this->step = 3;
    }

    /**
     * Passo 3: o tema fica no navegador (Flux). O painel grava o fim do primeiro acesso.
     */
    public function confirmTheme(): void
    {
        abort_unless($this->step === 3, 409);

        auth()->user()->forceFill(['onboarded_at' => now()])->save();

        $this->step = 4;
    }

    public function enter(): void
    {
        abort_if(auth()->user()->needsOnboarding(), 409);

        $this->redirectRoute('home');
    }

    public function render()
    {
        return $this->view()->layout('layouts::onboarding')->title(__('Primeiro acesso'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <ol class="grid grid-cols-4 gap-2" aria-label="{{ __('Passos do primeiro acesso') }}">
        @foreach ($this::STEPS as $number => $label)
            <li class="flex flex-col items-center gap-2 text-center" @if ($number === $step) aria-current="step" @endif>
                <span @class([
                    'flex size-9 items-center justify-center rounded-full text-sm font-semibold',
                    'bg-green-600 text-white' => $number < $step,
                    'bg-[#b8323a] text-white ring-4 ring-[#b8323a]/20' => $number === $step,
                    'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => $number > $step,
                ])>
                    @if ($number < $step)
                        <flux:icon.check class="size-5" />
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span @class(['text-xs', 'font-semibold' => $number === $step, 'text-zinc-500' => $number !== $step])>{{ __($label) }}</span>
            </li>
        @endforeach
    </ol>

    <flux:card class="flex flex-col gap-6">
        @if ($step === 1)
            <div>
                <flux:heading size="lg">{{ __('Crie a sua senha') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Olá, :name. Troque a senha provisória por uma senha só sua.', ['name' => auth()->user()->name]) }}</flux:text>
            </div>

            <form wire:submit="savePassword" class="flex flex-col gap-4">
                <flux:input wire:model="password" type="password" :label="__('Senha nova')" autocomplete="new-password" viewable />
                <flux:input wire:model="password_confirmation" type="password" :label="__('Confirme a senha nova')" autocomplete="new-password" viewable />
                <flux:text variant="subtle">{{ __('A senha tem no mínimo 12 caracteres.') }}</flux:text>
                <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Salvar e continuar') }}</flux:button>
            </form>
        @elseif ($step === 2 && ! $showRecoveryCodes)
            <div>
                <flux:heading size="lg">{{ __('Ative a verificação em duas etapas') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Em todo login, o painel pede um código do aplicativo autenticador do seu celular.') }}</flux:text>
            </div>

            <ol class="flex list-decimal flex-col gap-1 ps-5 text-sm text-zinc-600 dark:text-zinc-300">
                <li>{{ __('Abra o Google Authenticator ou o Microsoft Authenticator no celular.') }}</li>
                <li>{{ __('Leia o QR code abaixo. Sem câmera, digite a chave manual.') }}</li>
                <li>{{ __('Digite o código de 6 dígitos que o aplicativo mostra.') }}</li>
            </ol>

            <div class="flex flex-col items-center gap-3">
                <div class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700">
                    {!! $qrCodeSvg !!}
                </div>
                <flux:text variant="subtle" class="text-xs">{{ __('Chave manual') }}</flux:text>
                <code class="select-all rounded bg-zinc-100 px-2 py-1 font-mono text-sm tracking-wider dark:bg-zinc-800">{{ $manualSetupKey }}</code>
            </div>

            <form wire:submit="confirmTwoFactor" class="flex flex-col gap-4">
                <flux:input wire:model="code" :label="__('Código do aplicativo')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="font-mono" />
                <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Confirmar código') }}</flux:button>
            </form>

            <flux:text variant="subtle" class="text-xs">{{ __('O código muda a cada 30 segundos e depende da hora do celular. Deixe a data e a hora do celular no modo automático.') }}</flux:text>
        @elseif ($step === 2)
            <div>
                <flux:heading size="lg">{{ __('Guarde os códigos de recuperação') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Sem o celular, você entra com um destes códigos. Cada código vale uma vez.') }}</flux:text>
            </div>

            <div class="grid grid-cols-2 gap-2 rounded-lg bg-zinc-100 p-4 font-mono text-sm dark:bg-zinc-800">
                @foreach ($this->recoveryCodes as $recoveryCode)
                    <span class="select-all">{{ $recoveryCode }}</span>
                @endforeach
            </div>

            <flux:callout icon="exclamation-triangle" variant="warning">
                {{ __('Guarde os códigos num lugar seguro, fora do computador do exame. Depois desta tela, você vê os códigos de novo só em Perfil e 2FA.') }}
            </flux:callout>

            <flux:button wire:click="continueAfterRecoveryCodes" variant="primary" icon-trailing="arrow-right">{{ __('Já guardei os códigos') }}</flux:button>
        @elseif ($step === 3)
            <div>
                <flux:heading size="lg">{{ __('Escolha o tema') }}</flux:heading>
                <flux:text class="mt-1">{{ __('O tema muda na hora. Depois, você troca o tema no menu do usuário.') }}</flux:text>
            </div>

            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun">{{ __('Claro') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Escuro') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('Sistema') }}</flux:radio>
            </flux:radio.group>

            <flux:button wire:click="confirmTheme" variant="primary" icon-trailing="arrow-right">{{ __('Confirmar o tema') }}</flux:button>
        @else
            <div class="flex flex-col items-center gap-3 text-center">
                <span class="flex size-14 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400">
                    <flux:icon.check-badge class="size-8" />
                </span>
                <flux:heading size="lg">{{ __('Tudo pronto') }}</flux:heading>
                <flux:text>{{ __('A sua conta está protegida. No próximo login, o painel pede a senha e o código do aplicativo.') }}</flux:text>
            </div>

            <flux:button wire:click="enter" variant="primary" icon-trailing="arrow-right">{{ __('Acessar o painel') }}</flux:button>
        @endif
    </flux:card>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <flux:button type="submit" variant="ghost" size="sm">{{ __('Sair') }}</flux:button>
    </form>
</div>
