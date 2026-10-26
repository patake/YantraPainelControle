<?php

namespace App\Enums;

/**
 * Papéis dos usuários do painel. Administrador e suporte formam a Staff Weega.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Support = 'support';
    case Operator = 'operator';

    public function isStaff(): bool
    {
        return $this !== self::Operator;
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Support => 'Suporte',
            self::Operator => 'Operador',
        };
    }
}
