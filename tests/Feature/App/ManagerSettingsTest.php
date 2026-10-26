<?php

namespace Tests\Feature\App;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-ACE-14: o gestor do hospital personaliza as teclas da captura de tela (R-EXA-11)
 * dos equipamentos YO SRC dos clientes vinculados.
 */
class ManagerSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->managerWithClient()->withTwoFactor()->create();
        $this->device = Device::factory()->active()->screenCapture()->create([
            'client_id' => $this->manager->clients()->value('clients.id'),
            'hostname' => 'RECEPCAO-01',
        ]);
    }

    public function test_manager_uses_the_client_area_and_sees_the_settings_menu(): void
    {
        $this->actingAs($this->manager)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee(route('app.settings'));
    }

    public function test_manager_changes_the_keys_of_a_screen_capture_device(): void
    {
        $version = $this->device->config_version;
        $this->actingAs($this->manager);

        Livewire::test('pages::app.settings')
            ->assertSee('RECEPCAO-01')
            ->assertSet("hotkeys.{$this->device->id}", 'Ctrl+Y')
            ->set("hotkeys.{$this->device->id}", 'alt + f9')
            ->call('saveHotkey', $this->device->id)
            ->assertHasNoErrors()
            ->assertSet("hotkeys.{$this->device->id}", 'Alt+F9');

        $this->assertSame('Alt+F9', $this->device->settings()->value('hotkey'));
        $this->assertGreaterThan($version, $this->device->fresh()->config_version);
    }

    public function test_rejects_a_combination_without_ctrl_alt_or_win(): void
    {
        $this->actingAs($this->manager);

        Livewire::test('pages::app.settings')
            ->set("hotkeys.{$this->device->id}", 'Shift+Y')
            ->call('saveHotkey', $this->device->id)
            ->assertHasErrors("hotkeys.{$this->device->id}");
    }

    public function test_lists_only_screen_capture_devices_of_the_linked_clients(): void
    {
        $printer = Device::factory()->active()->create(['client_id' => $this->device->client_id, 'hostname' => 'CARDIO-SALA-02']);
        $foreign = Device::factory()->active()->screenCapture()->create(['hostname' => 'OUTRO-HOSPITAL']);
        $this->actingAs($this->manager);

        Livewire::test('pages::app.settings')
            ->assertSee('RECEPCAO-01')
            ->assertDontSee('CARDIO-SALA-02')
            ->assertDontSee('OUTRO-HOSPITAL')
            ->set("hotkeys.{$foreign->id}", 'Ctrl+K')
            ->call('saveHotkey', $foreign->id)
            ->assertNotFound();

        $this->assertNull($foreign->settings()->value('hotkey'));
        $this->assertNull($printer->settings()->value('hotkey'));
    }

    public function test_operator_cannot_open_the_settings(): void
    {
        $this->actingAs(User::factory()->operatorWithClient()->withTwoFactor()->create())
            ->get(route('app.settings'))
            ->assertForbidden();
    }

    public function test_admin_links_a_manager_to_a_client(): void
    {
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.users.form')
            ->set('name', 'Gestora Nova')
            ->set('email', 'gestora@hospital.com.br')
            ->set('role', 'manager')
            ->call('save')
            ->assertHasErrors('clientIds')
            ->set('clientIds', [$this->device->client_id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$this->device->client_id], User::where('email', 'gestora@hospital.com.br')->sole()->clients()->pluck('clients.id')->all());
    }
}
