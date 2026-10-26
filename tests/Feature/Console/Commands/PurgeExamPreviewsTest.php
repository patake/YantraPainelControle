<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\ExamFileKind;
use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R-EXA-7: o painel apaga os previews dos exames terminados e os previews com mais de 24 horas.
 */
class PurgeExamPreviewsTest extends TestCase
{
    use RefreshDatabase;

    private function preview(Exam $exam): ExamFile
    {
        $path = $exam->previewFolder().'/'.fake()->uuid().'.pdf';
        Storage::disk('s3')->put($path, 'pdf');

        return $exam->files()->create([
            'kind' => ExamFileKind::Pdf, 'original_name' => 'ecg.pdf', 'preview_path' => $path,
            'sha256' => str_repeat('a', 64), 'size_bytes' => 3,
        ]);
    }

    public function test_purges_finished_and_old_exams_and_keeps_recent_open_exams(): void
    {
        Storage::fake('s3');
        $finished = $this->preview(Exam::factory()->create(['status' => ExamStatus::Cancelled]));
        $old = $this->preview(Exam::factory()->create(['status' => ExamStatus::Reviewing, 'started_at' => now()->subHours(25)]));
        $recent = $this->preview(Exam::factory()->create(['status' => ExamStatus::Reviewing]));
        $paths = [$finished->preview_path, $old->preview_path];

        $this->artisan('exams:purge-previews')->expectsOutput('Exames com previews apagados: 2.')->assertSuccessful();

        Storage::disk('s3')->assertMissing($paths);
        Storage::disk('s3')->assertExists($recent->preview_path);
        $this->assertNull($finished->fresh()->preview_path);
        $this->assertNotNull($recent->fresh()->preview_path);
    }
}
