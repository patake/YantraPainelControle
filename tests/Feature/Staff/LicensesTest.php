<?php

namespace Tests\Feature\Staff;

use App\Models\Client;
use App\Models\Device;
use App\Models\License;
use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LicensesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_adds_a_license_package_to_a_client(): void
    {
        $client = Client::factory()->create();
        $system = System::factory()->create();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.clients.show', ['client' => $client])
            ->set('licenseSystemId', $system->id)
            ->set('licenseQuantity', 10)
            ->set('licenseValidUntil', '2027-12-31')
            ->call('saveLicense')
            ->assertHasNoErrors();

        $license = License::sole();
        $this->assertSame(10, $license->quantity);
        $this->assertSame('2027-12-31', $license->valid_until->format('Y-m-d'));
    }

    public function test_rejects_a_second_package_of_the_same_system(): void
    {
        $license = License::factory()->create();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.clients.show', ['client' => $license->client])
            ->set('licenseSystemId', $license->system_id)
            ->set('licenseQuantity', 3)
            ->call('saveLicense')
            ->assertHasErrors(['licenseSystemId' => 'O cliente já tem um pacote deste sistema. Altere o pacote existente.']);
    }

    public function test_does_not_reduce_a_package_below_the_licenses_in_use(): void
    {
        $license = License::factory()->create(['quantity' => 5]);
        Device::factory()->count(3)->create(['client_id' => $license->client_id, 'system_id' => $license->system_id]);
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.clients.show', ['client' => $license->client])
            ->call('editLicense', $license->id)
            ->set('licenseQuantity', 2)
            ->call('saveLicense')
            ->assertHasErrors(['licenseQuantity' => 'O cliente usa 3 licenças deste sistema. Bloqueie equipamentos antes de reduzir o pacote.']);

        $this->assertSame(5, $license->fresh()->quantity);
    }

    public function test_support_cannot_change_licenses(): void
    {
        $client = Client::factory()->create();
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        Livewire::test('pages::staff.clients.show', ['client' => $client])
            ->set('licenseSystemId', System::factory()->create()->id)
            ->set('licenseQuantity', 1)
            ->call('saveLicense')
            ->assertForbidden();
    }

    public function test_admin_changes_the_identity_of_a_system(): void
    {
        $system = System::factory()->create(['name' => 'Nome antigo']);
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.systems.index')
            ->call('edit', $system->id)
            ->set('name', 'Yantra Cardio Printer')
            ->set('color', '#1E5FA8')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Yantra Cardio Printer', $system->fresh()->name);
        $this->assertSame('#1e5fa8', $system->fresh()->branding['color']);
    }

    public function test_support_cannot_change_a_system(): void
    {
        $system = System::factory()->create();
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        Livewire::test('pages::staff.systems.index')
            ->call('edit', $system->id)
            ->assertForbidden();
    }
}
