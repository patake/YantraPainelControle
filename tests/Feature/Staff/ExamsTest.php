<?php

namespace Tests\Feature\Staff;

use App\Enums\CommandType;
use App\Models\AgentCommand;
use App\Models\Client;
use App\Models\Device;
use App\Models\ErpConnection;
use App\Models\ExamType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-EXA-3: só o administrador altera SQLs, conexões e pastas.
 */
class ExamsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->withTwoFactor()->create();
    }

    public function test_admin_imports_an_exam_from_the_3_0_files(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test('pages::staff.exams.index')
            ->set('importClientId', $client->id)
            ->set('examFile', UploadedFile::fake()->createWithContent('EXAME.json', file_get_contents(base_path('tests/Fixtures/yantra-cardio/Eletro/EXAME.json'))))
            ->set('parametersFile', UploadedFile::fake()->createWithContent('PARAMETROS.json', file_get_contents(base_path('tests/Fixtures/yantra-cardio/Eletro/PARAMETROS.json'))))
            ->call('import')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Eletro', $client->examTypes()->sole()->name);
    }

    public function test_admin_saves_an_exam_with_readings_in_a_new_version(): void
    {
        $exam = ExamType::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test('pages::staff.exams.form', ['examType' => $exam])
            ->set('destinationFolder', '\\\\srv\\yantra\\ECG')
            ->call('addField')
            ->set('fields.0.kind', 'filter')
            ->set('fields.0.placeholder', 'cd_setor')
            ->set('fields.0.label', 'Setor')
            ->call('save')
            ->assertHasNoErrors();

        $exam->refresh();
        $this->assertSame(2, $exam->version);
        $this->assertSame('\\\\srv\\yantra\\ECG', $exam->destination_folder);
        $this->assertSame('CD_SETOR', $exam->fields()->sole()->placeholder);
    }

    public function test_rejects_a_repeated_placeholder(): void
    {
        $exam = ExamType::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test('pages::staff.exams.form', ['examType' => $exam])
            ->call('addField')
            ->set('fields.0.placeholder', 'X')
            ->call('addField')
            ->set('fields.1.placeholder', 'X')
            ->call('save')
            ->assertHasErrors(['fields.1.placeholder' => 'distinct']);
    }

    public function test_support_sees_the_exam_but_cannot_save(): void
    {
        $exam = ExamType::factory()->create();
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        $this->get(route('staff.exams.edit', $exam))->assertOk()->assertSee($exam->name);
        Livewire::test('pages::staff.exams.form', ['examType' => $exam])->call('save')->assertForbidden();
    }

    public function test_admin_saves_the_erp_connection_with_an_encrypted_password_and_keeps_it_on_edit(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($this->admin());

        $component = Livewire::test('pages::staff.clients.show', ['client' => $client])
            ->set('tab', 'erp')
            ->set('erpHost', 'oracle.hosp.local')
            ->set('erpPort', 1521)
            ->set('erpServiceName', 'TASYPRD')
            ->set('erpUsername', 'YANTRA_APP')
            ->set('erpPassword', 'senha-banco')
            ->call('saveErpConnection')
            ->assertHasNoErrors();

        $component->set('erpHost', 'oracle2.hosp.local')->call('saveErpConnection')->assertHasNoErrors();

        $connection = ErpConnection::sole();
        $this->assertSame('oracle2.hosp.local', $connection->host);
        $this->assertSame('senha-banco', $connection->password);
        $this->assertStringNotContainsString('senha-banco', (string) DB::table('erp_connections')->value('password'));
    }

    public function test_connection_test_creates_a_command_for_the_chosen_device(): void
    {
        $device = Device::factory()->active()->create();
        ErpConnection::create([
            'client_id' => $device->client_id, 'db_type' => 'oracle', 'host' => 'h', 'port' => 1521,
            'service_name' => 's', 'username' => 'u', 'password' => 'p',
        ]);
        $this->actingAs($this->admin());

        Livewire::test('pages::staff.clients.show', ['client' => $device->client])
            ->set('erpTestDeviceId', $device->id)
            ->call('testErpConnection')
            ->assertHasNoErrors();

        $command = AgentCommand::sole();
        $this->assertSame(CommandType::TestErp, $command->type);
        $this->assertSame($device->id, $command->device_id);
    }

    public function test_admin_enables_exams_on_a_device_and_raises_the_parameter_version(): void
    {
        $device = Device::factory()->active()->serialReader()->create();
        $exam = ExamType::factory()->create(['client_id' => $device->client_id]);
        $this->actingAs($this->admin());

        $component = Livewire::test('pages::staff.devices.show', ['device' => $device]);
        $before = $device->fresh()->config_version;

        $component->set('examTypeIds', [$exam->id])
            ->set('serialPort', 'com3')
            ->call('save')
            ->assertHasNoErrors();

        $device->refresh();
        $this->assertSame([$exam->id], $device->examTypes()->pluck('exam_types.id')->all());
        $this->assertSame('COM3', $device->settings->serial_port);
        $this->assertGreaterThan($before, $device->config_version);
    }

    public function test_device_cannot_enable_an_exam_of_another_client(): void
    {
        $device = Device::factory()->active()->create();
        $foreign = ExamType::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test('pages::staff.devices.show', ['device' => $device])
            ->set('examTypeIds', [$foreign->id])
            ->call('save')
            ->assertHasErrors('examTypeIds.0');
    }

    /**
     * R-EXA-11: a combinação de teclas da captura de tela tem Ctrl, Alt ou Win.
     */
    public function test_admin_saves_the_screen_capture_keys_in_the_canonical_format(): void
    {
        $device = Device::factory()->active()->screenCapture()->create();
        $this->actingAs($this->admin());

        Livewire::test('pages::staff.devices.show', ['device' => $device])
            ->assertSet('hotkey', 'Ctrl+Y')
            ->set('hotkey', 'Shift+Y')
            ->call('save')
            ->assertHasErrors('hotkey')
            ->set('hotkey', 'shift+ctrl+y')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('hotkey', 'Ctrl+Shift+Y');

        $this->assertSame('Ctrl+Shift+Y', $device->settings()->value('hotkey'));
    }
}
