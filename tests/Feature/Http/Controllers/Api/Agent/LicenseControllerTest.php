<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Enums\SystemFeature;
use App\Models\Device;
use App\Models\System;
use App\Support\ResponseSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(Device $device): string
    {
        return $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;
    }

    public function test_returns_the_signed_license_of_a_trial_device(): void
    {
        $this->freezeSecond();
        $system = System::factory()->create([
            'code' => 'YC_PRT', 'name' => 'Yantra Cardio Printer',
            'feature' => SystemFeature::Printer, 'branding' => ['color' => '#b8323a'],
        ]);
        $device = Device::factory()->create(['system_id' => $system->id, 'trial_ends_at' => now()->addDays(5)]);

        $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.license'))
            ->assertOk()
            ->assertHeader(ResponseSigner::HEADER_SIGNATURE)
            ->assertExactJson([
                'device_id' => $device->id,
                'status' => 'trial',
                'system' => [
                    'code' => 'YC_PRT',
                    'name' => 'Yantra Cardio Printer',
                    'feature' => 'printer',
                    'branding' => ['color' => '#b8323a'],
                ],
                'trial_ends_at' => now()->addDays(5)->toIso8601String(),
                'checked_at' => now()->toIso8601String(),
                'message' => null,
            ]);
    }

    public function test_answers_a_blocked_device_with_the_blocked_message(): void
    {
        $device = Device::factory()->blocked()->create();

        $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.license'))
            ->assertOk()
            ->assertJsonPath('status', 'blocked')
            ->assertJsonPath('message', 'A licença deste equipamento está bloqueada. Fale com a Weega Technologies.');
    }

    public function test_answers_an_expired_trial_with_the_expired_message(): void
    {
        $device = Device::factory()->expired()->create();

        $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.license'))
            ->assertOk()
            ->assertJsonPath('status', 'expired')
            ->assertJsonPath('message', 'O período de teste terminou. Fale com a Weega Technologies para ativar a licença.');
    }

    public function test_returns_401_without_a_token(): void
    {
        $this->getJson(route('agent.license'))->assertUnauthorized();
    }
}
