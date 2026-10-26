<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_staff_to_the_staff_area(): void
    {
        $this->actingAs(User::factory()->support()->withTwoFactor()->create())
            ->get(route('home'))
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_sends_an_operator_to_the_client_area(): void
    {
        $this->actingAs(User::factory()->operatorWithClient()->withTwoFactor()->create())
            ->get(route('home'))
            ->assertRedirect(route('app.dashboard'));
    }

    public function test_forbids_an_operator_in_the_staff_area(): void
    {
        $this->actingAs(User::factory()->operatorWithClient()->withTwoFactor()->create())
            ->get(route('staff.clients.index'))
            ->assertForbidden();
    }

    public function test_forbids_staff_in_the_client_area(): void
    {
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())
            ->get(route('app.dashboard'))
            ->assertForbidden();
    }

    public function test_signs_out_a_user_deactivated_during_the_session(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user);
        $user->update(['active' => false]);

        $this->get(route('staff.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
