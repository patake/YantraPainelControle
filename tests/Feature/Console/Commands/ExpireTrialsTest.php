<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-LIC-3: o trial termina 7 dias depois do registro.
 */
class ExpireTrialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_only_the_trials_past_the_deadline(): void
    {
        $overdue = Device::factory()->create(['trial_ends_at' => now()->subMinute()]);
        $running = Device::factory()->create(['trial_ends_at' => now()->addDay()]);
        $active = Device::factory()->active()->create();

        $this->artisan('devices:expire-trials')->expectsOutput('Trials expirados: 1.')->assertSuccessful();

        $this->assertSame(DeviceStatus::Expired, $overdue->fresh()->status);
        $this->assertSame(DeviceStatus::Trial, $running->fresh()->status);
        $this->assertSame(DeviceStatus::Active, $active->fresh()->status);
    }
}
