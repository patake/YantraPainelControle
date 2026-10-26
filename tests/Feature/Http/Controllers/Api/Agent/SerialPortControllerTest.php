<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * E8 e R-EXA-13: o assistente da porta COM do agente informa a porta que achou.
 * Só o sistema de porta serial (YO HUB) usa a porta COM.
 */
class SerialPortControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(Device $device): string
    {
        return $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;
    }

    public function test_stores_the_port_found_by_the_wizard_and_the_agent_keeps_it(): void
    {
        $device = Device::factory()->active()->serialReader()->create();
        $version = $device->config_version;
        $token = $this->tokenFor($device);

        $response = $this->withToken($token)
            ->putJson(route('agent.settings.serial-port'), ['port' => 'com4', 'adapter' => 'Prolific USB-to-Serial', 'test_passed' => true])
            ->assertOk()
            ->assertHeader('X-Yantra-Signature')
            ->assertJsonPath('port', 'COM4')
            ->assertJsonPath('adapter', 'Prolific USB-to-Serial')
            ->assertJsonPath('test_passed', true);

        $this->assertGreaterThan($version, $response->json('config_version'));

        $settings = $device->settings()->sole();
        $this->assertSame('COM4', $settings->serial_port);
        $this->assertNotNull($settings->serial_reported_at);

        // O próximo GET /config devolve a porta do assistente, e não a porta antiga.
        $this->withToken($token)->getJson(route('agent.config'))->assertJsonPath('serial_port', 'COM4');
    }

    public function test_rejects_a_port_outside_the_com_format(): void
    {
        $device = Device::factory()->active()->serialReader()->create();

        $this->withToken($this->tokenFor($device))
            ->putJson(route('agent.settings.serial-port'), ['port' => '/dev/ttyUSB0', 'test_passed' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('port');
    }

    public function test_only_the_serial_system_uses_the_port(): void
    {
        $printer = Device::factory()->active()->create();
        $printer->settings()->create(['serial_port' => 'COM9']);
        $token = $this->tokenFor($printer);

        $this->withToken($token)
            ->putJson(route('agent.settings.serial-port'), ['port' => 'COM3', 'test_passed' => true])
            ->assertConflict();

        $this->withToken($token)->getJson(route('agent.config'))->assertJsonPath('serial_port', null);
    }

    public function test_staff_page_shows_the_wizard_result_and_a_manual_change_clears_it(): void
    {
        $device = Device::factory()->active()->serialReader()->create();
        $device->settings()->create([
            'serial_port' => 'COM4', 'serial_adapter' => 'Prolific USB-to-Serial',
            'serial_test_passed' => true, 'serial_reported_at' => now(),
        ]);
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.devices.show', ['device' => $device])
            ->assertSee('Prolific USB-to-Serial')
            ->assertSee('teste de comunicação aprovado')
            ->set('serialPort', 'COM5')
            ->call('save')
            ->assertHasNoErrors();

        $settings = $device->settings()->sole();
        $this->assertSame('COM5', $settings->serial_port);
        $this->assertNull($settings->serial_adapter);
        $this->assertNull($settings->serial_reported_at);
    }
}
