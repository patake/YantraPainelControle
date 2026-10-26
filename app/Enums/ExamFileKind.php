<?php

namespace App\Enums;

/**
 * Tipo do arquivo do exame enviado pelo agente.
 */
enum ExamFileKind: string
{
    case Pdf = 'pdf';
    case Png = 'png';
    case SerialRaw = 'serial_raw';
    case SerialData = 'serial_data';
}
