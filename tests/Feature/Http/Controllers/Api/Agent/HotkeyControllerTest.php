<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E7: o assistente do agente grava a combinação de teclas aceita pelo Windows (R-EXA-11).
 */
class HotkeyControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(Device $device): string
    {
        return $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;
    }

    public function test_stores_the_normalized_combination_and_raises_the_parameter_version(): void
    {
        $device = Device::factory()->active()->screenCapture()->create();
        $version = $device->config_version;

        $response = $this->withToken($this->tokenFor($device))
            ->putJson(route('agent.settings.hotkey'), ['hotkey' => 'ctrl + shift + y'])
            ->assertOk()
            ->assertHeader('X-Yantra-Signature')
            ->assertJsonPath('hotkey', 'Ctrl+Shift+Y')
            ->assertJsonPath('config_version', $device->fresh()->config_version);

        $this->assertGreaterThan($version, $response->json('config_version'));
        $this->assertSame('Ctrl+Shift+Y', $device->settings()->value('hotkey'));

        $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.config'))
            ->assertJsonPath('hotkey', 'Ctrl+Shift+Y');
    }

    public function test_parameters_bring_ctrl_y_when_the_device_has_no_combination(): void
    {
        $device = Device::factory()->active()->screenCapture()->create();

        $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.config'))
            ->assertJsonPath('hotkey', 'Ctrl+Y');
    }

    public function test_rejects_a_combination_without_ctrl_alt_or_win(): void
    {
        $device = Device::factory()->active()->screenCapture()->create();

        $this->withToken($this->tokenFor($device))
            ->putJson(route('agent.settings.hotkey'), ['hotkey' => 'Shift+Y'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hotkey');
    }

    public function test_only_the_screen_capture_system_uses_the_combination(): void
    {
        $printer = Device::factory()->active()->create();

        $this->withToken($this->tokenFor($printer))
            ->putJson(route('agent.settings.hotkey'), ['hotkey' => 'Ctrl+Y'])
            ->assertConflict();

        $this->withToken($this->tokenFor($printer))
            ->getJson(route('agent.config'))
            ->assertJsonPath('hotkey', null);
    }

    public function test_blocked_device_cannot_change_the_combination(): void
    {
        $device = Device::factory()->blocked()->screenCapture()->create();

        $this->withToken($this->tokenFor($device))
            ->putJson(route('agent.settings.hotkey'), ['hotkey' => 'Ctrl+Y'])
            ->assertForbidden();
    }
}
