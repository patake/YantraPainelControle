<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_the_name_and_keeps_the_email(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create(['email' => 'admin@weega.com.br']);
        $this->actingAs($user);

        Livewire::test('pages::settings.profile')
            ->set('name', 'Nome Novo')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('Nome Novo', $user->fresh()->name);
        $this->assertSame('admin@weega.com.br', $user->fresh()->email);
    }
}
