<?php

namespace App\Http\Requests\Agent;

use App\Rules\ValidCnpj;
use App\Support\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dados que o assistente de ativação envia no registro do equipamento.
 */
class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cnpj'))) {
            $this->merge(['cnpj' => Cnpj::normalize($this->input('cnpj'))]);
        }

        if (is_string($this->input('signature'))) {
            $this->merge(['signature' => strtolower($this->input('signature'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', new ValidCnpj],
            'system' => ['required', 'string', 'max:20'],
            'signature' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'hostname' => ['required', 'string', 'max:255'],
            'machine_type' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'macs' => ['nullable', 'array', 'max:20'],
            'macs.*' => ['string', 'max:32'],
            'windows_version' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['nullable', 'string', 'max:20'],
        ];
    }
}
