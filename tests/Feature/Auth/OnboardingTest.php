<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * R-ACE-15: no primeiro acesso, o usuário troca a senha, cadastra o 2FA, confirma o tema
 * e só depois acessa o painel.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'senha-nova-do-primeiro-acesso';

    public function test_wizard_opens_right_after_the_first_login(): void
    {
        $user = User::factory()->operatorWithClient()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('home'));

        $this->get(route('home'))->assertRedirect(route('onboarding'));
        $this->get(route('app.dashboard'))->assertRedirect(route('onboarding'));
        $this->get(route('onboarding'))->assertOk()->assertSee('Crie a sua senha');
    }

    public function test_user_goes_through_the_four_steps(): void
    {
        $user = User::factory()->operatorWithClient()->create();
        $this->actingAs($user);

        $wizard = Livewire::test('pages::onboarding')
            ->assertSet('step', 1)
            ->set('password', self::NEW_PASSWORD)
            ->set('password_confirmation', self::NEW_PASSWORD)
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertSet('step', 2);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));

        // O aplicativo autenticador calcula o código com a chave do QR code.
        $code = app(Google2FA::class)->getCurrentOtp($wizard->get('manualSetupKey'));

        $wizard->set('code', $code)
            ->call('confirmTwoFactor')
            ->assertHasNoErrors()
            ->assertSet('showRecoveryCodes', true)
            ->assertSee($user->fresh()->recoveryCodes()[0])
            ->call('continueAfterRecoveryCodes')
            ->assertSet('step', 3);

        $this->get(route('app.dashboard'))->assertRedirect(route('onboarding'));

        $wizard->call('confirmTheme')
            ->assertSet('step', 4)
            ->assertSee('Tudo pronto')
            ->call('enter')
            ->assertRedirect(route('home'));

        $this->assertTrue($user->fresh()->hasConfirmedTwoFactor());
        $this->get(route('app.dashboard'))->assertOk();
    }

    public function test_new_password_must_differ_from_the_temporary_password(): void
    {
        $this->actingAs(User::factory()->operatorWithClient()->create(['password' => self::NEW_PASSWORD]));

        Livewire::test('pages::onboarding')
            ->set('password', self::NEW_PASSWORD)
            ->set('password_confirmation', self::NEW_PASSWORD)
            ->call('savePassword')
            ->assertHasErrors('password')
            ->assertSet('step', 1);
    }

    public function test_rejects_a_wrong_code(): void
    {
        $this->actingAs(User::factory()->operatorWithClient()->create(['password_changed_at' => now()]));

        Livewire::test('pages::onboarding')
            ->assertSet('step', 2)
            ->set('code', '000000')
            ->call('confirmTwoFactor')
            ->assertHasErrors('code')
            ->assertSet('showRecoveryCodes', false);
    }

    public function test_reloading_the_wizard_keeps_the_key_already_read_by_the_phone(): void
    {
        $this->actingAs(User::factory()->operatorWithClient()->create(['password_changed_at' => now()]));

        $first = Livewire::test('pages::onboarding')->get('manualSetupKey');

        $this->assertSame($first, Livewire::test('pages::onboarding')->get('manualSetupKey'));
    }

    public function test_invited_user_starts_at_the_two_factor_step(): void
    {
        $this->actingAs(User::factory()->operatorWithClient()->create(['password_changed_at' => now()]));

        Livewire::test('pages::onboarding')->assertSet('step', 2)->assertSee('Ative a verificação em duas etapas');
    }

    public function test_user_who_finished_goes_to_the_panel(): void
    {
        $this->actingAs(User::factory()->operatorWithClient()->withTwoFactor()->create());

        Livewire::test('pages::onboarding')->assertRedirect(route('home'));
    }
}
