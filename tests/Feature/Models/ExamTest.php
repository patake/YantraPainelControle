<?php

namespace Tests\Feature\Models;

use App\Enums\ExamStatus;
use App\Models\Device;
use App\Models\Exam;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-EXA-1: um equipamento tem no máximo um exame aberto.
 */
class ExamTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_a_second_open_exam_on_the_same_device(): void
    {
        $device = Device::factory()->active()->create();
        Exam::factory()->for($device)->create(['status' => ExamStatus::Reviewing]);

        $this->expectException(UniqueConstraintViolationException::class);

        Exam::factory()->for($device)->create(['status' => ExamStatus::Starting]);
    }

    public function test_allows_a_new_exam_after_the_previous_exam_ends(): void
    {
        $device = Device::factory()->active()->create();
        Exam::factory()->for($device)->completed()->create();
        Exam::factory()->for($device)->create(['status' => ExamStatus::Cancelled]);

        Exam::factory()->for($device)->create(['status' => ExamStatus::Capturing]);

        $this->assertSame(1, Exam::open()->whereBelongsTo($device)->count());
    }

    public function test_allows_open_exams_on_different_devices(): void
    {
        Exam::factory()->count(2)->create(['status' => ExamStatus::Capturing]);

        $this->assertSame(2, Exam::open()->count());
    }
}
