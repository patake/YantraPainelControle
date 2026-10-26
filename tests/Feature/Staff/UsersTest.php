<?php

namespace Tests\Feature\Staff;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_an_operator_linked_to_clients_and_sends_the_password_link(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.users.form')
            ->set('name', 'Operador Novo')
            ->set('email', 'Operador@Hospital.com.br')
            ->set('role', 'operator')
            ->set('clientIds', [$client->id])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'operador@hospital.com.br')->sole();
        $this->assertSame(UserRole::Operator, $user->role);
        $this->assertSame([$client->id], $user->clients()->pluck('clients.id')->all());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_requires_at_least_one_client_for_an_operator(): void
    {
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.users.form')
            ->set('name', 'Operador Novo')
            ->set('email', 'novo@hospital.com.br')
            ->set('role', 'operator')
            ->call('save')
            ->assertHasErrors(['clientIds' => 'required']);
    }

    public function test_staff_user_keeps_no_client_link(): void
    {
        $client = Client::factory()->create();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.users.form')
            ->set('name', 'Suporte Novo')
            ->set('email', 'suporte2@weega.com.br')
            ->set('role', 'support')
            ->set('clientIds', [$client->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, User::where('email', 'suporte2@weega.com.br')->sole()->clients()->count());
    }

    public function test_support_cannot_create_users(): void
    {
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        $this->get(route('staff.users.create'))->assertForbidden();
    }

    public function test_admin_resets_the_two_factor_of_another_user(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.users.index')->call('resetTwoFactor', $user->id);

        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertFalse($user->fresh()->hasConfirmedTwoFactor());
    }

    public function test_admin_cannot_reset_the_own_two_factor(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($admin);

        Livewire::test('pages::staff.users.index')
            ->call('resetTwoFactor', $admin->id)
            ->assertForbidden();
    }
}
