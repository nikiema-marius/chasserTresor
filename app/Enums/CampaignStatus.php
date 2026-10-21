<?php

namespace App\Enums;

/** Cycle de vie d'une campagne. */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Active => 'Active',
            self::Paused => 'En pause',
            self::Closed => 'Clôturée',
        };
    }
}
