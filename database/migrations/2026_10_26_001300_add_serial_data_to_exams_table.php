<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dados que o agente extraiu da porta serial com as regras regex do exame (E8).
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->json('serial_data')->nullable()->after('complementary_info');
            $table->timestamp('serial_received_at')->nullable()->after('serial_data');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['serial_data', 'serial_received_at']);
        });
    }
};
