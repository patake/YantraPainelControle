<?php

namespace Tests\Feature\App;

use App\Enums\CommandType;
use App\Enums\ExamFieldKind;
use App\Enums\ExamFileKind;
use App\Enums\ExamStatus;
use App\Models\AgentCommand;
use App\Models\Device;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Telas do operador no ciclo de exame (E6).
 */
class ExamCycleTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Device $device;

    private ExamType $examType;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::disk('s3')->buildTemporaryUrlsUsing(fn (string $path, DateTimeInterface $expires) => "https://s3.test/{$path}");

        $this->device = Device::factory()->active()->create();
        $this->examType = ExamType::factory()->create(['client_id' => $this->device->client_id, 'name' => 'ECG Repouso']);
        $this->examType->fields()->create(['kind' => ExamFieldKind::Filter, 'placeholder' => 'CD_SETOR', 'label' => 'Setor', 'input' => 'text']);
        $this->device->examTypes()->attach($this->examType);

        $this->operator = User::factory()->withTwoFactor()->create();
        $this->operator->clients()->attach($this->device->client_id);
    }

    private function openExam(ExamStatus $status = ExamStatus::Reviewing): Exam
    {
        return Exam::factory()->create([
            'device_id' => $this->device->id, 'client_id' => $this->device->client_id,
            'exam_type_id' => $this->examType->id, 'operator_id' => $this->operator->id, 'status' => $status,
        ]);
    }

    public function test_operator_starts_an_exam_and_the_agent_gets_the_start_command(): void
    {
        $this->actingAs($this->operator);

        Livewire::test('pages::app.exams.create')
            ->set('deviceId', $this->device->id)
            ->set('examTypeId', $this->examType->id)
            ->call('start')
            ->assertHasNoErrors()
            ->assertRedirect();

        $exam = Exam::sole();
        $this->assertSame(ExamStatus::Starting, $exam->status);
        $command = AgentCommand::sole();
        $this->assertSame(CommandType::StartExam, $command->type);
        $this->assertSame('ECG Repouso', $command->payload['exam_name']);
    }

    public function test_does_not_start_a_second_exam_on_the_same_device(): void
    {
        $this->openExam();
        $this->actingAs($this->operator);

        Livewire::test('pages::app.exams.create')
            ->set('deviceId', $this->device->id)
            ->set('examTypeId', $this->examType->id)
            ->call('start')
            ->assertHasErrors(['deviceId' => 'O equipamento já tem um exame aberto. Conclua ou cancele o exame antes.']);
    }

    public function test_operator_cannot_open_an_exam_of_another_client(): void
    {
        $foreign = Exam::factory()->create();
        $this->actingAs($this->operator);

        $this->get(route('app.exams.show', $foreign))->assertNotFound();
    }

    public function test_patient_query_sends_only_the_fields_of_the_exam(): void
    {
        $exam = $this->openExam();
        $this->actingAs($this->operator);

        Livewire::test('pages::app.exams.show', ['exam' => $exam])
            ->set('queryValues.CD_SETOR', '12')
            ->set('queryValues.OUTRO', 'injetado')
            ->call('queryPatients');

        $command = AgentCommand::where('type', CommandType::QueryPatients)->sole();
        $this->assertSame(['values' => ['CD_SETOR' => '12']], $command->payload);
    }

    public function test_recording_sends_the_password_only_in_the_encrypted_command(): void
    {
        $exam = $this->openExam();
        $file = $exam->files()->create(['kind' => ExamFileKind::Pdf, 'original_name' => 'ecg.pdf', 'sha256' => str_repeat('a', 64), 'size_bytes' => 3]);
        AgentCommand::create([
            'device_id' => $this->device->id, 'exam_id' => $exam->id, 'type' => CommandType::QueryPatients,
            'status' => 'done', 'expires_at' => now()->addMinute(),
            'result' => ['success' => true, 'data' => ['columns' => ['PACIENTE', 'ATENDIMENTO'], 'rows' => [['PACIENTE' => 'PACIENTE EXEMPLO 1', 'ATENDIMENTO' => '4815162']]]],
        ]);
        $this->actingAs($this->operator);

        Livewire::test('pages::app.exams.show', ['exam' => $exam])
            ->set('patientIndex', 0)
            ->set('selectedFileIds', [$file->id])
            ->set('tasyUsername', 'MEDICO')
            ->set('tasyPassword', 'senha-tasy')
            ->call('record')
            ->assertHasNoErrors()
            ->assertSet('tasyPassword', '');

        $command = AgentCommand::where('type', CommandType::RecordReport)->sole();
        $this->assertSame('senha-tasy', $command->payload['tasy_password']);
        $this->assertSame('4815162', $command->payload['patient']['ATENDIMENTO']);
        $this->assertStringNotContainsString('senha-tasy', (string) DB::table('agent_commands')->where('id', $command->id)->value('payload'));
        $this->assertStringNotContainsString('senha-tasy', json_encode($exam->fresh()->toArray()));
        $this->assertSame(ExamStatus::Recording, $exam->fresh()->status);
        $this->assertTrue($file->fresh()->selected);
    }

    public function test_recording_requires_a_patient_and_a_file(): void
    {
        $exam = $this->openExam();
        $this->actingAs($this->operator);

        Livewire::test('pages::app.exams.show', ['exam' => $exam])
            ->set('tasyUsername', 'MEDICO')
            ->set('tasyPassword', 'senha')
            ->call('record')
            ->assertHasErrors(['patientIndex', 'selectedFileIds']);
    }

    public function test_cancel_erases_the_previews_and_tells_the_agent(): void
    {
        $exam = $this->openExam();
        $path = $exam->previewFolder().'/a.pdf';
        Storage::disk('s3')->put($path, 'pdf');
        $exam->files()->create(['kind' => ExamFileKind::Pdf, 'original_name' => 'a.pdf', 'preview_path' => $path, 'sha256' => str_repeat('a', 64), 'size_bytes' => 3]);
        $this->actingAs($this->operator);

        Livewire::test('pages::app.exams.show', ['exam' => $exam])->call('cancel');

        $this->assertSame(ExamStatus::Cancelled, $exam->fresh()->status);
        Storage::disk('s3')->assertMissing($path);
        $this->assertSame(1, AgentCommand::where('type', CommandType::CancelExam)->count());
    }

    /**
     * E7: a captura de tela (PNG) aparece na conferência ao lado dos PDFs.
     */
    public function test_review_shows_the_screen_capture_image_next_to_the_pdf(): void
    {
        $exam = $this->openExam();
        $exam->files()->create(['kind' => ExamFileKind::Pdf, 'original_name' => 'ecg.pdf', 'preview_path' => $exam->previewFolder().'/a.pdf', 'sha256' => str_repeat('a', 64), 'size_bytes' => 3]);
        $png = $exam->files()->create(['kind' => ExamFileKind::Png, 'original_name' => 'captura.png', 'preview_path' => $exam->previewFolder().'/b.png', 'sha256' => str_repeat('b', 64), 'size_bytes' => 3]);
        $this->actingAs($this->operator);

        Livewire::test('pages::app.exams.show', ['exam' => $exam])
            ->assertSee('ecg.pdf')
            ->assertSee('captura.png')
            ->set('previewFileId', $png->id)
            ->assertSeeHtml('<img src="https://s3.test/'.$png->preview_path.'"');
    }

    public function test_history_shows_only_the_exams_of_the_linked_clients(): void
    {
        $this->openExam(ExamStatus::Completed)->update(['patient' => ['PACIENTE' => 'PACIENTE PROPRIO']]);
        Exam::factory()->completed()->create(['patient' => ['PACIENTE' => 'PACIENTE ALHEIO']]);

        $this->actingAs($this->operator)
            ->get(route('app.exams.index'))
            ->assertOk()
            ->assertSee('PACIENTE PROPRIO')
            ->assertDontSee('PACIENTE ALHEIO');
    }
}
