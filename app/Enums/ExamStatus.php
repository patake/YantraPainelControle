<?php

namespace App\Enums;

/**
 * Estado do exame realizado (ciclo de exame).
 */
enum ExamStatus: string
{
    case Starting = 'starting';
    case Capturing = 'capturing';
    case Reviewing = 'reviewing';
    case Recording = 'recording';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Estados de um exame aberto. Um equipamento tem no máximo um exame aberto (R-EXA-1).
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Starting, self::Capturing, self::Reviewing, self::Recording];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }
}
