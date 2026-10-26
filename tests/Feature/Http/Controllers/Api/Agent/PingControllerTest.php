<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Models\ApiRequestLog;
use App\Models\Device;
use App\Support\ResponseSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('agent.ping'))->assertUnauthorized();
    }

    public function test_returns_401_when_the_token_is_invalid(): void
    {
        $this->withToken('1|token-invalido')->getJson(route('agent.ping'))->assertUnauthorized();
    }

    public function test_returns_403_when_the_token_lacks_the_agent_ability(): void
    {
        Sanctum::actingAs(Device::factory()->create(), ['outra']);

        $this->getJson(route('agent.ping'))
            ->assertForbidden()
            ->assertJson(['message' => 'O token não pertence a um equipamento.']);
    }

    public function test_returns_the_device_state_for_a_device_token(): void
    {
        $device = Device::factory()->active()->create(['config_version' => 7]);
        $token = $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;

        $this->withToken($token)->getJson(route('agent.ping'))
            ->assertOk()
            ->assertJson([
                'device_id' => $device->id,
                'status' => 'active',
                'config_version' => 7,
            ]);
    }

    public function test_signs_the_response_with_the_published_public_key(): void
    {
        $token = Device::factory()->create()->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;
        $publicKey = $this->getJson(route('agent.public-key'))->json('public_key');

        $response = $this->withToken($token)->getJson(route('agent.ping'))->assertOk();

        $timestamp = (int) $response->headers->get(ResponseSigner::HEADER_TIMESTAMP);
        $signature = (string) $response->headers->get(ResponseSigner::HEADER_SIGNATURE);

        $this->assertSame('test', $response->headers->get(ResponseSigner::HEADER_KEY_ID));
        $this->assertTrue(ResponseSigner::verify($timestamp, $response->getContent(), $signature, $publicKey));
        $this->assertFalse(ResponseSigner::verify($timestamp + 1, $response->getContent(), $signature, $publicKey));
    }

    public function test_updates_the_last_seen_time_of_the_device(): void
    {
        $device = Device::factory()->create(['last_seen_at' => now()->subHour()]);
        $token = $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;

        $this->freezeSecond();
        $this->withToken($token)->getJson(route('agent.ping'))->assertOk();

        $this->assertTrue($device->fresh()->last_seen_at->equalTo(now()));
    }

    public function test_logs_the_request_with_the_device_and_route(): void
    {
        $device = Device::factory()->create();
        $token = $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;

        $this->withToken($token)->getJson(route('agent.ping'))->assertOk();

        $this->assertDatabaseHas(ApiRequestLog::class, [
            'device_id' => $device->id,
            'method' => 'GET',
            'route' => '/api/v1/agent/ping',
            'status' => 200,
        ]);
    }

    public function test_logs_the_refused_request_without_a_device(): void
    {
        $this->getJson(route('agent.ping'))->assertUnauthorized();

        $this->assertDatabaseHas(ApiRequestLog::class, [
            'device_id' => null,
            'route' => '/api/v1/agent/ping',
            'status' => 401,
        ]);
    }
}
