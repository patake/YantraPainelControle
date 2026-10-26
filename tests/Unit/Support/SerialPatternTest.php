<?php

namespace Tests\Unit\Support;

use App\Support\SerialPattern;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-EXA-12: a regra usa só a sintaxe do RE2 e extrai o primeiro grupo de captura.
 */
class SerialPatternTest extends TestCase
{
    private const CONTENT = "PACIENTE: JOAO DA SILVA\r\nFC: 72 bpm\r\nPA: 120/80 mmHg\r\n";

    public function test_extracts_the_first_capture_group(): void
    {
        $this->assertSame('72', SerialPattern::extract('FC:\s*(\d+)', self::CONTENT));
        $this->assertSame('120/80', SerialPattern::extract('PA:\s*([0-9]+/[0-9]+)', self::CONTENT));
    }

    public function test_extracts_the_whole_match_without_a_group(): void
    {
        $this->assertSame('72 bpm', SerialPattern::extract('\d+ bpm', self::CONTENT));
    }

    public function test_returns_null_when_nothing_matches(): void
    {
        $this->assertNull(SerialPattern::extract('SpO2:\s*(\d+)', self::CONTENT));
    }

    public function test_accepts_a_pattern_with_a_tilde(): void
    {
        $this->assertNull(SerialPattern::error('a~b'));
        $this->assertSame('b', SerialPattern::extract('a~(b)', 'a~b'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedPatterns(): array
    {
        return [
            'referência' => ['(\d)\1'],
            'referência com \\g' => ['(\d)\g{1}'],
            'lookahead' => ['FC(?=:)'],
            'lookbehind negativo' => ['(?<!X)FC'],
            'grupo atômico' => ['(?>\d+)'],
            'possessivo' => ['\d++'],
            'recursão' => ['\((?R)?\)'],
            'condicional' => ['(a)?(?(1)b|c)'],
            'sintaxe inválida' => ['FC:(\d+'],
        ];
    }

    #[DataProvider('unsupportedPatterns')]
    public function test_rejects_what_re2_does_not_accept(string $pattern): void
    {
        $this->assertNotNull(SerialPattern::error($pattern));
        $this->assertNull(SerialPattern::extract($pattern, self::CONTENT));
    }

    public function test_accepts_named_groups_and_lazy_quantifiers(): void
    {
        $this->assertNull(SerialPattern::error('PACIENTE:\s*(?P<nome>.+?)\r?\n'));
        $this->assertSame('JOAO DA SILVA', SerialPattern::extract('PACIENTE:\s*(?P<nome>.+?)\r?\n', self::CONTENT));
    }
}
