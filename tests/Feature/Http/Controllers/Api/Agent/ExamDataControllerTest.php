<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Enums\ExamStatus;
use App\Models\Device;
use App\Models\Exam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E8: o agente envia os dados extraídos da porta serial com as regras regex do exame.
 */
class ExamDataControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(Device $device): string
    {
        return $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;
    }

    public function test_stores_the_fields_and_merges_a_second_send(): void
    {
        $exam = Exam::factory()->create(['status' => ExamStatus::Capturing]);
        $token = $this->tokenFor($exam->device);

        $this->withToken($token)
            ->postJson(route('agent.exams.data.store', $exam), ['fields' => ['FC' => '72', 'PA' => '120/80']])
            ->assertNoContent();

        $this->withToken($token)
            ->postJson(route('agent.exams.data.store', $exam), ['fields' => ['FC' => '75', 'SPO2' => '98']])
            ->assertNoContent();

        $exam->refresh();
        $this->assertSame(['FC' => '75', 'PA' => '120/80', 'SPO2' => '98'], $exam->serial_data);
        $this->assertNotNull($exam->serial_received_at);
        $this->assertSame(ExamStatus::Reviewing, $exam->status);
    }

    public function test_rejects_a_field_name_outside_the_format(): void
    {
        $exam = Exam::factory()->create(['status' => ExamStatus::Capturing]);

        $this->withToken($this->tokenFor($exam->device))
            ->postJson(route('agent.exams.data.store', $exam), ['fields' => ['fc' => '72', 'PA' => '120/80']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fields');

        $this->assertNull($exam->fresh()->serial_data);
    }

    public function test_returns_409_for_a_finished_exam_and_404_for_another_device(): void
    {
        $finished = Exam::factory()->completed()->create();
        $open = Exam::factory()->create(['status' => ExamStatus::Capturing]);

        $this->withToken($this->tokenFor($finished->device))
            ->postJson(route('agent.exams.data.store', $finished), ['fields' => ['FC' => '72']])
            ->assertConflict();

        $this->withToken($this->tokenFor($finished->device))
            ->postJson(route('agent.exams.data.store', $open), ['fields' => ['FC' => '72']])
            ->assertNotFound();
    }
}
