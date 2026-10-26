<?php

namespace App\Rules;

use App\Support\Hotkey;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Combinação de teclas da captura de tela com Ctrl, Alt ou Win e uma tecla (R-EXA-11).
 */
class ValidHotkey implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Hotkey::normalize($value) === null) {
            $fail(__('Use Ctrl, Alt ou Win e uma tecla: letra, número, F1 a F12 ou PrintScreen. Exemplo: Ctrl+Y.'));
        }
    }
}
