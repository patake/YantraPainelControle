<?php

namespace App\Rules;

use App\Support\Cnpj;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CNPJ com 14 dígitos e dígitos verificadores válidos (R-ACE-1). Aceita a máscara.
 */
class ValidCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Cnpj::isValid($value)) {
            $fail(__('O CNPJ informado não é válido.'));
        }
    }
}
