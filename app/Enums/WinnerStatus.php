<?php

namespace App\Enums;

enum WinnerStatus: string
{
    case Proposed = 'proposed';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Notified = 'notified';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposé',
            self::Validated => 'Validé',
            self::Rejected => 'Rejeté',
            self::Notified => 'Notifié',
            self::Delivered => 'Lot remis',
        };
    }
}
