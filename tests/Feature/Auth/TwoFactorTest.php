<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-ACE-5: o 2FA é obrigatório para todos.
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_a_user_without_two_factor_to_the_setup(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('staff.dashboard'))
            ->assertRedirect(route('security.edit'));
    }

    public function test_setup_screen_opens_right_after_login_without_asking_the_password_again(): void
    {
        $user = User::factory()->admin()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->get(route('security.edit'))
            ->assertOk()
            ->assertSee('O painel exige a verificação em duas etapas.');
    }

    public function test_user_with_confirmed_two_factor_reaches_the_area(): void
    {
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())
            ->get(route('staff.dashboard'))
            ->assertOk();
    }

    public function test_locks_the_code_challenge_for_15_minutes_after_5_wrong_codes(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $session = ['login.id' => $user->id, 'login.remember' => false];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withSession($session)
                ->post(route('two-factor.login.store'), ['code' => '000000'])
                ->assertRedirect();
        }

        $this->withSession($session)
            ->post(route('two-factor.login.store'), ['code' => '000000'])
            ->assertTooManyRequests();

        $this->travel(14)->minutes();
        $this->withSession($session)
            ->post(route('two-factor.login.store'), ['code' => '000000'])
            ->assertTooManyRequests();

        $this->travel(2)->minutes();
        $this->withSession($session)
            ->post(route('two-factor.login.store'), ['code' => '000000'])
            ->assertRedirect();
    }

    public function test_recovery_code_signs_in_once(): void
    {
        $user = User::factory()->operatorWithClient()->withTwoFactor()->create();
        $session = ['login.id' => $user->id, 'login.remember' => false];

        $this->withSession($session)
            ->post(route('two-factor.login.store'), ['recovery_code' => '7KQ2-M9XD'])
            ->assertRedirect('/inicio');

        $this->assertAuthenticatedAs($user);
        $this->assertNotContains('7KQ2-M9XD', $user->fresh()->recoveryCodes());
    }
}
