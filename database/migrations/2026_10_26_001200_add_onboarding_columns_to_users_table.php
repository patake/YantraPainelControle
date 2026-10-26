<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assistente de primeiro acesso (R-ACE-15): senha, 2FA, tema e acesso ao painel.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('password');
            $table->timestamp('onboarded_at')->nullable()->after('last_login_at');
        });

        // Quem já tem o 2FA confirmado já passou pelo primeiro acesso.
        DB::table('users')->whereNotNull('two_factor_confirmed_at')->update([
            'password_changed_at' => now(),
            'onboarded_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_changed_at', 'onboarded_at']);
        });
    }
};
