<?php

namespace Tests\Feature\Models;

use App\Models\Device;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-LIC-1 e R-LIC-2: licenças em uso e licença livre para um registro novo.
 */
class LicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_only_trial_and_active_devices_of_the_same_client_and_system(): void
    {
        $license = License::factory()->create(['quantity' => 5]);
        $owner = ['client_id' => $license->client_id, 'system_id' => $license->system_id];

        Device::factory()->create($owner);
        Device::factory()->active()->create($owner);
        Device::factory()->blocked()->create($owner);
        Device::factory()->expired()->create($owner);
        Device::factory()->active()->create(['client_id' => $license->client_id]);
        Device::factory()->active()->create(['system_id' => $license->system_id]);

        $this->assertSame(2, $license->usedCount());
    }

    public function test_has_no_available_seat_when_every_license_is_in_use(): void
    {
        $license = License::factory()->create(['quantity' => 2]);
        Device::factory()->count(2)->create(['client_id' => $license->client_id, 'system_id' => $license->system_id]);

        $this->assertFalse($license->hasAvailableSeat());
    }

    public function test_has_an_available_seat_below_the_quantity(): void
    {
        $license = License::factory()->create(['quantity' => 2]);
        Device::factory()->create(['client_id' => $license->client_id, 'system_id' => $license->system_id]);

        $this->assertTrue($license->hasAvailableSeat());
    }

    public function test_has_no_available_seat_after_the_validity_date(): void
    {
        $license = License::factory()->expired()->create(['quantity' => 5]);

        $this->assertFalse($license->hasAvailableSeat());
    }
}
