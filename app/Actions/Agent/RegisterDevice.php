<?php

namespace App\Actions\Agent;

use App\Enums\DeviceStatus;
use App\Exceptions\AgentRegistrationException;
use App\Models\Client;
use App\Models\Device;
use App\Models\License;
use App\Models\System;
use Illuminate\Support\Facades\DB;

/**
 * Registra o equipamento na ativação por CNPJ e entrega o token do agente.
 */
class RegisterDevice
{
    /**
     * Dias de trial depois do registro (R-LIC-3).
     */
    public const TRIAL_DAYS = 7;

    /**
     * @param  array{cnpj: string, system: string, signature: string, hostname: string, machine_type?: ?string, domain?: ?string, macs?: ?array<int, string>, windows_version?: ?string, agent_version?: ?string}  $data
     * @return array{device: Device, token: string, reinstalled: bool}
     */
    public function handle(array $data, ?string $ip = null): array
    {
        $client = Client::where('cnpj', $data['cnpj'])->where('active', true)->first();

        if ($client === null) {
            throw AgentRegistrationException::unknownClient();
        }

        $system = System::where('code', $data['system'])->first();
        $existing = Device::where('signature', $data['signature'])->first();

        if ($existing !== null && $existing->client_id !== $client->id) {
            // A mesma assinatura em outro cliente: suspeita de clone (R-LIC-6).
            $existing->writeAudit('clone_suspected', [], [
                'attempted_cnpj' => $client->cnpj,
                'hostname' => $data['hostname'],
                'ip' => $ip,
            ]);

            throw AgentRegistrationException::signatureOfAnotherClient();
        }

        if ($existing !== null) {
            return $this->reinstall($existing, $data);
        }

        return DB::transaction(function () use ($client, $system, $data) {
            // O bloqueio da linha impede dois registros na última licença livre (R-LIC-2).
            $license = $system === null ? null : License::whereBelongsTo($client)
                ->whereBelongsTo($system)
                ->lockForUpdate()
                ->first();

            if ($license === null) {
                throw AgentRegistrationException::systemNotLicensed($data['system']);
            }

            if (! $license->hasAvailableSeat()) {
                throw AgentRegistrationException::noAvailableSeat($system->name);
            }

            $device = Device::create([
                'client_id' => $client->id,
                'system_id' => $system->id,
                'signature' => $data['signature'],
                'status' => DeviceStatus::Trial,
                'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                'registered_at' => now(),
                'last_seen_at' => now(),
                ...$this->machineData($data),
            ]);

            $device->settings()->create();

            return [
                'device' => $device,
                'token' => $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken,
                'reinstalled' => false,
            ];
        });
    }

    /**
     * Reinstalação no mesmo cliente: o equipamento continua o mesmo, com o mesmo
     * estado, e recebe um token novo (R-LIC-7).
     *
     * @param  array<string, mixed>  $data
     * @return array{device: Device, token: string, reinstalled: bool}
     */
    private function reinstall(Device $device, array $data): array
    {
        return DB::transaction(function () use ($device, $data) {
            $device->tokens()->delete();
            $device->update([...$this->machineData($data), 'last_seen_at' => now()]);

            return [
                'device' => $device,
                'token' => $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken,
                'reinstalled' => true,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function machineData(array $data): array
    {
        return [
            'hostname' => $data['hostname'],
            'machine_type' => $data['machine_type'] ?? null,
            'domain' => $data['domain'] ?? null,
            'macs' => $data['macs'] ?? null,
            'windows_version' => $data['windows_version'] ?? null,
            'agent_version' => $data['agent_version'] ?? null,
        ];
    }
}
