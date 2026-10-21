<?php

namespace App\Enums;

enum ParticipantStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Disqualified = 'disqualified';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Blocked => 'Bloqué',
            self::Disqualified => 'Disqualifié',
        };
    }
}
