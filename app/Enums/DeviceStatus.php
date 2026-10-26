<?php

namespace App\Enums;

/**
 * Estado da licença do equipamento no painel (R-LIC).
 */
enum DeviceStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
    case Blocked = 'blocked';

    /**
     * Estados que ocupam uma licença do pacote do cliente (R-LIC-1).
     *
     * @return list<self>
     */
    public static function usingLicense(): array
    {
        return [self::Trial, self::Active];
    }

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Ativo',
            self::Expired => 'Expirado',
            self::Blocked => 'Bloqueado',
        };
    }
}
