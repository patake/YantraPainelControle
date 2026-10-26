<?php

namespace App\Support;

use App\Enums\ExamFieldKind;
use App\Models\Device;
use App\Models\ExamType;
use App\Models\ExamTypeField;
use App\Models\SerialRule;

/**
 * Parâmetros que o agente baixa em GET /config (E5) e a versão desses parâmetros.
 *
 * Cada alteração de exame, leitura, regra serial, conexão do ERP ou parametrização
 * aumenta devices.config_version. O agente baixa os parâmetros quando a versão muda.
 */
final class DeviceConfig
{
    public static function bumpDevice(string $deviceId): void
    {
        Device::whereKey($deviceId)->increment('config_version');
    }

    public static function bumpClient(int $clientId): void
    {
        Device::where('client_id', $clientId)->increment('config_version');
    }

    public static function bumpExamType(int $examTypeId): void
    {
        Device::whereHas('examTypes', fn ($query) => $query->whereKey($examTypeId))->increment('config_version');
    }

    /**
     * @return array<string, mixed>
     */
    public static function for(Device $device): array
    {
        $device->loadMissing([
            'settings',
            'client.erpConnection',
            'examTypes' => fn ($query) => $query->where('active', true)->with(['fields', 'serialRules'])->orderBy('name'),
        ]);

        $settings = $device->settings;
        $connection = $device->client->erpConnection;

        return [
            'version' => $device->config_version,
            'capture_base_path' => $settings?->capture_base_path ?? 'C:\ProgramData\YantraAgente\capturas',
            'network_credential' => $settings?->network_username ? [
                'username' => $settings->network_username,
                'password' => $settings->network_password,
            ] : null,
            'hotkey' => $settings?->hotkey,
            'serial_port' => $settings?->serial_port,
            'erp_connection' => $connection ? [
                'db_type' => $connection->db_type->value,
                'host' => $connection->host,
                'port' => $connection->port,
                'service_name' => $connection->service_name,
                'username' => $connection->username,
                'password' => $connection->password,
            ] : null,
            'exams' => $device->examTypes->map(fn (ExamType $exam) => [
                'id' => $exam->id,
                'name' => $exam->name,
                'erp_type' => $exam->erp_type->value,
                'procedure_code' => $exam->procedure_code,
                'destination_folder' => $exam->destination_folder,
                'version' => $exam->version,
                'print_options' => $exam->print_options ?? (object) [],
                'sql' => [
                    'query' => $exam->sql_query,
                    'auth' => $exam->sql_auth,
                    'insert' => $exam->sql_insert,
                    'image' => $exam->sql_image,
                    'update' => $exam->sql_update,
                ],
                'fields' => $exam->fields->map(fn (ExamTypeField $field) => [
                    'kind' => $field->kind->value,
                    'placeholder' => $field->placeholder,
                    'db_column' => $field->db_column,
                    'label' => $field->label,
                    'input' => $field->input,
                    'default_value' => $field->default_value,
                    'options' => $field->options,
                    'required' => $field->required,
                ])->values(),
                'serial_rules' => $exam->serialRules->map(fn (SerialRule $rule) => [
                    'field' => $rule->field,
                    'pattern' => $rule->pattern,
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * Leituras que o operador vê: Parâmetro, Filtro e Informação complementar.
     *
     * @return list<ExamFieldKind>
     */
    public static function operatorKinds(): array
    {
        return [ExamFieldKind::Param, ExamFieldKind::Filter, ExamFieldKind::Info];
    }
}
