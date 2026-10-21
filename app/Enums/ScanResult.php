<?php

namespace App\Enums;

/** Résultat d'une tentative de scan, avec le titre et le message affichés au participant. */
enum ScanResult: string
{
    case Valid = 'valid';
    case AlreadyOwnedQr = 'already_owned_qr';
    case AlreadyOwnedSlot = 'already_owned_slot';
    case AlreadyClaimed = 'already_claimed';
    case InvalidToken = 'invalid_token';
    case OtherCampaign = 'other_campaign';
    case Disabled = 'disabled';
    case Revoked = 'revoked';
    case CampaignNotStarted = 'campaign_not_started';
    case CampaignClosed = 'campaign_closed';
    case OutsideHours = 'outside_hours';
    case TooFast = 'too_fast';
    case RateLimited = 'rate_limited';
    case ParticipantBlocked = 'participant_blocked';

    public function isSuccess(): bool
    {
        return $this === self::Valid;
    }

    public function title(): string
    {
        return match ($this) {
            self::Valid => 'Bravo !',
            self::AlreadyOwnedQr, self::AlreadyOwnedSlot => 'Déjà scanné !',
            self::AlreadyClaimed => 'Trop tard !',
            self::InvalidToken => 'QR code invalide',
            self::OtherCampaign => 'Autre jeu',
            self::Disabled => 'QR code désactivé',
            self::Revoked => 'QR code expiré',
            self::CampaignNotStarted => 'Patience !',
            self::CampaignClosed => 'Jeu terminé',
            self::OutsideHours => 'Jeu fermé',
            self::TooFast => 'Doucement !',
            self::RateLimited => 'Trop de tentatives',
            self::ParticipantBlocked => 'Participation suspendue',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::Valid => 'Bravo ! Tu as trouvé une nouvelle lettre.',
            self::AlreadyOwnedQr => 'Tu as déjà récupéré cette lettre avec ce QR code.',
            self::AlreadyOwnedSlot => 'Tu as déjà cette lettre. Cherche les autres QR codes !',
            self::AlreadyClaimed => 'Un autre participant a déjà récupéré ce QR code.',
            self::InvalidToken => "Ce QR code n'est pas reconnu. Vérifie que tu scannes un QR code officiel du jeu.",
            self::OtherCampaign => "Ce QR code appartient à un autre jeu que celui auquel tu es inscrit.",
            self::Disabled => 'Ce QR code est momentanément désactivé.',
            self::Revoked => "Ce QR code n'est plus valable.",
            self::CampaignNotStarted => "Le jeu n'a pas encore commencé. Reviens à l'ouverture !",
            self::CampaignClosed => 'Le jeu est terminé. Merci pour ta participation !',
            self::OutsideHours => "Le jeu est fermé en dehors des heures d'ouverture du salon.",
            self::TooFast => 'Patiente quelques secondes avant de scanner à nouveau.',
            self::RateLimited => 'Trop de tentatives. Réessaie dans une minute.',
            self::ParticipantBlocked => 'Ta participation est suspendue. Rends-toi au stand Orange Money.',
        };
    }
}
