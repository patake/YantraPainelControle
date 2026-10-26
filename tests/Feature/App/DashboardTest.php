<?php

namespace Tests\Feature\App;

use App\Models\Client;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-ACE-2: o operador vê só os dados dos clientes vinculados.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_sees_only_the_devices_of_the_linked_clients(): void
    {
        $own = Client::factory()->create(['trade_name' => 'Hospital do Operador']);
        $other = Client::factory()->create(['trade_name' => 'Outro Hospital']);
        Device::factory()->active()->create(['client_id' => $own->id, 'hostname' => 'SALA-PROPRIA']);
        Device::factory()->active()->create(['client_id' => $other->id, 'hostname' => 'SALA-ALHEIA']);

        $operator = User::factory()->withTwoFactor()->create();
        $operator->clients()->attach($own);

        $this->actingAs($operator)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('SALA-PROPRIA')
            ->assertDontSee('SALA-ALHEIA')
            ->assertDontSee('Outro Hospital');
    }
}
