<?php

namespace Tests\Feature\Staff;

use App\Enums\ReleaseChannel;
use App\Models\AgentRelease;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReleasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_publishes_an_installer_in_the_s3_releases_folder(): void
    {
        Storage::fake('s3');
        $installer = UploadedFile::fake()->createWithContent('YantraAgente-Setup.exe', 'conteudo do instalador');
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.releases.index')
            ->set('version', '2.1.0')
            ->set('channel', 'pilot')
            ->set('notes', 'Captura de tela')
            ->set('installer', $installer)
            ->call('save')
            ->assertHasNoErrors();

        $release = AgentRelease::sole();
        $this->assertSame('agent-releases/2.1.0/YantraAgente-Setup-2.1.0.exe', $release->storage_path);
        $this->assertSame(hash('sha256', 'conteudo do instalador'), $release->sha256);
        $this->assertSame(ReleaseChannel::Pilot, $release->channel);
        Storage::disk('s3')->assertExists($release->storage_path);
    }

    public function test_rejects_a_repeated_version_and_a_file_that_is_not_exe(): void
    {
        Storage::fake('s3');
        AgentRelease::create([
            'version' => '2.0.0', 'channel' => ReleaseChannel::Stable, 'storage_path' => 'x',
            'sha256' => str_repeat('a', 64), 'size_bytes' => 1, 'published_at' => now(),
        ]);
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.releases.index')
            ->set('version', '2.0.0')
            ->set('installer', UploadedFile::fake()->create('notas.pdf', 10))
            ->call('save')
            ->assertHasErrors(['version' => 'unique', 'installer']);
    }

    public function test_promotes_a_pilot_version_to_stable(): void
    {
        $release = AgentRelease::create([
            'version' => '2.1.0', 'channel' => ReleaseChannel::Pilot, 'storage_path' => 'x',
            'sha256' => str_repeat('a', 64), 'size_bytes' => 1, 'published_at' => now(),
        ]);
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.releases.index')->call('promote', $release->id);

        $this->assertSame(ReleaseChannel::Stable, $release->fresh()->channel);
    }

    public function test_support_cannot_publish(): void
    {
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        Livewire::test('pages::staff.releases.index')->call('save')->assertForbidden();
    }

    public function test_staff_moves_a_device_to_the_pilot_channel(): void
    {
        $device = Device::factory()->active()->create();
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        Livewire::test('pages::staff.devices.index')->call('setChannel', $device->id, 'pilot');

        $this->assertSame(ReleaseChannel::Pilot, $device->fresh()->release_channel);
    }
}
