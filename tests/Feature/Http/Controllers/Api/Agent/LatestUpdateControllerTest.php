<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Enums\ReleaseChannel;
use App\Models\AgentRelease;
use App\Models\Device;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LatestUpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::disk('s3')->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiresAt) => "https://s3.test/{$path}?expires={$expiresAt->getTimestamp()}",
        );
    }

    private function release(string $version, ReleaseChannel $channel, bool $published = true): AgentRelease
    {
        return AgentRelease::create([
            'version' => $version,
            'channel' => $channel,
            'storage_path' => AgentRelease::storagePathFor($version),
            'sha256' => hash('sha256', $version),
            'size_bytes' => 1000,
            'published_at' => $published ? now() : null,
        ]);
    }

    private function tokenFor(Device $device): string
    {
        return $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;
    }

    public function test_stable_device_receives_the_newest_stable_version_by_version_order(): void
    {
        $this->freezeSecond();
        $this->release('2.9.0', ReleaseChannel::Stable);
        $this->release('2.10.0', ReleaseChannel::Stable);
        $this->release('2.11.0', ReleaseChannel::Pilot);
        $device = Device::factory()->active()->create(['release_channel' => ReleaseChannel::Stable]);

        $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.updates.latest', ['current' => '2.0.0']))
            ->assertOk()
            ->assertJsonPath('version', '2.10.0')
            ->assertJsonPath('sha256', hash('sha256', '2.10.0'))
            ->assertJsonPath('download_url', 'https://s3.test/agent-releases/2.10.0/YantraAgente-Setup-2.10.0.exe?expires='.now()->addMinutes(10)->getTimestamp());
    }

    public function test_pilot_device_receives_the_pilot_version(): void
    {
        $this->release('2.0.0', ReleaseChannel::Stable);
        $this->release('2.1.0', ReleaseChannel::Pilot);
        $device = Device::factory()->active()->create(['release_channel' => ReleaseChannel::Pilot]);

        $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.updates.latest', ['current' => '2.0.0']))
            ->assertOk()
            ->assertJsonPath('version', '2.1.0');
    }

    public function test_returns_204_when_the_device_has_the_newest_version(): void
    {
        $this->release('2.0.0', ReleaseChannel::Stable);
        $this->release('2.1.0', ReleaseChannel::Stable, published: false);
        $device = Device::factory()->active()->create();

        $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.updates.latest', ['current' => '2.0.0']))
            ->assertNoContent();
    }

    public function test_rejects_a_current_version_outside_the_format(): void
    {
        $device = Device::factory()->active()->create();

        $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.updates.latest', ['current' => 'dois']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current');
    }
}
