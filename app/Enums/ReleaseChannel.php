<?php

namespace App\Enums;

/**
 * Canal de distribuição das versões do agente.
 */
enum ReleaseChannel: string
{
    case Pilot = 'pilot';
    case Stable = 'stable';
}
