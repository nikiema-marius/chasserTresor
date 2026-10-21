<?php

namespace App\Enums;

enum RewardMode: string
{
    // Les N premiers à trouver le mot
    case FirstN = 'first_n';
    // Tirage au sort parmi les participants admissibles
    case Draw = 'draw';
    // Classement par temps (inscription → réussite)
    case Ranking = 'ranking';

    public function label(): string
    {
        return match ($this) {
            self::FirstN => 'Premiers arrivés',
            self::Draw => 'Tirage au sort',
            self::Ranking => 'Classement par temps',
        };
    }
}
