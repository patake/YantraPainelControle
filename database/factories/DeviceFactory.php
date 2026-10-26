<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Client;
use App\Models\Device;
use App\Models\System;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registeredAt = now()->subDays(2);

        return [
            'client_id' => Client::factory(),
            'system_id' => System::factory(),
            'signature' => hash('sha256', fake()->unique()->uuid()),
            'status' => DeviceStatus::Trial,
            'trial_ends_at' => $registeredAt->copy()->addDays(7),
            'hostname' => strtoupper(fake()->unique()->bothify('CARDIO-SALA-##??')),
            'machine_type' => 'Desktop',
            'domain' => 'HOSP.LOCAL',
            'macs' => [fake()->macAddress()],
            'windows_version' => 'Windows 11 Pro 24H2',
            'agent_version' => '2.0.0',
            'config_version' => 1,
            'last_seen_at' => now(),
            'registered_at' => $registeredAt,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::Active,
            'trial_ends_at' => null,
            'decided_at' => now()->subDay(),
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::Blocked,
            'decided_at' => now()->subDay(),
        ]);
    }

    /**
     * Equipamento do sistema de captura de tela (YO SRC).
     */
    public function screenCapture(): static
    {
        return $this->state(fn (array $attributes) => [
            'system_id' => System::factory()->screen(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::Expired,
            'trial_ends_at' => now()->subDay(),
        ]);
    }
}
