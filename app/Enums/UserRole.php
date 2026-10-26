<?php

namespace App\Enums;

/**
 * Papéis dos usuários do painel. Administrador e suporte formam a Staff Weega.
 * Operador e gestor são usuários do hospital e usam a área Cliente (R-ACE-14).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Support = 'support';
    case Manager = 'manager';
    case Operator = 'operator';

    public function isStaff(): bool
    {
        return in_array($this, [self::Admin, self::Support], true);
    }

    /**
     * Usuário do hospital: vê só os clientes vinculados.
     */
    public function isClientUser(): bool
    {
        return ! $this->isStaff();
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Support => 'Suporte',
            self::Manager => 'Gestor',
            self::Operator => 'Operador',
        };
    }
}
