<?php

namespace App\Enums;

/**
 * Estado do comando enviado ao agente.
 */
enum CommandStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Done = 'done';
    case Failed = 'failed';
    case Expired = 'expired';
}
