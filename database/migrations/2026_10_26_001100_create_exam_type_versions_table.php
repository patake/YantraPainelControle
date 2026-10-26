<?php

use App\Actions\Exams\SaveExamType;
use App\Models\ExamType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histórico das versões do exame. O painel nunca apaga uma versão (R-EXA-10).
     */
    public function up(): void
    {
        Schema::create('exam_type_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('snapshot');
            $table->unsignedInteger('restored_from')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['exam_type_id', 'version']);
        });

        // Os exames que já existem entram no histórico com a versão atual.
        ExamType::with('fields')->each(fn (ExamType $examType) => DB::table('exam_type_versions')->insert([
            'exam_type_id' => $examType->id,
            'version' => $examType->version,
            'snapshot' => json_encode(SaveExamType::snapshot($examType)),
            'created_at' => now(),
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_type_versions');
    }
};
