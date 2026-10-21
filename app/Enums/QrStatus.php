<?php

namespace App\Enums;

enum QrStatus: string
{
    case Active = 'active';
    // Désactivé : réversible
    case Disabled = 'disabled';
    // Révoqué : définitif
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Disabled => 'Désactivé',
            self::Revoked => 'Révoqué',
        };
    }
}
