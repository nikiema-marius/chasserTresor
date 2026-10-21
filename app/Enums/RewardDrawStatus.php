<?php

namespace App\Enums;

enum RewardDrawStatus: string
{
    // Proposition générée, en attente de validation humaine
    case Proposed = 'proposed';
    case Validated = 'validated';
    case Cancelled = 'cancelled';
}
