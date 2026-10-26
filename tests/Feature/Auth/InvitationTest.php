<?php

namespace Tests\Feature\Auth;

use App\Actions\Users\SendUserInvitation;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-ACE-13: o convite do usuário novo vale por 7 dias.
 */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'senha-forte-do-convite';

    private function invite(User $user): string
    {
        Notification::fake();
        app(SendUserInvitation::class)->handle($user);

        $url = null;
        Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use (&$url) {
            $url = $notification->url;

            return $notification->days === 7;
        });

        return $url;
    }

    public function test_user_creates_the_password_with_the_invitation_link(): void
    {
        $user = User::factory()->operatorWithClient()->create();
        $url = $this->invite($user);

        $this->get($url)->assertOk()->assertSee($user->email);

        $this->post($url, ['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNull($user->invited_at);
    }

    public function test_link_still_works_on_the_sixth_day_and_expires_after_7_days(): void
    {
        $user = User::factory()->operatorWithClient()->create();
        $url = $this->invite($user);

        $this->travel(6)->days();
        $this->get($url)->assertSee('Criar a senha');

        $this->travel(2)->days();
        $this->get($url)->assertSee('Convite inválido');
        $this->post($url, ['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSee('Convite inválido');
        $this->assertFalse(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_link_works_once(): void
    {
        $user = User::factory()->operatorWithClient()->create();
        $url = $this->invite($user);

        $this->post($url, ['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD]);

        $this->get($url)->assertSee('Convite inválido');
    }

    public function test_a_new_invitation_replaces_the_old_link(): void
    {
        $user = User::factory()->operatorWithClient()->create();
        $oldUrl = $this->invite($user);
        $this->travel(1)->minute();
        $newUrl = $this->invite($user);

        $this->get($oldUrl)->assertSee('Convite inválido');
        $this->get($newUrl)->assertSee('Criar a senha');
    }

    public function test_rejects_a_tampered_link(): void
    {
        $user = User::factory()->operatorWithClient()->create();
        $other = User::factory()->operatorWithClient()->create();
        $url = $this->invite($user);
        $this->invite($other);

        $this->get(str_replace("/convite/{$user->id}", "/convite/{$other->id}", $url))->assertSee('Convite inválido');
    }

    public function test_password_needs_12_characters(): void
    {
        $user = User::factory()->operatorWithClient()->create();
        $url = $this->invite($user);

        $this->post($url, ['password' => 'curta', 'password_confirmation' => 'curta'])->assertSessionHasErrors('password');
        $this->assertNotNull($user->fresh()->invited_at);
    }

    public function test_admin_resends_a_pending_invitation(): void
    {
        $user = User::factory()->operatorWithClient()->create();
        $this->invite($user);
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.users.index')
            ->assertSee('Convite pendente')
            ->call('resendInvitation', $user->id)
            ->assertHasNoErrors();

        Notification::assertSentToTimes($user, UserInvitation::class, 2);
    }
}
