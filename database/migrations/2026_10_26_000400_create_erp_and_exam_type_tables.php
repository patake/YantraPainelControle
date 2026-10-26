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
        Schema::create('erp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('db_type', 20);
            $table->string('host');
            $table->unsignedInteger('port');
            $table->string('service_name');
            $table->string('username');
            $table->text('password');
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('erp_type', 20);
            $table->string('procedure_code')->nullable();
            $table->string('destination_folder');
            $table->text('sql_query');
            $table->text('sql_auth');
            $table->text('sql_insert');
            $table->text('sql_image');
            $table->text('sql_update');
            $table->json('print_options')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['client_id', 'name']);
        });

        Schema::create('exam_type_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('placeholder', 60);
            $table->string('label')->nullable();
            $table->string('input', 20)->default('text');
            $table->string('default_value')->nullable();
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['exam_type_id', 'placeholder']);
        });

        Schema::create('serial_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $table->string('field', 60);
            $table->text('pattern');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('exam_type_id');
        });

        Schema::create('device_exam_type', function (Blueprint $table) {
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['device_id', 'exam_type_id']);
            $table->index('exam_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_exam_type');
        Schema::dropIfExists('serial_rules');
        Schema::dropIfExists('exam_type_fields');
        Schema::dropIfExists('exam_types');
        Schema::dropIfExists('erp_connections');
    }
};
