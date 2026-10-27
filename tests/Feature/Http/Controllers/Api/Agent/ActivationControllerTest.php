<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Models\Client;
use App\Models\Device;
use App\Models\License;
use App\Models\System;
use App\Support\ResponseSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_the_licensed_systems_with_the_free_seats(): void
    {
        $client = Client::factory()->create(['cnpj' => '11222333000181', 'trade_name' => 'Hospital Alfa']);
        $cardio = System::factory()->create(['code' => 'YC_PRT', 'name' => 'Yantra Cardio Printer']);
        $hub = System::factory()->create(['code' => 'YO_HUB', 'name' => 'Yantra Hub']);
        License::factory()->create(['client_id' => $client->id, 'system_id' => $cardio->id, 'quantity' => 3]);
        License::factory()->create(['client_id' => $client->id, 'system_id' => $hub->id, 'quantity' => 1]);
        Device::factory()->count(2)->create(['client_id' => $client->id, 'system_id' => $cardio->id]);
        Device::factory()->active()->create(['client_id' => $client->id, 'system_id' => $hub->id]);

        $this->withHeader('X-Activation-Code', $client->activation_code)
            ->getJson(route('agent.activation', '11222333000181'))
            ->assertOk()
            ->assertHeader(ResponseSigner::HEADER_SIGNATURE)
            ->assertExactJson([
                'client' => ['trade_name' => 'Hospital Alfa'],
                'systems' => [
                    ['code' => 'YC_PRT', 'name' => 'Yantra Cardio Printer', 'available' => 1],
                    ['code' => 'YO_HUB', 'name' => 'Yantra Hub', 'available' => 0],
                ],
            ]);
    }

    public function test_hides_a_license_after_the_validity_date(): void
    {
        $license = License::factory()->expired()->create();

        $this->withHeader('X-Activation-Code', $license->client->activation_code)
            ->getJson(route('agent.activation', $license->client->cnpj))
            ->assertOk()
            ->assertJsonPath('systems', []);
    }

    /**
     * R-LIC-11: o CNPJ é público, e a consulta pede também o código de ativação do cliente.
     */
    public function test_needs_the_activation_code_of_the_client(): void
    {
        $client = Client::factory()->create(['cnpj' => '11222333000181', 'activation_code' => 'ABCD-2345']);
        $other = Client::factory()->create();
        $url = route('agent.activation', '11222333000181');

        $this->getJson($url)->assertNotFound();
        $this->withHeader('X-Activation-Code', 'ABCD-2346')->getJson($url)->assertNotFound()
            ->assertJsonPath('message', 'Nenhum cliente ativo tem este CNPJ com este código de ativação.');
        $this->withHeader('X-Activation-Code', $other->activation_code)->getJson($url)->assertNotFound();

        // O técnico digita sem o hífen e em minúsculas.
        $this->withHeader('X-Activation-Code', 'abcd2345')->getJson($url)->assertOk();

        // O código novo invalida o anterior.
        $client->regenerateActivationCode();
        $this->withHeader('X-Activation-Code', 'ABCD-2345')->getJson($url)->assertNotFound();
        $this->withHeader('X-Activation-Code', $client->fresh()->activation_code)->getJson($url)->assertOk();
    }

    public function test_returns_404_for_an_unknown_inactive_or_invalid_cnpj(): void
    {
        Client::factory()->inactive()->create(['cnpj' => '11222333000181']);

        $this->getJson(route('agent.activation', '11222333000181'))->assertNotFound();
        $this->getJson(route('agent.activation', '45997418000153'))->assertNotFound();
        $this->getJson(route('agent.activation', '123'))->assertNotFound();
    }

    public function test_returns_429_after_10_attempts_in_one_hour(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->getJson(route('agent.activation', '45997418000153'))->assertNotFound();
        }

        $this->getJson(route('agent.activation', '45997418000153'))->assertTooManyRequests();
    }
}
