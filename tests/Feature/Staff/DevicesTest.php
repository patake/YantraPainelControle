<?php

namespace Tests\Feature\Staff;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-LIC-4: só a Staff Weega ativa e bloqueia equipamentos.
 */
class DevicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_activates_a_trial_and_the_decision_is_recorded(): void
    {
        $device = Device::factory()->create();
        $support = User::factory()->support()->withTwoFactor()->create();
        $this->actingAs($support);

        Livewire::test('pages::staff.devices.index')->call('activate', $device->id);

        $device->refresh();
        $this->assertSame(DeviceStatus::Active, $device->status);
        $this->assertNull($device->trial_ends_at);
        $this->assertSame($support->id, $device->decided_by);
    }

    public function test_blocks_a_device(): void
    {
        $device = Device::factory()->active()->create();
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        Livewire::test('pages::staff.devices.index')->call('block', $device->id);

        $this->assertSame(DeviceStatus::Blocked, $device->fresh()->status);
    }

    public function test_does_not_reactivate_a_blocked_device_without_a_free_seat(): void
    {
        $license = License::factory()->create(['quantity' => 1]);
        $owner = ['client_id' => $license->client_id, 'system_id' => $license->system_id];
        $blocked = Device::factory()->blocked()->create($owner);
        Device::factory()->active()->create($owner);
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.devices.index')->call('activate', $blocked->id);

        $this->assertSame(DeviceStatus::Blocked, $blocked->fresh()->status);
    }

    public function test_filters_by_status(): void
    {
        Device::factory()->create(['hostname' => 'SALA-TRIAL']);
        Device::factory()->active()->create(['hostname' => 'SALA-ATIVA']);
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        Livewire::test('pages::staff.devices.index')
            ->set('status', 'trial')
            ->assertSee('SALA-TRIAL')
            ->assertDontSee('SALA-ATIVA');
    }

    public function test_forbids_an_operator(): void
    {
        $this->actingAs(User::factory()->operatorWithClient()->withTwoFactor()->create())
            ->get(route('staff.devices.index'))
            ->assertForbidden();
    }
}
