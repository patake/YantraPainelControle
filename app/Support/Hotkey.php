<?php

namespace App\Support;

/**
 * Combinação de teclas da captura de tela (E7, R-EXA-11).
 *
 * A combinação tem Ctrl, Alt ou Win e uma tecla: letra, número, F1 a F12 ou PrintScreen.
 * Shift sozinho não vale, porque Shift + letra digita texto na janela do usuário.
 */
class Hotkey
{
    /**
     * Combinação padrão do equipamento sem combinação gravada (R-EXA-11).
     */
    public const DEFAULT = 'Ctrl+Y';

    /**
     * Modificadores na ordem do formato canônico.
     */
    private const MODIFIERS = ['CTRL' => 'Ctrl', 'CONTROL' => 'Ctrl', 'ALT' => 'Alt', 'SHIFT' => 'Shift', 'WIN' => 'Win', 'WINDOWS' => 'Win'];

    private const ORDER = ['Ctrl', 'Alt', 'Shift', 'Win'];

    /**
     * Formato canônico, por exemplo "Ctrl+Shift+Y", ou null para uma combinação inválida.
     */
    public static function normalize(string $value): ?string
    {
        $parts = array_map(fn (string $part) => strtoupper(trim($part)), explode('+', $value));
        $key = array_pop($parts);
        $modifiers = [];

        foreach ($parts as $part) {
            $modifier = self::MODIFIERS[$part] ?? null;

            if ($modifier === null || in_array($modifier, $modifiers, true)) {
                return null;
            }

            $modifiers[] = $modifier;
        }

        $key = match (true) {
            (bool) preg_match('/^[A-Z0-9]$/', $key) => $key,
            (bool) preg_match('/^F([1-9]|1[0-2])$/', $key) => $key,
            in_array($key, ['PRINTSCREEN', 'PRTSC', 'PRTSCN'], true) => 'PrintScreen',
            default => null,
        };

        if ($key === null || array_diff($modifiers, ['Shift']) === []) {
            return null;
        }

        $ordered = array_values(array_intersect(self::ORDER, $modifiers));

        return implode('+', [...$ordered, $key]);
    }
}
