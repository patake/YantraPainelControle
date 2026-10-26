<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Enums\DbType;
use App\Enums\ExamFieldKind;
use App\Models\Device;
use App\Models\ErpConnection;
use App\Models\ExamType;
use App\Support\ResponseSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(Device $device): string
    {
        return $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;
    }

    private function configuredDevice(): Device
    {
        $device = Device::factory()->active()->create();
        $device->settings()->create(['network_username' => 'HOSP\\yantra', 'network_password' => 'senha-rede']);

        ErpConnection::create([
            'client_id' => $device->client_id, 'db_type' => DbType::Oracle, 'host' => 'oracle.hosp.local',
            'port' => 1521, 'service_name' => 'TASYPRD', 'username' => 'YANTRA_APP', 'password' => 'senha-banco',
        ]);

        $exam = ExamType::factory()->create(['client_id' => $device->client_id, 'name' => 'ECG Repouso']);
        $exam->fields()->create(['kind' => ExamFieldKind::Internal, 'placeholder' => 'CD_PROCEDIMENTO', 'default_value' => '40101010', 'input' => 'text']);
        ExamType::factory()->create(['client_id' => $device->client_id, 'name' => 'Desabilitado']);
        $device->examTypes()->attach($exam);

        return $device->fresh();
    }

    public function test_returns_the_signed_parameters_of_the_device(): void
    {
        $device = $this->configuredDevice();

        $response = $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.config'))
            ->assertOk()
            ->assertHeader('ETag', '"'.$device->config_version.'"')
            ->assertHeader(ResponseSigner::HEADER_SIGNATURE)
            ->assertJsonPath('version', $device->config_version)
            ->assertJsonPath('network_credential.password', 'senha-rede')
            ->assertJsonPath('erp_connection.password', 'senha-banco')
            ->assertJsonPath('erp_connection.service_name', 'TASYPRD')
            ->assertJsonCount(1, 'exams')
            ->assertJsonPath('exams.0.name', 'ECG Repouso')
            ->assertJsonPath('exams.0.fields.0.placeholder', 'CD_PROCEDIMENTO');

        $this->assertStringContainsString('{CD_PROCEDIMENTO}', $response->json('exams.0.sql.query'));
    }

    public function test_returns_304_when_the_agent_has_the_current_version(): void
    {
        $device = $this->configuredDevice();

        $this->withToken($this->tokenFor($device))
            ->withHeader('If-None-Match', '"'.$device->config_version.'"')
            ->get(route('agent.config'))
            ->assertStatus(304);
    }

    public function test_a_change_in_the_exam_raises_the_version_of_the_device(): void
    {
        $device = $this->configuredDevice();
        $before = $device->config_version;

        $device->examTypes()->first()->update(['destination_folder' => '\\\\novo\\yantra']);

        $this->assertGreaterThan($before, $device->fresh()->config_version);
    }

    public function test_a_change_in_the_erp_connection_raises_the_version_of_every_device_of_the_client(): void
    {
        $device = $this->configuredDevice();
        $other = Device::factory()->create(['client_id' => $device->client_id]);
        $before = [$device->config_version, $other->fresh()->config_version];

        ErpConnection::where('client_id', $device->client_id)->sole()->update(['host' => 'novo.hosp.local']);

        $this->assertSame($before[0] + 1, $device->fresh()->config_version);
        $this->assertSame($before[1] + 1, $other->fresh()->config_version);
    }

    public function test_returns_403_to_a_blocked_device(): void
    {
        $device = Device::factory()->blocked()->create();

        $this->withToken($this->tokenFor($device))
            ->getJson(route('agent.config'))
            ->assertForbidden();
    }
}
