<?php

namespace App\Enums;

enum ScanSource: string
{
    // Scanner intégré à l'application
    case InApp = 'in_app';
    // URL ouverte par l'appareil photo natif du téléphone
    case DeepLink = 'deep_link';
    // Code court saisi à la main
    case ManualCode = 'manual_code';
}
