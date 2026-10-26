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
        Schema::create('agent_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->string('channel', 20);
            $table->string('storage_path');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_releases');
    }
};
