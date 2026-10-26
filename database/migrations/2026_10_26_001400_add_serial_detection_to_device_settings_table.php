<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Porta COM que o assistente do agente achou (E8, R-EXA-13): adaptador, teste e data.
     */
    public function up(): void
    {
        Schema::table('device_settings', function (Blueprint $table) {
            $table->string('serial_adapter')->nullable()->after('serial_port');
            $table->boolean('serial_test_passed')->nullable()->after('serial_adapter');
            $table->timestamp('serial_reported_at')->nullable()->after('serial_test_passed');
        });
    }

    public function down(): void
    {
        Schema::table('device_settings', function (Blueprint $table) {
            $table->dropColumn(['serial_adapter', 'serial_test_passed', 'serial_reported_at']);
        });
    }
};
