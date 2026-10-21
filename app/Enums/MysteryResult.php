<?php

namespace App\Enums;

/** Résultat d'une proposition de mot mystère. */
enum MysteryResult: string
{
    case Correct = 'correct';
    case Incorrect = 'incorrect';
    case NoAttemptsLeft = 'no_attempts_left';
    case NotCompleted = 'not_completed';
    case AlreadySolved = 'already_solved';
    case CampaignClosed = 'campaign_closed';
    case Blocked = 'blocked';
    case RateLimited = 'rate_limited';
    case Empty = 'empty';

    public function message(int $remaining = 0): string
    {
        return match ($this) {
            self::Correct => 'BRAVO ! Tu as trouvé le mot mystère !',
            self::Incorrect => $remaining > 0
                ? "Ce n'est pas le bon mot. Il te reste {$remaining} essai".($remaining > 1 ? 's' : '').'.'
                : "Ce n'est pas le bon mot. Tu n'as plus d'essai.",
            self::NoAttemptsLeft => "Tu as utilisé tous tes essais.",
            self::NotCompleted => "Collecte d'abord toutes les lettres.",
            self::AlreadySolved => 'Tu as déjà trouvé le mot mystère.',
            self::CampaignClosed => "Le jeu est fermé : impossible de soumettre un mot pour l'instant.",
            self::Blocked => 'Ta participation est suspendue. Rends-toi au stand Orange Money.',
            self::RateLimited => 'Patiente quelques secondes avant de réessayer.',
            self::Empty => 'Saisis un mot composé de lettres.',
        };
    }
}
