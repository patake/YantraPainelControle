<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Uma tela já aberta não continua a funcionar para um usuário desativado (R-ACE-6) ou sem 2FA (R-ACE-5).
 * O teste faz o pedido HTTP real do Livewire, que reaplica os middlewares da rota.
 */
class LivewirePersistentMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Abre a tela e devolve o snapshot do componente da página.
     */
    private function openUsersPage(User $admin): string
    {
        $html = $this->actingAs($admin)->get(route('staff.users.index'))->assertOk()->getContent();

        preg_match('/wire:snapshot="([^"]+)"[^>]*wire:name="pages::staff\.users\.index"/', $html, $matches);
        $this->assertNotEmpty($matches, 'O snapshot da tela de usuários não apareceu no HTML.');

        return html_entity_decode($matches[1]);
    }

    private function callAction(string $snapshot, string $method, array $params = []): TestResponse
    {
        return $this->withHeaders(['X-Livewire' => 'true'])->postJson(route('default-livewire.update'), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => (object) [],
                'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
            ]],
        ]);
    }

    public function test_open_page_still_works_for_an_active_user(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $snapshot = $this->openUsersPage($admin);

        $this->callAction($snapshot, '$refresh')->assertOk();
    }

    public function test_deactivated_user_cannot_use_an_open_page(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $snapshot = $this->openUsersPage($admin);

        $admin->update(['active' => false]);

        $this->callAction($snapshot, '$refresh')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_whose_two_factor_was_reset_goes_back_to_the_wizard(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $snapshot = $this->openUsersPage($admin);

        $admin->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        $this->callAction($snapshot, '$refresh')->assertRedirect(route('onboarding'));
    }
}
