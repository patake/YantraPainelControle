<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_forgot_password_screen(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_sends_the_reset_link_to_a_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /**
     * R-ACE-9: ninguém descobre quais e-mails existem.
     */
    public function test_answers_the_same_message_for_an_unknown_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $message = 'Se o e-mail existe no cadastro, o link chega em poucos minutos.';

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status', $message);
        $this->post(route('password.email'), ['email' => 'ninguem@exemplo.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $message);
    }

    /**
     * R-ACE-8: senha com no mínimo 12 caracteres.
     */
    public function test_rejects_a_new_password_shorter_than_12_characters(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'curta12345',
                'password_confirmation' => 'curta12345',
            ])->assertSessionHasErrors('password');

            return true;
        });
    }

    public function test_resets_the_password_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'senha-nova-segura-123',
                'password_confirmation' => 'senha-nova-segura-123',
            ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('senha-nova-segura-123', $user->fresh()->password));
    }
}
