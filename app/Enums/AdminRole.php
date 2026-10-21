<?php

namespace App\Enums;

enum AdminRole: string
{
    // Tous les droits : exports, récompenses, gestion des admins, journal
    case SuperAdmin = 'super_admin';
    // Campagnes et QR codes
    case Admin = 'admin';
    // Lecture seule (statistiques, participants)
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrateur',
            self::Admin => 'Administrateur',
            self::Viewer => 'Lecteur',
        };
    }
}
