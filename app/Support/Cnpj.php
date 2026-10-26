<?php

namespace App\Support;

/**
 * Validação, formatação e geração de CNPJ (14 dígitos numéricos).
 */
final class Cnpj
{
    /**
     * Remove pontos, barra e hífen.
     */
    public static function normalize(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj) ?? '';
    }

    /**
     * Confere o tamanho e os dois dígitos verificadores (R-ACE-1).
     */
    public static function isValid(string $cnpj): bool
    {
        $digits = self::normalize($cnpj);

        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        $base = substr($digits, 0, 12);

        return $base.self::checkDigits($base) === $digits;
    }

    public static function format(string $cnpj): string
    {
        $digits = self::normalize($cnpj);

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2),
        );
    }

    /**
     * Gera um CNPJ válido, só com dígitos. Uso em factories e testes.
     */
    public static function generate(): string
    {
        $base = '';

        for ($i = 0; $i < 8; $i++) {
            $base .= random_int(0, 9);
        }

        $base .= '0001';

        return $base.self::checkDigits($base);
    }

    /**
     * Calcula os dois dígitos verificadores para os 12 primeiros dígitos.
     */
    private static function checkDigits(string $base): string
    {
        $first = self::digit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $second = self::digit($base.$first, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $first.$second;
    }

    /**
     * @param  list<int>  $weights
     */
    private static function digit(string $digits, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (int) $digits[$index] * $weight;
        }

        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
