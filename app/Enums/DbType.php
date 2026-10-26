<?php

namespace App\Enums;

/**
 * Tipo de banco do ERP. O agente tem um driver para cada tipo.
 */
enum DbType: string
{
    case Oracle = 'oracle';
}
