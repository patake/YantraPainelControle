<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_login_screen_in_portuguese(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Entrar');
    }

    public function test_redirects_a_guest_to_the_login(): void
    {
        $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_two_factor_signs_in_and_records_the_login_time(): void
    {
        $this->freezeSecond();
        $user = User::factory()->admin()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/inicio');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->last_login_at->equalTo(now()));
    }

    public function test_user_with_two_factor_goes_to_the_code_challenge(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $user->id);

        $this->assertGuest();
    }

    public function test_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'errada'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_rejects_an_inactive_user(): void
    {
        $user = User::factory()->admin()->inactive()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Seu acesso está desativado. Fale com o suporte da Weega.']);

        $this->assertGuest();
    }

    public function test_rejects_an_operator_whose_clients_are_all_inactive(): void
    {
        $operator = User::factory()->create();
        $operator->clients()->attach(Client::factory()->inactive()->create());

        $this->post(route('login.store'), ['email' => $operator->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_accepts_an_operator_with_one_active_client(): void
    {
        $operator = User::factory()->create();
        $operator->clients()->attach([Client::factory()->inactive()->create()->id, Client::factory()->create()->id]);

        $this->post(route('login.store'), ['email' => $operator->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($operator);
    }

    public function test_signs_out(): void
    {
        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
