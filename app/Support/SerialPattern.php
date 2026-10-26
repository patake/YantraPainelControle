<?php

namespace App\Support;

/**
 * Regra regex da porta serial (E8, R-EXA-12).
 *
 * O agente aplica as regras com o motor RE2, que não trava com nenhum conteúdo. O painel testa as
 * regras com o PCRE do PHP. Por isso, o painel recusa os recursos do PCRE que o RE2 não tem:
 * referência a grupo anterior, lookahead, lookbehind, grupo atômico, quantificador possessivo,
 * recursão e condicional. O valor extraído é o primeiro grupo de captura ou, sem grupo, o trecho inteiro.
 */
class SerialPattern
{
    /**
     * Recursos do PCRE que o RE2 não aceita, com a mensagem para o administrador.
     */
    private const UNSUPPORTED = [
        '/\\\\[1-9]|\\\\[gk][{<\'0-9-]/' => 'referência a um grupo anterior',
        '/\(\?<?[=!]/' => 'lookahead ou lookbehind',
        '/\(\?>/' => 'grupo atômico',
        '/[*+?}]\+/' => 'quantificador possessivo',
        '/\(\?(R|[0-9]|&|P>)/' => 'recursão',
        '/\(\?\(/' => 'condicional',
    ];

    /**
     * Mensagem de erro da regra, ou null para uma regra válida.
     */
    public static function error(string $pattern): ?string
    {
        foreach (self::UNSUPPORTED as $detector => $feature) {
            if (preg_match($detector, $pattern)) {
                return __('A regra usa :feature, que o motor RE2 do agente não aceita.', ['feature' => __($feature)]);
            }
        }

        if (@preg_match(self::delimit($pattern), '') === false) {
            return __('A regra não é uma expressão regular válida.');
        }

        return null;
    }

    /**
     * Valor extraído do conteúdo, ou null quando a regra não encontra nada.
     */
    public static function extract(string $pattern, string $content): ?string
    {
        if (self::error($pattern) !== null || ! preg_match(self::delimit($pattern), $content, $matches)) {
            return null;
        }

        return trim($matches[1] ?? $matches[0]);
    }

    private static function delimit(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~u';
    }
}
