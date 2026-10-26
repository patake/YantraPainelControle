<?php

namespace App\Enums;

/**
 * Função do agente que cada sistema libera.
 */
enum SystemFeature: string
{
    case Printer = 'printer';
    case Screen = 'screen';
    case Serial = 'serial';
}
