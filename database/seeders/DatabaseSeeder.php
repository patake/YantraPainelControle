<?php

namespace Database\Seeders;

use App\Enums\DeviceStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Device;
use App\Models\License;
use App\Models\System;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Dados de exemplo para desenvolvimento. Os nomes seguem o protótipo do painel.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SystemSeeder::class);

        User::factory()->create([
            'name' => 'Administrador Weega',
            'email' => 'admin@weega.com.br',
            'role' => UserRole::Admin,
        ]);

        User::factory()->create([
            'name' => 'Suporte Weega',
            'email' => 'suporte@weega.com.br',
            'role' => UserRole::Support,
        ]);

        $client = Client::factory()->create([
            'legal_name' => 'Hospital Exemplo Norte Ltda.',
            'trade_name' => 'Hospital Exemplo Norte',
        ]);

        $operator = User::factory()->create([
            'name' => 'Operador Exemplo',
            'email' => 'operador@hospital-exemplo.com.br',
            'role' => UserRole::Operator,
        ]);
        $operator->clients()->attach($client);

        $cardio = System::where('code', 'YC_PRT')->firstOrFail();

        License::factory()->create([
            'client_id' => $client->id,
            'system_id' => $cardio->id,
            'quantity' => 10,
        ]);

        Device::factory()->active()->create([
            'client_id' => $client->id,
            'system_id' => $cardio->id,
            'hostname' => 'CARDIO-SALA-02',
        ]);

        Device::factory()->create([
            'client_id' => $client->id,
            'system_id' => $cardio->id,
            'hostname' => 'CARDIO-SALA-04',
            'status' => DeviceStatus::Trial,
        ]);
    }
}
