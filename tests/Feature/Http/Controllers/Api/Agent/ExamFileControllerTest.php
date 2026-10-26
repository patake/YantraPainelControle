<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use App\Enums\ExamStatus;
use App\Models\Device;
use App\Models\Exam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R-EXA-6 e R-SEG-6: cópia de preview no S3, com o SHA-256 conferido.
 */
class ExamFileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    private function tokenFor(Device $device): string
    {
        return $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('ECG_12_derivacoes.pdf', "%PDF-1.7\nconteudo do exame");
    }

    public function test_stores_the_preview_and_moves_the_exam_to_review(): void
    {
        $exam = Exam::factory()->create(['status' => ExamStatus::Capturing]);
        $pdf = $this->pdf();

        $response = $this->withToken($this->tokenFor($exam->device))
            ->postJson(route('agent.exams.files.store', $exam), [
                'kind' => 'pdf',
                'sha256' => hash_file('sha256', $pdf->getRealPath()),
                'file' => $pdf,
            ])
            ->assertCreated()
            ->assertJsonPath('original_name', 'ECG_12_derivacoes.pdf');

        $file = $exam->files()->sole();
        $this->assertSame($response->json('id'), $file->id);
        $this->assertStringStartsWith("previews/{$exam->client_id}/{$exam->id}/", $file->preview_path);
        Storage::disk('s3')->assertExists($file->preview_path);
        $this->assertSame(ExamStatus::Reviewing, $exam->fresh()->status);
    }

    public function test_rejects_a_file_whose_hash_does_not_match(): void
    {
        $exam = Exam::factory()->create(['status' => ExamStatus::Capturing]);

        $this->withToken($this->tokenFor($exam->device))
            ->postJson(route('agent.exams.files.store', $exam), ['kind' => 'pdf', 'sha256' => str_repeat('0', 64), 'file' => $this->pdf()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sha256');

        $this->assertSame(0, $exam->files()->count());
    }

    public function test_returns_409_for_a_finished_exam(): void
    {
        $exam = Exam::factory()->completed()->create();
        $pdf = $this->pdf();

        $this->withToken($this->tokenFor($exam->device))
            ->postJson(route('agent.exams.files.store', $exam), ['kind' => 'pdf', 'sha256' => hash_file('sha256', $pdf->getRealPath()), 'file' => $pdf])
            ->assertConflict();
    }

    public function test_returns_404_for_an_exam_of_another_device(): void
    {
        $exam = Exam::factory()->create(['status' => ExamStatus::Capturing]);
        $pdf = $this->pdf();

        $this->withToken($this->tokenFor(Device::factory()->active()->create()))
            ->postJson(route('agent.exams.files.store', $exam), ['kind' => 'pdf', 'sha256' => hash_file('sha256', $pdf->getRealPath()), 'file' => $pdf])
            ->assertNotFound();
    }
}
