<?php

namespace App\Enums;

/**
 * Tipo do ERP no cadastro do exame, no modelo do Yantra Cardio 3.0.
 */
enum ErpType: string
{
    case TasyHtml5 = 'TASY_HTML5';
    case Delphi = 'DELPHI';
    case Java = 'JAVA';
}
