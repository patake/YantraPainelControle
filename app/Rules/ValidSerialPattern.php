<?php

namespace App\Rules;

use App\Support\SerialPattern;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regra regex da porta serial no subconjunto que o RE2 do agente aceita (R-EXA-12).
 */
class ValidSerialPattern implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $error = is_string($value) ? SerialPattern::error($value) : __('A regra não é uma expressão regular válida.');

        if ($error !== null) {
            $fail($error);
        }
    }
}
