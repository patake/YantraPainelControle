<?php

use App\Support\ActivationCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código de ativação do cliente (R-LIC-11), criptografado como as outras credenciais.
     * Os clientes que já existem recebem um código novo.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('activation_code')->nullable()->after('contact_phone');
        });

        DB::table('clients')->orderBy('id')->pluck('id')->each(function (int $id) {
            DB::table('clients')->where('id', $id)->update(['activation_code' => Crypt::encryptString(ActivationCode::generate())]);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('activation_code');
        });
    }
};
