<?php

namespace App\Enums;

/** Mode de validation d'un QR code. */
enum ValidationMode: string
{
    // Chaque participant peut scanner le QR une fois
    case PerParticipant = 'per_participant';
    // Seul le premier participant qui scanne le QR obtient la lettre
    case FirstCome = 'first_come';

    public function label(): string
    {
        return match ($this) {
            self::PerParticipant => 'Multi-participants (1 scan par participant)',
            self::FirstCome => 'Unique (premier arrivé)',
        };
    }
}
