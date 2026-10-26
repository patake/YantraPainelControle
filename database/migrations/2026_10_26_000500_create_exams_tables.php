<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('device_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('temp_folder')->nullable();
            $table->json('patient')->nullable();
            $table->json('complementary_info')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'started_at']);
            $table->index('device_id');
        });

        // Um equipamento tem no máximo um exame aberto (R-EXA-1).
        DB::statement("CREATE UNIQUE INDEX exams_one_open_per_device ON exams (device_id) WHERE status IN ('starting', 'capturing', 'reviewing', 'recording')");

        Schema::create('exam_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('original_name');
            $table->string('preview_path')->nullable();
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->boolean('selected')->default(false);
            $table->string('final_path')->nullable();
            $table->string('erp_report_id')->nullable();
            $table->timestamps();

            $table->index('exam_id');
        });

        Schema::create('agent_commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('exam_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->text('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('result')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Consulta periódica do agente: comandos pendentes do equipamento.
            $table->index(['device_id', 'status']);
            $table->index('exam_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_commands');
        Schema::dropIfExists('exam_files');
        Schema::dropIfExists('exams');
    }
};
