<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Enums\DeviceStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Device;
use App\Models\License;
use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create(['cnpj' => '11222333000181']);
        $system = System::factory()->create(['code' => 'YC_PRT', 'name' => 'Yantra Cardio Printer']);
        License::factory()->create(['client_id' => $this->client->id, 'system_id' => $system->id, 'quantity' => 1]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cnpj' => '11.222.333/0001-81',
            'activation_code' => $this->client->activation_code,
            'system' => 'YC_PRT',
            'signature' => str_repeat('a1', 32),
            'hostname' => 'CARDIO-SALA-04',
            'machine_type' => 'Desktop',
            'domain' => 'HOSP.LOCAL',
            'macs' => ['00:1A:2B:3C:4D:5E'],
            'windows_version' => 'Windows 11 Pro 24H2',
            'agent_version' => '2.0.0',
        ], $overrides);
    }

    public function test_registers_the_device_in_trial_and_returns_a_working_token(): void
    {
        $this->freezeSecond();

        $response = $this->postJson(route('agent.register'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('status', 'trial')
            ->assertJsonPath('trial_ends_at', now()->addDays(7)->toIso8601String());

        $device = Device::sole();
        $this->assertSame($this->client->id, $device->client_id);
        $this->assertSame(DeviceStatus::Trial, $device->status);
        $this->assertNotNull($device->settings);

        $this->withToken($response->json('token'))
            ->getJson(route('agent.ping'))
            ->assertOk()
            ->assertJsonPath('device_id', $device->id);
    }

    public function test_returns_404_for_a_cnpj_without_an_active_client(): void
    {
        $this->postJson(route('agent.register'), $this->payload(['cnpj' => '45997418000153']))
            ->assertNotFound()
            ->assertJsonPath('message', 'Nenhum cliente ativo tem este CNPJ com este código de ativação.');
    }

    /**
     * R-LIC-11: sem o código de ativação do cliente, o registro não ocupa uma licença.
     */
    public function test_needs_the_activation_code_of_the_client(): void
    {
        $this->postJson(route('agent.register'), $this->payload(['activation_code' => 'ERRA-DO22']))
            ->assertNotFound()
            ->assertJsonPath('message', 'Nenhum cliente ativo tem este CNPJ com este código de ativação.');
        $this->postJson(route('agent.register'), $this->payload(['activation_code' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('activation_code');
        $this->assertSame(0, $this->client->devices()->count());

        $this->postJson(route('agent.register'), $this->payload(['activation_code' => strtolower(str_replace('-', '', $this->client->activation_code))]))
            ->assertCreated();
    }

    public function test_returns_422_for_a_system_without_license(): void
    {
        $this->postJson(route('agent.register'), $this->payload(['system' => 'YO_HUB']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['system' => 'O cliente não tem licença do sistema YO_HUB.']);
    }

    public function test_returns_409_when_every_seat_is_in_use(): void
    {
        $this->postJson(route('agent.register'), $this->payload())->assertCreated();

        $this->postJson(route('agent.register'), $this->payload(['signature' => str_repeat('b2', 32)]))
            ->assertConflict()
            ->assertJsonPath('message', 'Todas as licenças do sistema Yantra Cardio Printer estão em uso. Fale com a Weega.');

        $this->assertSame(1, Device::count());
    }

    public function test_reinstall_on_the_same_client_keeps_the_device_and_revokes_the_old_token(): void
    {
        $first = $this->postJson(route('agent.register'), $this->payload())->assertCreated();
        Device::sole()->block(User::factory()->admin()->create());

        $second = $this->postJson(route('agent.register'), $this->payload(['hostname' => 'CARDIO-SALA-04B']))
            ->assertOk()
            ->assertJsonPath('device_id', $first->json('device_id'))
            ->assertJsonPath('status', 'blocked');

        $this->assertSame('CARDIO-SALA-04B', Device::sole()->hostname);
        $this->withToken($first->json('token'))->getJson(route('agent.ping'))->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($second->json('token'))->getJson(route('agent.ping'))->assertOk();
    }

    public function test_marks_a_clone_suspicion_when_the_signature_belongs_to_another_client(): void
    {
        $other = Device::factory()->create(['signature' => str_repeat('a1', 32)]);

        $this->postJson(route('agent.register'), $this->payload())
            ->assertConflict()
            ->assertJsonPath('message', 'Este equipamento já está registrado em outro cliente. Fale com a Weega.');

        $log = AuditLog::where('action', 'clone_suspected')->sole();
        $this->assertSame($other->id, $log->auditable_id);
        $this->assertSame('11222333000181', $log->new_values['attempted_cnpj']);
    }

    public function test_rejects_an_invalid_signature_and_cnpj(): void
    {
        $this->postJson(route('agent.register'), $this->payload(['signature' => 'curta', 'cnpj' => '11222333000182']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signature', 'cnpj']);
    }
}
