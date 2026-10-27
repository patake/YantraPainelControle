<?php

namespace App\Support;

/**
 * Código de ativação do cliente (R-LIC-11).
 *
 * O CNPJ é público. Por isso, a ativação de um equipamento pede também o código do cliente,
 * que a Staff Weega passa ao técnico. O código tem 8 caracteres, no formato ABCD-2345, sem os
 * caracteres que se confundem (0, O, 1, I e L).
 */
class ActivationCode
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return self::format($code);
    }

    /**
     * Código sem o hífen e sem os espaços, em maiúsculas.
     */
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    public static function format(string $code): string
    {
        $code = self::normalize($code);

        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    /**
     * Compara em tempo constante. O técnico digita com ou sem o hífen, em maiúsculas ou minúsculas.
     */
    public static function matches(?string $expected, ?string $typed): bool
    {
        if ($expected === null || $typed === null || self::normalize($typed) === '') {
            return false;
        }

        return hash_equals(self::normalize($expected), self::normalize($typed));
    }
}
