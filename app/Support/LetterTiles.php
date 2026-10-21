<?php

namespace App\Support;

use App\Enums\LettersDisplay;
use App\Models\Participant;

/**
 * Tuiles de lettres d'un participant, groupées par mot (null = lettre à trouver).
 *
 * - « À leur position » : chaque lettre à sa place, « MAX IT » → [M,A,X] [I,T]
 * - « Dans le désordre » : ordre de découverte ; une nouvelle lettre s'ajoute
 *   à la suite sans déplacer les autres et les positions ne sont jamais révélées
 */
final class LetterTiles
{
    /** @return list<list<?string>> */
    public static function for(Participant $participant, bool $revealAll = false): array
    {
        $campaign = $participant->campaign;
        $letters = $campaign->letters;

        // Mot trouvé : on affiche le mot complet, dans l'ordre
        if ($revealAll) {
            return self::split($letters->pluck('letter')->all(), $campaign->wordLengths());
        }

        if ($campaign->letters_display === LettersDisplay::Ordered) {
            $collected = $participant->participantLetters()->pluck('campaign_letter_id')->flip();

            return self::split(
                $letters->map(fn ($l) => $collected->has($l->id) ? $l->letter : null)->all(),
                $campaign->wordLengths(),
            );
        }

        $found = $participant->participantLetters()
            ->with('campaignLetter:id,letter')
            ->orderBy('collected_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($pl) => $pl->campaignLetter->letter)
            ->all();

        return [array_pad($found, $letters->count(), null)];
    }

    /** @return list<list<?string>> */
    private static function split(array $tiles, array $wordLengths): array
    {
        $groups = [];

        foreach ($wordLengths ?: [count($tiles)] as $length) {
            $groups[] = array_splice($tiles, 0, $length);
        }

        return array_values(array_filter($groups));
    }
}
