<?php

namespace App\Enums;

/**
 * Tipo de leitura do exame. Cada leitura preenche um marcador {X} dos SQLs.
 */
enum ExamFieldKind: string
{
    case Internal = 'internal';
    case Param = 'param';
    case Filter = 'filter';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Parâmetro interno',
            self::Param => 'Parâmetro',
            self::Filter => 'Filtro',
            self::Info => 'Informação complementar',
        };
    }
}
