<?php

namespace Tests\Unit\Support;

use App\Support\Hotkey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * R-EXA-11: a combinação tem Ctrl, Alt ou Win e uma tecla.
 */
class HotkeyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function validCombinations(): array
    {
        return [
            'sugestão do assistente' => ['Ctrl+Y', 'Ctrl+Y'],
            'minúsculas e espaços' => [' ctrl + shift + y ', 'Ctrl+Shift+Y'],
            'ordem canônica' => ['Shift+Alt+Ctrl+1', 'Ctrl+Alt+Shift+1'],
            'tecla de função' => ['Win+F12', 'Win+F12'],
            'PrintScreen' => ['alt+prtsc', 'Alt+PrintScreen'],
            'nomes longos' => ['Control+Windows+K', 'Ctrl+Win+K'],
        ];
    }

    #[DataProvider('validCombinations')]
    public function test_normalizes_a_valid_combination(string $input, string $expected): void
    {
        $this->assertSame($expected, Hotkey::normalize($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCombinations(): array
    {
        return [
            'sem modificador' => ['Y'],
            'só Shift' => ['Shift+Y'],
            'só modificadores' => ['Ctrl+Alt'],
            'modificador repetido' => ['Ctrl+Ctrl+Y'],
            'tecla desconhecida' => ['Ctrl+Enter'],
            'F13' => ['Ctrl+F13'],
            'duas teclas' => ['Ctrl+Y+Z'],
            'vazio' => [''],
        ];
    }

    #[DataProvider('invalidCombinations')]
    public function test_rejects_an_invalid_combination(string $input): void
    {
        $this->assertNull(Hotkey::normalize($input));
    }
}
