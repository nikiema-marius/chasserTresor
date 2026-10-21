<?php

namespace App\Enums;

/** Affichage des lettres sur le tableau de bord du participant. */
enum LettersDisplay: string
{
    // [A][R][T][?]… : la lettre apparaît à sa position
    case Ordered = 'ordered';
    // Lettres dans le désordre : le participant doit reconstituer le mot
    case Shuffled = 'shuffled';

    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'À leur position',
            self::Shuffled => 'Dans le désordre (anagramme)',
        };
    }
}
