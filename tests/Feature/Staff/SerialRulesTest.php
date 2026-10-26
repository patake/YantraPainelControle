<?php

namespace Tests\Feature\Staff;

use App\Models\Device;
use App\Models\ExamType;
use App\Models\User;
use App\Support\DeviceConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * E8 e R-EXA-12: regras regex da porta serial no cadastro do exame.
 */
class SerialRulesTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE = "FC: 72 bpm\nPA: 120/80 mmHg\n";

    public function test_admin_saves_the_rules_in_a_new_version_and_the_agent_gets_them(): void
    {
        $device = Device::factory()->active()->create();
        $examType = ExamType::factory()->create(['client_id' => $device->client_id]);
        $device->examTypes()->attach($examType);
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.exams.form', ['examType' => $examType])
            ->call('addSerialRule')
            ->set('serialRules.0.field', 'fc')
            ->set('serialRules.0.pattern', 'FC:\s*(\d+)')
            ->call('save')
            ->assertHasNoErrors();

        $examType->refresh();
        $this->assertSame(2, $examType->version);
        $this->assertSame([['field' => 'FC', 'pattern' => 'FC:\s*(\d+)']], $examType->versions()->where('version', 2)->sole()->snapshot['serial_rules']);

        // Os parâmetros que o agente baixa em GET /config levam as regras do exame.
        $config = json_decode(json_encode(DeviceConfig::for($device->fresh())), true);
        $this->assertSame([['field' => 'FC', 'pattern' => 'FC:\s*(\d+)']], $config['exams'][0]['serial_rules']);
    }

    public function test_rejects_a_rule_that_re2_does_not_accept_and_a_repeated_field(): void
    {
        $examType = ExamType::factory()->create();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.exams.form', ['examType' => $examType])
            ->set('serialRules', [
                ['field' => 'FC', 'pattern' => 'FC(?=:)'],
                ['field' => 'fc', 'pattern' => 'FC:\s*(\d+)'],
            ])
            ->call('save')
            ->assertHasErrors(['serialRules.0.pattern', 'serialRules.1.field']);

        $this->assertSame(0, $examType->serialRules()->count());
    }

    public function test_support_tests_the_rules_with_a_pasted_content(): void
    {
        $examType = ExamType::factory()->create();
        $examType->serialRules()->createMany([
            ['field' => 'FC', 'pattern' => 'FC:\s*(\d+)', 'position' => 0],
            ['field' => 'SPO2', 'pattern' => 'SpO2:\s*(\d+)', 'position' => 1],
        ]);
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        Livewire::test('pages::staff.exams.form', ['examType' => $examType])
            ->set('serialSample', self::SAMPLE)
            ->assertSee('72')
            ->assertSee('Não encontrado');
    }

    public function test_restoring_a_version_restores_the_rules(): void
    {
        $examType = ExamType::factory()->create();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.exams.form', ['examType' => $examType])
            ->set('serialRules', [['field' => 'FC', 'pattern' => 'FC:\s*(\d+)']])
            ->call('save');
        Livewire::test('pages::staff.exams.form', ['examType' => $examType->fresh()])
            ->set('serialRules', [])
            ->call('save');

        $this->assertSame(0, $examType->serialRules()->count());

        Livewire::test('pages::staff.exams.version', ['examType' => $examType, 'version' => 3])
            ->assertSee('Regras da porta serial')
            ->assertSee('removida');

        Livewire::test('pages::staff.exams.version', ['examType' => $examType, 'version' => 2])->call('restore');

        $this->assertSame(['FC'], $examType->serialRules()->pluck('field')->all());
    }
}
