<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\ExamStatus;
use App\Models\AgentCommand;
use App\Models\Exam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireAgentCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function expiredCommand(Exam $exam, CommandType $type): AgentCommand
    {
        return AgentCommand::create([
            'device_id' => $exam->device_id, 'exam_id' => $exam->id, 'type' => $type,
            'payload' => ['tasy_password' => 'senha-tasy'], 'status' => CommandStatus::Delivered,
            'expires_at' => now()->subSecond(),
        ]);
    }

    public function test_expired_recording_erases_the_password_and_returns_the_exam_to_review(): void
    {
        $exam = Exam::factory()->create(['status' => ExamStatus::Recording]);
        $command = $this->expiredCommand($exam, CommandType::RecordReport);

        $this->artisan('commands:expire')->assertSuccessful();

        $this->assertSame(CommandStatus::Expired, $command->fresh()->status);
        $this->assertNull($command->fresh()->payload);
        $this->assertSame(ExamStatus::Reviewing, $exam->fresh()->status);
    }

    public function test_expired_start_fails_the_exam(): void
    {
        $exam = Exam::factory()->create(['status' => ExamStatus::Starting]);
        $this->expiredCommand($exam, CommandType::StartExam);

        $this->artisan('commands:expire')->assertSuccessful();

        $this->assertSame(ExamStatus::Failed, $exam->fresh()->status);
    }
}
