<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\ExamFileKind;
use App\Enums\ExamStatus;
use App\Models\AgentCommand;
use App\Models\Device;
use App\Models\Exam;
use App\Models\ExamFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommandControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(Device $device): string
    {
        return $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;
    }

    private function command(Device $device, CommandType $type, array $attributes = []): AgentCommand
    {
        return AgentCommand::create(array_merge([
            'device_id' => $device->id,
            'type' => $type,
            'payload' => ['exam_name' => 'ECG'],
            'expires_at' => now()->addMinute(),
        ], $attributes));
    }

    public function test_delivers_each_pending_command_once(): void
    {
        $device = Device::factory()->active()->create();
        $command = $this->command($device, CommandType::Sync);
        $this->command(Device::factory()->active()->create(), CommandType::Sync);
        $token = $this->tokenFor($device);

        $this->withToken($token)->getJson(route('agent.commands.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $command->id)
            ->assertJsonPath('data.0.payload.exam_name', 'ECG')
            ->assertJsonPath('next_poll_seconds', 30);

        $this->assertSame(CommandStatus::Delivered, $command->fresh()->status);
        $this->withToken($token)->getJson(route('agent.commands.index'))->assertJsonCount(0, 'data');
    }

    public function test_polls_every_5_seconds_while_an_exam_is_open(): void
    {
        $exam = Exam::factory()->create(['status' => ExamStatus::Capturing]);

        $this->withToken($this->tokenFor($exam->device))
            ->getJson(route('agent.commands.index'))
            ->assertJsonPath('next_poll_seconds', 5);
    }

    public function test_start_result_moves_the_exam_to_capturing_with_the_temporary_folder(): void
    {
        $exam = Exam::factory()->create(['status' => ExamStatus::Starting, 'temp_folder' => null]);
        $command = $this->command($exam->device, CommandType::StartExam, ['exam_id' => $exam->id, 'status' => CommandStatus::Delivered]);

        $this->withToken($this->tokenFor($exam->device))
            ->postJson(route('agent.commands.result', $command), ['success' => true, 'data' => ['temp_folder' => 'C:\capturas\ECG\1']])
            ->assertNoContent();

        $this->assertSame(ExamStatus::Capturing, $exam->fresh()->status);
        $this->assertSame('C:\capturas\ECG\1', $exam->fresh()->temp_folder);
        $this->assertSame(CommandStatus::Done, $command->fresh()->status);
    }

    public function test_record_result_completes_the_exam_erases_the_password_and_the_previews(): void
    {
        Storage::fake('s3');
        $exam = Exam::factory()->create(['status' => ExamStatus::Recording]);
        $file = $this->previewFile($exam, selected: true);
        $command = $this->command($exam->device, CommandType::RecordReport, [
            'exam_id' => $exam->id, 'status' => CommandStatus::Delivered, 'payload' => ['tasy_password' => 'senha-tasy'],
        ]);

        $this->withToken($this->tokenFor($exam->device))
            ->postJson(route('agent.commands.result', $command), ['success' => true, 'data' => ['reports' => [
                ['file_id' => $file->id, 'final_path' => '\\\\srv\\yantra\\ECG-1.pdf', 'erp_report_id' => 88421],
            ]]])
            ->assertNoContent();

        $this->assertSame(ExamStatus::Completed, $exam->fresh()->status);
        $this->assertSame('88421', $file->fresh()->erp_report_id);
        $this->assertNull($file->fresh()->preview_path);
        $this->assertNull($command->fresh()->payload);
        Storage::disk('s3')->assertMissing($file->preview_path);
    }

    public function test_record_result_with_one_error_returns_the_exam_to_review(): void
    {
        Storage::fake('s3');
        $exam = Exam::factory()->create(['status' => ExamStatus::Recording]);
        $good = $this->previewFile($exam, selected: true);
        $bad = $this->previewFile($exam, selected: true);
        $command = $this->command($exam->device, CommandType::RecordReport, ['exam_id' => $exam->id, 'status' => CommandStatus::Delivered]);

        $this->withToken($this->tokenFor($exam->device))
            ->postJson(route('agent.commands.result', $command), ['success' => false, 'error' => 'ORA-02291', 'data' => ['reports' => [
                ['file_id' => $good->id, 'final_path' => 'x', 'erp_report_id' => 1],
                ['file_id' => $bad->id, 'error' => 'ORA-02291'],
            ]]])
            ->assertNoContent();

        $this->assertSame(ExamStatus::Reviewing, $exam->fresh()->status);
        $this->assertNull($bad->fresh()->erp_report_id);
        $this->assertNotNull($good->fresh()->preview_path);
    }

    public function test_returns_410_for_an_expired_command_and_409_for_a_repeated_result(): void
    {
        $device = Device::factory()->active()->create();
        $expired = $this->command($device, CommandType::Sync, ['status' => CommandStatus::Delivered, 'expires_at' => now()->subSecond()]);
        $done = $this->command($device, CommandType::Sync, ['status' => CommandStatus::Done]);
        $token = $this->tokenFor($device);

        $this->withToken($token)->postJson(route('agent.commands.result', $expired), ['success' => true])->assertStatus(410);
        $this->withToken($token)->postJson(route('agent.commands.result', $done), ['success' => true])->assertConflict();
    }

    public function test_returns_404_for_a_command_of_another_device(): void
    {
        $command = $this->command(Device::factory()->active()->create(), CommandType::Sync, ['status' => CommandStatus::Delivered]);

        $this->withToken($this->tokenFor(Device::factory()->active()->create()))
            ->postJson(route('agent.commands.result', $command), ['success' => true])
            ->assertNotFound();
    }

    private function previewFile(Exam $exam, bool $selected): ExamFile
    {
        $path = $exam->previewFolder().'/'.fake()->uuid().'.pdf';
        Storage::disk('s3')->put($path, 'pdf');

        return $exam->files()->create([
            'kind' => ExamFileKind::Pdf, 'original_name' => 'ecg.pdf', 'preview_path' => $path,
            'sha256' => str_repeat('a', 64), 'size_bytes' => 3, 'selected' => $selected,
        ]);
    }
}
