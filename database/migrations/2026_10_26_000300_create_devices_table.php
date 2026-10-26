<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('system_id')->constrained()->restrictOnDelete();
            $table->char('signature', 64)->unique();
            $table->string('status', 20);
            $table->timestamp('trial_ends_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('hostname');
            $table->string('machine_type')->nullable();
            $table->string('domain')->nullable();
            $table->json('macs')->nullable();
            $table->string('windows_version')->nullable();
            $table->string('agent_version', 20)->nullable();
            $table->unsignedInteger('config_version')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('registered_at');
            $table->timestamps();

            // Contagem de licenças em uso por cliente e sistema (R-LIC-1).
            $table->index(['client_id', 'system_id', 'status']);
            // Tarefa agendada que expira os trials (R-LIC-3).
            $table->index(['status', 'trial_ends_at']);
        });

        Schema::create('device_settings', function (Blueprint $table) {
            $table->foreignUuid('device_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('capture_base_path')->default('C:\ProgramData\YantraAgente\capturas');
            $table->string('network_username')->nullable();
            $table->text('network_password')->nullable();
            $table->string('hotkey', 40)->nullable();
            $table->string('serial_port', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_settings');
        Schema::dropIfExists('devices');
    }
};
