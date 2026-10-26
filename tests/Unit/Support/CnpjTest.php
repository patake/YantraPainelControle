<?php

namespace Tests\Unit\Support;

use App\Support\Cnpj;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class CnpjTest extends TestCase
{
    #[TestWith(['11222333000181'])]
    #[TestWith(['11.222.333/0001-81'])]
    public function test_accepts_a_cnpj_with_valid_check_digits(string $cnpj): void
    {
        $this->assertTrue(Cnpj::isValid($cnpj));
    }

    #[TestWith(['11222333000182'], 'segundo dígito errado')]
    #[TestWith(['11222333000191'], 'primeiro dígito errado')]
    #[TestWith(['1122233300018'], 'só 13 dígitos')]
    #[TestWith(['11111111111111'], 'dígitos repetidos')]
    #[TestWith([''], 'vazio')]
    public function test_rejects_an_invalid_cnpj(string $cnpj): void
    {
        $this->assertFalse(Cnpj::isValid($cnpj));
    }

    public function test_formats_the_digits_with_the_usual_mask(): void
    {
        $this->assertSame('11.222.333/0001-81', Cnpj::format('11222333000181'));
    }

    public function test_generates_valid_cnpjs(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $cnpj = Cnpj::generate();

            $this->assertMatchesRegularExpression('/^\d{14}$/', $cnpj);
            $this->assertTrue(Cnpj::isValid($cnpj), "CNPJ gerado inválido: {$cnpj}");
        }
    }
}
